<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

        if (! is_array($packs) || $packs === []) {
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
    public function apply(): void
    {
        $subscription = $this->pressing->subscription;

        if ($subscription !== null) {
            match ($this->kind) {
                self::KIND_OPTION => $subscription->addAddon($this->feature),
                self::KIND_ORDERS => $subscription->addExtraOrders((int) $this->extra_orders),
                self::KIND_PLAN => $subscription->update(['plan' => $this->plan, 'orders_limit' => $this->plan->ordersLimit()]),
                self::KIND_RENEWAL => $this->renew($subscription),
            };
        }

        $this->update(['status' => self::DONE, 'handled_at' => now()]);
    }

    /**
     * Abonnement payé pour un mois (ou un an) de plus : la période repart de
     * la fin de la période en cours si elle n'est pas finie, sinon d'aujourd'hui.
     */
    private function renew(Subscription $subscription): void
    {
        $end = $subscription->current_period_ends_at;
        $start = ($end !== null && $end->isFuture() && $subscription->status === SubscriptionStatus::Active) ? $end : now();
        $annual = $this->cycle === self::ANNUAL;

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => $annual ? self::ANNUAL : self::MONTHLY,
            'current_period_starts_at' => $start,
            'current_period_ends_at' => $annual ? $start->copy()->addYear() : $start->copy()->addMonthNoOverflow(),
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
