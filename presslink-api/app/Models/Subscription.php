<?php

namespace App\Models;

use App\Casts\SubscriptionPlanCast;
use App\Enums\Feature;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * RB-09 : un pressing dont l'abonnement est expiré ne peut plus créer
 * de commande. Le quota de commandes est mensuel : `orders_used` compte les
 * commandes du mois `orders_month` et repart de zéro le mois suivant.
 *
 * Options prises en plus de la formule : `addons` (fonctions à la carte,
 * App\Enums\Feature) et `extra_orders` (commandes ajoutées pour le mois
 * `extra_orders_month`).
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'pressing_id',
        'plan',
        'status',
        'billing_cycle',
        'setup_fee_fcfa',
        'setup_fee_paid_at',
        'orders_limit',
        'orders_used',
        'orders_month',
        'addons',
        'extra_orders',
        'extra_orders_month',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription) {
            $subscription->orders_month ??= now()->format('Y-m');
        });
    }

    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlanCast::class,
            'status' => SubscriptionStatus::class,
            'addons' => 'array',
            'setup_fee_paid_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /**
     * La formule inclut cette fonction, ou le pressing l'a prise en option.
     * Pendant l'essai gratuit, tout est ouvert.
     */
    public function hasFeature(Feature $feature): bool
    {
        return $this->isInTrial() || $this->plan->includes($feature) || $this->hasAddon($feature);
    }

    /** Essai gratuit en cours (pas encore terminé). */
    public function isInTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture());
    }

    /** Durée de l'essai gratuit (jours), réglée depuis le Super Admin. */
    public static function trialDays(): int
    {
        return max(0, (int) Setting::read('trial_days', 14));
    }

    public function hasAddon(Feature $feature): bool
    {
        return in_array($feature->value, $this->addons ?? [], true);
    }

    /** @param  list<Feature>  $features */
    public function syncAddons(array $features): void
    {
        $this->update(['addons' => array_values(array_unique(array_map(fn (Feature $f) => $f->value, $features)))]);
    }

    public function addAddon(Feature $feature): void
    {
        $this->update(['addons' => array_values(array_unique([...($this->addons ?? []), $feature->value]))]);
    }

    /** Commandes ajoutées pour le mois en cours. */
    public function extraOrdersThisMonth(): int
    {
        return $this->extra_orders_month === now()->format('Y-m') ? (int) $this->extra_orders : 0;
    }

    /** Ajoute des commandes au quota du mois en cours. */
    public function addExtraOrders(int $count): void
    {
        $this->update([
            'extra_orders' => $this->extraOrdersThisMonth() + $count,
            'extra_orders_month' => now()->format('Y-m'),
        ]);
    }

    /** Quota du mois : celui de la formule plus les commandes ajoutées (null = illimité). */
    public function ordersLimitThisMonth(): ?int
    {
        return $this->orders_limit === null ? null : $this->orders_limit + $this->extraOrdersThisMonth();
    }

    public function hasReachedOrdersLimit(): bool
    {
        $limit = $this->ordersLimitThisMonth();

        return $limit !== null && $this->ordersUsedThisMonth() >= $limit;
    }

    /** RB-09. */
    public function allowsNewOrder(): bool
    {
        if (! $this->status->allowsOrderCreation()) {
            return false;
        }

        if ($this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast()) {
            return false;
        }

        if ($this->hasReachedOrdersLimit()) {
            return false;
        }

        return true;
    }

    /** Commandes créées ce mois-ci (0 si le compteur date d'un mois précédent). */
    public function ordersUsedThisMonth(): int
    {
        return $this->orders_month === now()->format('Y-m') ? (int) $this->orders_used : 0;
    }

    /**
     * +1 commande, en une seule requête (pas de perte sous concurrence). Au
     * premier passage d'un nouveau mois, le compteur repart à 1.
     */
    public function incrementOrdersUsed(): void
    {
        $month = now()->format('Y-m');

        // orders_used avant orders_month : MySQL évalue les affectations dans
        // l'ordre, la comparaison porte donc sur l'ancien mois.
        static::query()->whereKey($this->getKey())->update([
            'orders_used' => DB::raw("CASE WHEN orders_month = '{$month}' THEN orders_used + 1 ELSE 1 END"),
            'orders_month' => $month,
            'updated_at' => now(),
        ]);

        $fresh = static::query()->whereKey($this->getKey())->first(['orders_used', 'orders_month', 'updated_at']);
        $this->forceFill($fresh->only(['orders_used', 'orders_month', 'updated_at']))->syncOriginal();
    }

    /** Le ticket d'entrée de la formule a-t-il été réglé ? */
    public function isSetupFeePaid(): bool
    {
        return $this->setup_fee_paid_at !== null;
    }
}
