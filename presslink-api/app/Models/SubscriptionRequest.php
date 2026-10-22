<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demande faite par un pressing depuis sa page Abonnement : une option (une
 * fonction à la carte), des commandes en plus pour le mois, une autre
 * formule ou le renouvellement de l'abonnement. Pas d'API de paiement :
 * l'équipe PressLink envoie un lien de paiement (Wave Business) du montant
 * demandé, puis active la demande depuis la fiche du pressing une fois payé.
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

    protected $fillable = ['pressing_id', 'requested_by', 'kind', 'feature', 'plan', 'extra_orders', 'cycle', 'amount_fcfa', 'payment_link', 'status', 'handled_at'];

    protected function casts(): array
    {
        return [
            'feature' => Feature::class,
            'plan' => SubscriptionPlan::class,
            'extra_orders' => 'integer',
            'amount_fcfa' => 'integer',
            'handled_at' => 'datetime',
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

    /** Active ce qui a été demandé sur l'abonnement du pressing (après paiement). */
    /**
     * Active ce qui a été demandé sur l'abonnement du pressing (après paiement).
     * Une seule fois : un double clic ou un second onglet ne l'applique pas deux fois.
     *
     * @throws RuntimeException demande déjà traitée, ou renouvellement d'une formule qui a changé depuis
     */
    public function apply(): void
    {
        DB::transaction(function () {
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

            $locked->update(['status' => self::DONE, 'handled_at' => now()]);
        });

        $this->refresh();
    }

    /**
     * Nouvelle formule payée : elle s'applique tout de suite, pour la durée
     * payée (un mois ou un an à partir d'aujourd'hui), et l'abonnement est actif
     * (même s'il était en essai ou expiré).
     */
    private function changePlan(Subscription $subscription): void
    {
        $subscription->update(['plan' => $this->plan, 'orders_limit' => $this->plan->ordersLimit()]);
        $this->openPaidPeriod($subscription, extend: false);
    }

    /** Abonnement payé pour un mois (ou un an) de plus, sur la même formule. */
    private function renew(Subscription $subscription): void
    {
        if ($this->plan !== null && $subscription->plan !== $this->plan) {
            throw new RuntimeException("La formule a changé depuis cette demande ({$this->plan->label()} → {$subscription->plan->label()}) : refusez-la et demandez au pressing d'en refaire une.");
        }

        $this->openPaidPeriod($subscription, extend: true);
    }

    /**
     * Période payée. En prolongation d'une période active pas encore finie, la
     * fin est repoussée d'un mois (ou d'un an) ; sinon la période part d'aujourd'hui.
     */
    private function openPaidPeriod(Subscription $subscription, bool $extend): void
    {
        $annual = $this->cycle === self::ANNUAL;
        $end = $subscription->current_period_ends_at;
        $ongoing = $extend && $subscription->status === SubscriptionStatus::Active && $end !== null && $end->isFuture();
        $from = $ongoing ? $end->copy() : now();

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => $annual ? self::ANNUAL : self::MONTHLY,
            'current_period_starts_at' => $ongoing ? ($subscription->current_period_starts_at ?? now()) : now(),
            'current_period_ends_at' => $annual ? $from->addYear() : $from->addMonthNoOverflow(),
        ]);
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
            self::KIND_PLAN => $planPrice($plan),
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

    public function decline(): void
    {
        $this->update(['status' => self::DECLINED, 'handled_at' => now()]);
    }
}
