<?php

namespace App\Models;

use App\Casts\SubscriptionPlanCast;
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

        if ($this->orders_limit !== null && $this->ordersUsedThisMonth() >= $this->orders_limit) {
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

    /** Limite d'employés (hors administrateurs) de la formule. */
    public function allowsNewEmployee(): bool
    {
        $limit = $this->plan->employeesLimit();

        if ($limit === null) {
            return true;
        }

        return $this->pressing->employees()->wherePivot('is_active', true)->count() < $limit;
    }

    /** Limite de comptes administrateur de la formule. */
    public function allowsNewAdmin(): bool
    {
        $limit = $this->plan->adminsLimit();

        if ($limit === null) {
            return true;
        }

        return $this->pressing->admins()->wherePivot('is_active', true)->count() < $limit;
    }

    /** Limite de clients rattachés au pressing de la formule. */
    public function allowsNewClient(): bool
    {
        $limit = $this->plan->clientsLimit();

        if ($limit === null) {
            return true;
        }

        return $this->pressing->customers()->count() < $limit;
    }

    /** Limite de services/tarifs de la formule. */
    public function allowsNewService(): bool
    {
        $limit = $this->plan->servicesLimit();

        if ($limit === null) {
            return true;
        }

        return $this->pressing->services()->count() < $limit;
    }

    /** Le ticket d'entrée de la formule a-t-il été réglé ? */
    public function isSetupFeePaid(): bool
    {
        return $this->setup_fee_paid_at !== null;
    }
}
