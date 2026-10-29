<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Support\GeniusPay;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Demande faite par un pressing depuis sa page Abonnement : une option (une
 * fonction à la carte), des commandes en plus pour le mois, une autre
 * formule ou le renouvellement de l'abonnement. Pas d'API de paiement :
 * l'équipe PressLink envoie un lien de paiement (Wave Business) du montant
 * demandé, puis active la demande depuis la fiche du pressing une fois payé.
 * Quand GeniusPay est configuré, le pressing peut aussi payer en ligne : la
 * demande s'active alors dès que GeniusPay confirme le paiement.
 */
class SubscriptionRequest extends Model
{
    public const KIND_OPTION = 'option';

    public const KIND_ORDERS = 'orders';

    public const KIND_PLAN = 'plan';

    public const KIND_RENEWAL = 'renouvellement';

    public const MONTHLY = 'monthly';

    public const ANNUAL = 'annual';

    public const PENDING = 'pending';

    public const DONE = 'done';

    public const DECLINED = 'declined';

    /** Commandes en plus proposées par défaut pour le mois en cours : [nombre, prix]. */
    public const DEFAULT_ORDER_PACKS = [[50, 1_000], [100, 1_500], [500, 3_500]];

    /**
     * Moyens de paiement en ligne proposés sur le récapitulatif (codes GeniusPay).
     *
     * @var array<string, string>
     */
    public const ONLINE_METHODS = [
        'wave' => 'Wave',
        'orange_money' => 'Orange Money',
        'mtn_money' => 'MTN MoMo',
        'moov_money' => 'Moov Money',
        'card' => 'Carte bancaire',
    ];

    /** Moyens qui demandent le numéro du payeur (validation sur son téléphone). */
    public const PHONE_METHODS = ['orange_money', 'mtn_money', 'moov_money'];

    /**
     * Frais de l'opérateur ajoutés au prix, en % (réglables par le Super Admin) :
     * estimation tirée de l'exemple publié par GeniusPay (environ 150 F sur 10 000 F).
     */
    public const DEFAULT_PAYMENT_FEE_RATE = 1.5;

    /** Plafond des frais acceptés sur un paiement (%), contrôle du montant reçu. */
    public const MAX_PAYMENT_FEE_RATE = 10;

    /** Durée de vie d'une page de paiement GeniusPay (24 h), avec une marge. */
    public const ONLINE_PAYMENT_HOURS = 23;

    protected $fillable = ['pressing_id', 'requested_by', 'kind', 'feature', 'plan', 'extra_orders', 'cycle', 'amount_fcfa', 'payment_link', 'payment_reference', 'payment_checkout_url', 'payment_started_at', 'paid_online_at', 'payment_method', 'payment_fee_fcfa', 'paid_amount_fcfa', 'gateway_fees_fcfa', 'net_received_fcfa', 'status', 'decline_reason', 'handled_at', 'handled_by'];

    protected function casts(): array
    {
        return [
            'feature' => Feature::class,
            'plan' => SubscriptionPlan::class,
            'extra_orders' => 'integer',
            'amount_fcfa' => 'integer',
            'handled_at' => 'datetime',
            'payment_started_at' => 'datetime',
            'paid_online_at' => 'datetime',
            'payment_fee_fcfa' => 'integer',
            'paid_amount_fcfa' => 'integer',
            'gateway_fees_fcfa' => 'integer',
            'net_received_fcfa' => 'integer',
        ];
    }

    /**
     * Commandes en plus proposées au pressing (nombre et prix), réglées
     * depuis le Super Admin.
     *
     * @return list<array{count: int, price: ?int}>
     */
    public static function orderPacks(): array
    {
        $packs = Setting::read('order_packs');

        // Jamais réglé : paquets par défaut. Réglé à vide : aucun paquet proposé.
        if (! is_array($packs)) {
            return array_map(fn (array $pack) => ['count' => $pack[0], 'price' => $pack[1]], self::DEFAULT_ORDER_PACKS);
        }

        return array_values(array_map(fn (array $pack) => ['count' => (int) $pack['count'], 'price' => isset($pack['price']) ? (int) $pack['price'] : null], $packs));
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Membre de l'équipe PressLink qui a activé ou refusé la demande.
     *
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** @param  Builder<self>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** « Option Exports Excel et PDF », « 500 commandes en plus ce mois-ci », « Passer à Business ». */
    public function label(): string
    {
        return match ($this->kind) {
            self::KIND_OPTION => 'Option « '.$this->feature?->label().' »',
            self::KIND_ORDERS => number_format((int) $this->extra_orders, 0, ',', ' ').' commandes en plus ce mois-ci',
            self::KIND_RENEWAL => 'Renouveler la formule '.$this->plan?->label().($this->cycle === self::ANNUAL ? ' pour un an' : ' pour un mois'),
            default => 'Passer à la formule '.$this->plan?->label().($this->cycle === self::ANNUAL ? ' (à l\'année)' : ''),
        };
    }

    /**
     * Active ce qui a été demandé sur l'abonnement du pressing (après paiement).
     * Une seule fois : un double clic ou un second onglet ne l'applique pas deux fois.
     *
     * @param  bool  $byCurrentUser  false = activation automatique (paiement en ligne) : personne n'est noté comme l'ayant traitée
     *
     * @throws RuntimeException demande déjà traitée, ou renouvellement d'une formule qui a changé depuis
     */
    public function apply(bool $byCurrentUser = true): void
    {
        DB::transaction(function () use ($byCurrentUser) {
            $locked = self::whereKey($this->id)->lockForUpdate()->first();
            if ($locked === null || ! $locked->isPending()) {
                throw new RuntimeException('Cette demande a déjà été traitée.');
            }

            $subscription = $this->pressing->subscription;

            if ($subscription !== null) {
                match ($this->kind) {
                    // Déjà incluse dans la formule (changée entre-temps) : rien à ajouter.
                    self::KIND_OPTION => $subscription->plan->includes($this->feature) ? null : $subscription->addAddon($this->feature),
                    self::KIND_ORDERS => $subscription->addExtraOrders((int) $this->extra_orders),
                    self::KIND_PLAN => $this->changePlan($subscription),
                    self::KIND_RENEWAL => $this->renew($subscription),
                };
            }

            $locked->update(['status' => self::DONE, 'handled_at' => now(), 'handled_by' => $byCurrentUser ? auth()->id() : null]);
        });

        $this->refresh();

        // Validée à la main par l'équipe avec un montant : un paiement de l'historique.
        if ($byCurrentUser && $this->isPayment() && $this->paid_online_at === null) {
            PaymentAttempt::recordManual($this);
        }
    }

    /**
     * Nouvelle formule payée, appliquée tout de suite et l'abonnement actif
     * (même s'il était en essai ou expiré). Montée en gamme : la nouvelle période
     * part d'aujourd'hui (les jours restants ont été déduits du prix). Descente :
     * la nouvelle période commence à la fin de la période déjà payée.
     */
    private function changePlan(Subscription $subscription): void
    {
        ['starts_at' => $startsAt, 'ends_at' => $endsAt] = $this->paidPeriod($subscription, extend: false);

        $subscription->update([
            'plan' => $this->plan,
            'orders_limit' => $this->plan->ordersLimit(),
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => $this->cycle === self::ANNUAL ? self::ANNUAL : self::MONTHLY,
            'current_period_starts_at' => $startsAt,
            'current_period_ends_at' => $endsAt,
        ]);
    }

    /** Rang de la formule (Starter < Business < Premium). */
    private static function planRank(?SubscriptionPlan $plan): int
    {
        return $plan === null ? -1 : (int) array_search($plan, SubscriptionPlan::cases(), true);
    }

    /** Passage à une formule supérieure à la formule actuelle. */
    public function isPlanUpgrade(): bool
    {
        return $this->kind === self::KIND_PLAN && self::planRank($this->plan) > self::planRank($this->pressing->subscription?->plan);
    }

    /** Période payée en cours et pas encore terminée (hors essai gratuit). */
    private static function hasPaidTimeLeft(?Subscription $subscription): bool
    {
        return $subscription !== null && $subscription->status === SubscriptionStatus::Active
            && $subscription->current_period_ends_at?->isFuture() === true;
    }

    /**
     * Valeur des jours payés non utilisés (F CFA, arrondie à la centaine
     * inférieure) : le temps qui reste, au tarif journalier de la formule
     * actuelle (prix du mois ÷ 365/12 jours, ou prix de l'année ÷ 365 jours).
     * Une période prolongée plusieurs fois compte pour tout son temps restant.
     */
    public static function unusedCredit(?Subscription $subscription): int
    {
        if (! self::hasPaidTimeLeft($subscription)) {
            return 0;
        }

        $annual = $subscription->billing_cycle === self::ANNUAL;
        $price = (int) ($annual ? $subscription->plan->annualPriceFcfa() : $subscription->plan->monthlyPriceFcfa());
        $cycleSeconds = ($annual ? 365 : 365 / 12) * 86400;
        $start = $subscription->current_period_starts_at;
        $left = max(0, ($start?->isFuture() ? $start : now())->diffInSeconds($subscription->current_period_ends_at));

        return (int) (floor($price * $left / $cycleSeconds / 100) * 100);
    }

    /**
     * Prix d'un changement de formule. Montée en gamme pendant une période
     * payée : les jours restants sont déduits (au plus jusqu'au montant minimum
     * d'un paiement en ligne). Descente ou essai : prix plein.
     *
     * @return array{price: ?int, credit: int, amount: ?int}
     */
    public static function planChangePrice(?Subscription $subscription, SubscriptionPlan $plan, ?string $cycle): array
    {
        $price = $cycle === self::ANNUAL ? $plan->annualPriceFcfa() : $plan->monthlyPriceFcfa();
        $upgrade = self::planRank($plan) > self::planRank($subscription?->plan);
        $credit = ($price !== null && $upgrade) ? min(self::unusedCredit($subscription), max(0, $price - GeniusPay::MIN_AMOUNT)) : 0;

        return ['price' => $price, 'credit' => $credit, 'amount' => $price === null ? null : $price - $credit];
    }

    /**
     * Montant à jour d'un changement de formule pas encore en paiement : la
     * déduction baisse chaque jour, elle est recalculée avant d'afficher ou de payer.
     */
    public function refreshPlanChangeAmount(): void
    {
        if ($this->kind !== self::KIND_PLAN || $this->plan === null || ! $this->isPending() || $this->hasOnlinePaymentInProgress()) {
            return;
        }

        $amount = self::planChangePrice($this->pressing->subscription, $this->plan, $this->cycle)['amount'];
        if ($amount !== $this->amount_fcfa) {
            $this->update(['amount_fcfa' => $amount]);
        }
    }

    /** Abonnement payé pour un mois (ou un an) de plus, sur la même formule. */
    private function renew(Subscription $subscription): void
    {
        if ($this->plan !== null && $subscription->plan !== $this->plan) {
            throw new RuntimeException("La formule a changé depuis cette demande ({$this->plan->label()} → {$subscription->plan->label()}) : refusez-la et demandez au pressing d'en refaire une.");
        }

        $this->openPaidPeriod($subscription, extend: true);
    }

    /** Période payée ouverte sur l'abonnement (voir paidPeriod). */
    private function openPaidPeriod(Subscription $subscription, bool $extend): void
    {
        ['starts_at' => $startsAt, 'ends_at' => $endsAt] = $this->paidPeriod($subscription, $extend);

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => $this->cycle === self::ANNUAL ? self::ANNUAL : self::MONTHLY,
            'current_period_starts_at' => $startsAt,
            'current_period_ends_at' => $endsAt,
        ]);
    }

    /**
     * Période que paie cette demande. En prolongation d'une période active pas
     * encore finie, la fin est repoussée d'un mois (ou d'un an). Pendant un essai
     * gratuit, la période payée commence à la fin de l'essai : le pressing ne
     * perd aucun jour gratuit. Sinon, elle part d'aujourd'hui.
     *
     * @return array{starts_at: Carbon, ends_at: Carbon, after_trial: bool}
     */
    private function paidPeriod(Subscription $subscription, bool $extend): array
    {
        $add = fn (Carbon $from) => $this->cycle === self::ANNUAL ? $from->copy()->addYear() : $from->copy()->addMonthNoOverflow();
        $end = $subscription->current_period_ends_at;

        if ($extend && self::hasPaidTimeLeft($subscription)) {
            return ['starts_at' => $subscription->current_period_starts_at ?? now(), 'ends_at' => $add($end), 'after_trial' => false];
        }

        // Formule inférieure : rien de ce qui est payé n'est perdu, la nouvelle période suit.
        if (! $extend && $this->kind === self::KIND_PLAN && self::hasPaidTimeLeft($subscription)
            && self::planRank($this->plan) < self::planRank($subscription->plan)) {
            return ['starts_at' => $end->copy(), 'ends_at' => $add($end), 'after_trial' => false];
        }

        if ($subscription->isInTrial() && $subscription->trial_ends_at !== null) {
            return ['starts_at' => $subscription->trial_ends_at->copy(), 'ends_at' => $add($subscription->trial_ends_at), 'after_trial' => true];
        }

        return ['starts_at' => now(), 'ends_at' => $add(now()), 'after_trial' => false];
    }

    /**
     * Ce que le paiement ouvrira (formule ou renouvellement), pour l'annoncer
     * avant de payer ; null pour une option ou des commandes en plus.
     *
     * @return array{starts_at: Carbon, ends_at: Carbon, after_trial: bool}|null
     */
    public function periodAfterPayment(): ?array
    {
        $subscription = $this->pressing->subscription;

        if ($subscription === null || ! in_array($this->kind, [self::KIND_PLAN, self::KIND_RENEWAL], true)) {
            return null;
        }

        return $this->paidPeriod($subscription, extend: $this->kind === self::KIND_RENEWAL);
    }

    /**
     * Montant à payer, d'après les prix réglés par le Super Admin (null si
     * le prix n'est pas fixé : l'équipe PressLink le communique).
     */
    public static function priceFor(string $kind, ?Subscription $subscription, ?Feature $feature = null, ?SubscriptionPlan $plan = null, ?int $orders = null, ?string $cycle = null): ?int
    {
        $planPrice = fn (?SubscriptionPlan $p) => $p === null ? null : ($cycle === self::ANNUAL ? $p->annualPriceFcfa() : $p->monthlyPriceFcfa());

        return match ($kind) {
            self::KIND_OPTION => $feature?->priceFcfa(),
            self::KIND_ORDERS => collect(self::orderPacks())->firstWhere('count', $orders)['price'] ?? null,
            self::KIND_PLAN => $plan === null ? null : self::planChangePrice($subscription, $plan, $cycle)['amount'],
            self::KIND_RENEWAL => $planPrice($subscription?->plan),
            default => null,
        };
    }

    /** Message WhatsApp prêt à envoyer au pressing, avec le lien de paiement. */
    public function paymentMessage(): string
    {
        $amount = $this->amount_fcfa !== null ? number_format($this->amount_fcfa, 0, ',', ' ').' F' : null;

        return "Bonjour, ici l'équipe PressLink. Pour votre demande « {$this->label()} »"
            .($amount ? ", le montant est de {$amount}" : '').'. '
            .($this->payment_link ? "Vous pouvez payer avec Wave ici : {$this->payment_link} " : '')
            .'Nous activons votre demande dès le paiement reçu. Merci !';
    }

    /** Refus, avec un motif facultatif montré au pressing sur sa page Abonnement. */
    public function decline(?string $reason = null): void
    {
        $reason = trim((string) $reason);

        $this->update([
            'status' => self::DECLINED,
            'decline_reason' => $reason === '' ? null : $reason,
            'handled_at' => now(),
            'handled_by' => auth()->id(),
        ]);
    }

    /**
     * Taux des frais de l'opérateur, par moyen de paiement (%).
     *
     * @return array<string, float>
     */
    public static function paymentFeeRates(): array
    {
        $saved = Setting::read('payment_fee_rates');
        $saved = is_array($saved) ? $saved : [];

        return collect(self::ONLINE_METHODS)
            ->mapWithKeys(fn (string $label, string $code) => [$code => (float) ($saved[$code] ?? self::DEFAULT_PAYMENT_FEE_RATE)])
            ->all();
    }

    /** Frais de l'opérateur payés par le pressing, arrondis aux 5 F supérieurs (0 sans moyen choisi). */
    public static function paymentFeeFor(?int $amount, ?string $method): int
    {
        $rate = $method === null ? 0.0 : (self::paymentFeeRates()[$method] ?? 0.0);

        return $amount === null || $rate <= 0 ? 0 : (int) (ceil($amount * $rate / 100 / 5) * 5);
    }

    /** Le pressing peut payer cette demande en ligne (GeniusPay configuré, montant connu). */
    public function canBePaidOnline(): bool
    {
        return $this->isPending() && GeniusPay::isConfigured() && (int) $this->amount_fcfa >= GeniusPay::MIN_AMOUNT;
    }

    /** Une page de paiement en ligne a été ouverte et n'a pas encore expiré. */
    public function hasOnlinePaymentInProgress(): bool
    {
        return $this->payment_reference !== null && $this->paid_online_at === null
            && $this->payment_started_at?->isAfter(now()->subHours(self::ONLINE_PAYMENT_HOURS)) === true;
    }

    /**
     * Adresse où envoyer le pressing pour payer : la page déjà ouverte pour ce
     * moyen tant qu'elle est valable (un seul paiement par demande), sinon une
     * nouvelle. Avec un moyen choisi (Wave, Orange Money…), le pressing va
     * directement chez l'opérateur ; sans, il choisit sur la page GeniusPay.
     * Le montant est celui de la demande, calculé par le serveur.
     *
     * @param  string|null  $method  code de ONLINE_METHODS
     * @param  string|null  $phone  numéro du payeur (Mobile Money)
     *
     * @throws RuntimeException GeniusPay injoignable ou paiement refusé
     */
    public function startOnlinePayment(string $returnUrl, ?string $method = null, ?string $phone = null): string
    {
        if ($this->hasOnlinePaymentInProgress() && $this->payment_checkout_url && $this->payment_method === $method) {
            return $this->payment_checkout_url;
        }

        // Le pressing change de moyen de paiement : la page précédente est abandonnée.
        if ($this->hasOnlinePaymentInProgress() && $this->payment_reference) {
            $this->forgetFailedOnlinePayment($this->payment_reference, PaymentAttempt::CANCELLED, 'Autre moyen de paiement choisi');
        }

        $this->refreshPlanChangeAmount();
        $fee = self::paymentFeeFor($this->amount_fcfa, $method);

        // Chaque tentative a sa référence PressLink, donnée aussi à GeniusPay.
        $attempt = PaymentAttempt::startOnline($this, $method, $fee);
        $pressing = $this->pressing;

        try {
            $transaction = GeniusPay::createPayment(array_filter([
                'amount' => (int) $this->amount_fcfa + $fee,
                'description' => mb_substr('PressLink · '.$pressing->name.' · '.$this->label(), 0, 500),
                'external_reference' => $attempt->reference,
                'success_url' => $returnUrl,
                'error_url' => $returnUrl,
                'payment_method' => $method,
                'customer' => array_filter(['name' => $pressing->name, 'email' => $this->requester?->email, 'phone' => $phone ? PhoneNumber::normalize($phone) : null]),
                'metadata' => ['subscription_request_id' => $this->id, 'pressing_id' => $pressing->id, 'presslink_reference' => $attempt->reference],
            ]));
        } catch (Throwable $e) {
            $attempt->markFailed(PaymentAttempt::FAILED, 'Page de paiement non créée par GeniusPay : '.$e->getMessage());

            throw $e;
        }

        $attempt->update(['gateway_reference' => $transaction['reference'], 'environment' => $transaction['environment'] ?? GeniusPay::environment()]);

        // Mode direct : la page de l'opérateur (payment_url) ; sinon la page GeniusPay.
        $url = $transaction['payment_url'] ?? $transaction['checkout_url'];

        $this->update([
            'payment_reference' => $transaction['reference'],
            'payment_checkout_url' => $url,
            'payment_started_at' => now(),
            'payment_method' => $method,
            'payment_fee_fcfa' => $fee,
        ]);

        return $url;
    }

    /**
     * Paiement refusé, annulé ou expiré chez GeniusPay : la tentative est notée
     * dans l'historique (avec le motif), et sa page ne peut pas être réutilisée,
     * le prochain « Payer » en ouvrira une nouvelle.
     *
     * @param  string  $status  PaymentAttempt::FAILED, CANCELLED ou EXPIRED
     */
    public function forgetFailedOnlinePayment(?string $reference = null, string $status = PaymentAttempt::FAILED, ?string $reason = null): void
    {
        $gatewayReference = $reference ?? $this->payment_reference;
        if ($gatewayReference !== null) {
            PaymentAttempt::where('gateway_reference', $gatewayReference)->where('status', PaymentAttempt::PENDING)
                ->get()->each->markFailed($status, $reason);
        }

        if ($this->paid_online_at !== null || ($reference !== null && $reference !== $this->payment_reference)) {
            return;
        }

        $this->update(['payment_reference' => null, 'payment_checkout_url' => null, 'payment_started_at' => null]);
    }

    /**
     * Paiement confirmé par GeniusPay (notification signée ou vérification au
     * retour du pressing) : la demande s'active, une seule fois. Ignoré si le
     * paiement n'est pas réussi, si le montant ne correspond pas ou s'il vient
     * de l'autre environnement (test ou réel).
     *
     * @param  array<string, mixed>  $transaction
     * @return bool true si cet appel a activé la demande
     */
    public function confirmOnlinePayment(array $transaction, ?string $environment = null): bool
    {
        if (($transaction['status'] ?? null) !== 'completed') {
            return false;
        }

        $environment ??= $transaction['environment'] ?? null;
        if ($environment !== null && $environment !== GeniusPay::environment()) {
            Log::warning('GeniusPay : paiement d\'un autre environnement ignoré.', ['request' => $this->id, 'environment' => $environment]);

            return false;
        }

        // Le prix de la demande, plus les frais de l'opérateur payés par le pressing.
        $paid = (int) ($transaction['amount'] ?? 0);
        $price = (int) $this->amount_fcfa;
        if ($paid < $price || $paid > $price + (int) ceil($price * self::MAX_PAYMENT_FEE_RATE / 100)) {
            Log::warning('GeniusPay : montant différent de la demande, paiement non activé.', ['request' => $this->id, 'amount' => $transaction['amount'] ?? null, 'expected' => $price]);
            PaymentAttempt::where('gateway_reference', $transaction['reference'] ?? $this->payment_reference)->update([
                'failure_reason' => 'Montant reçu ('.number_format($paid, 0, ',', ' ').' F) différent du prix : à vérifier par l\'équipe',
            ]);

            return false;
        }

        try {
            $this->apply(byCurrentUser: false);
            $activated = true;
        } catch (RuntimeException $e) {
            // Déjà activée (notification reçue deux fois), ou à voir par l'équipe
            // (demande refusée entre-temps, formule changée) : le paiement reste noté.
            $activated = false;
            if (! $this->fresh()->isPayment() || $this->paid_online_at === null) {
                Log::warning('GeniusPay : paiement reçu pour une demande non activable.', ['request' => $this->id, 'reason' => $e->getMessage()]);
            }
        }

        // Frais retenus par GeniusPay et net reçu : dans la transaction, sinon demandés à GeniusPay.
        if (! isset($transaction['fees'], $transaction['net_amount']) && filled($transaction['reference'] ?? $this->payment_reference)) {
            try {
                $transaction += GeniusPay::findPayment($transaction['reference'] ?? $this->payment_reference) ?? [];
            } catch (Throwable $e) {
                Log::info('GeniusPay : frais du paiement non récupérés.', ['request' => $this->id, 'error' => $e->getMessage()]);
            }
        }

        $this->forceFill([
            'paid_online_at' => $this->paid_online_at ?? now(),
            'payment_method' => $transaction['payment_method'] ?? $transaction['gateway'] ?? $this->payment_method,
            'payment_reference' => $this->payment_reference ?? ($transaction['reference'] ?? null),
            'paid_amount_fcfa' => $paid,
            'payment_fee_fcfa' => $paid - $price,
            'gateway_fees_fcfa' => isset($transaction['fees']) ? (int) $transaction['fees'] : $this->gateway_fees_fcfa,
            'net_received_fcfa' => isset($transaction['net_amount']) ? (int) $transaction['net_amount'] : $this->net_received_fcfa,
        ])->save();

        $this->recordPaidAttempt($transaction, $environment);

        return $activated;
    }

    /**
     * La tentative de ce paiement passe à « Payé » dans l'historique (même si un
     * échec avait été noté avant : l'argent est reçu). Un paiement sans
     * tentative connue (démarré avant l'historique) y est ajouté.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function recordPaidAttempt(array $transaction, ?string $environment): void
    {
        $gatewayReference = $transaction['reference'] ?? $this->payment_reference;
        $attempt = $gatewayReference !== null
            ? PaymentAttempt::where('gateway_reference', $gatewayReference)->latest('id')->first()
            : null;
        $attempt ??= tap(PaymentAttempt::startOnline($this, $this->payment_method, (int) $this->payment_fee_fcfa))
            ->update(['gateway_reference' => $gatewayReference]);

        $attempt->update([
            'status' => PaymentAttempt::PAID,
            'payment_method' => $this->payment_method ?? $attempt->payment_method,
            'paid_amount_fcfa' => $this->paid_amount_fcfa,
            'gateway_fees_fcfa' => $this->gateway_fees_fcfa,
            'net_received_fcfa' => $this->net_received_fcfa,
            'environment' => $environment ?? $attempt->environment ?? GeniusPay::environment(),
            'failure_reason' => null,
            'completed_at' => $attempt->status === PaymentAttempt::PAID ? $attempt->completed_at : now(),
        ]);
    }

    /** Payée et activée, avec un montant : une ligne de l'historique des paiements. */
    public function isPayment(): bool
    {
        return $this->status === self::DONE && (int) $this->amount_fcfa > 0;
    }
}
