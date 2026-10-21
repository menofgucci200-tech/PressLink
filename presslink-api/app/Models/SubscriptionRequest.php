<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\SubscriptionPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande faite par un pressing depuis sa page Abonnement : une option (une
 * fonction à la carte), des commandes en plus pour le mois, ou une formule
 * supérieure. L'équipe PressLink encaisse hors application puis l'active
 * depuis la fiche du pressing (Super Admin).
 */
class SubscriptionRequest extends Model
{
    public const KIND_OPTION = 'option';

    public const KIND_ORDERS = 'orders';

    public const KIND_PLAN = 'plan';

    public const PENDING = 'pending';

    public const DONE = 'done';

    public const DECLINED = 'declined';

    /** Commandes en plus proposées par défaut pour le mois en cours. */
    public const DEFAULT_ORDER_PACKS = [250, 500, 1000];

    protected $fillable = ['pressing_id', 'requested_by', 'kind', 'feature', 'plan', 'extra_orders', 'status', 'handled_at'];

    protected function casts(): array
    {
        return [
            'feature' => Feature::class,
            'plan' => SubscriptionPlan::class,
            'extra_orders' => 'integer',
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
            return array_map(fn (int $count) => ['count' => $count, 'price' => null], self::DEFAULT_ORDER_PACKS);
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
            default => 'Passer à la formule '.$this->plan?->label(),
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
            };
        }

        $this->update(['status' => self::DONE, 'handled_at' => now()]);
    }

    public function decline(): void
    {
        $this->update(['status' => self::DECLINED, 'handled_at' => now()]);
    }
}
