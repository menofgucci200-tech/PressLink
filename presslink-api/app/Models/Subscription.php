<?php

namespace App\Models;

use App\Casts\SubscriptionPlanCast;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RB-09 : un pressing dont l'abonnement est expiré ne peut plus créer
 * de commande.
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
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
    ];

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

        if ($this->orders_limit !== null && $this->orders_used >= $this->orders_limit) {
            return false;
        }

        return true;
    }

    public function incrementOrdersUsed(): void
    {
        $this->increment('orders_used');
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
