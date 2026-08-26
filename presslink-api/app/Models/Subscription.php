<?php

namespace App\Models;

use App\Enums\SubscriptionPlan;
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
        'orders_limit',
        'orders_used',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'status' => SubscriptionStatus::class,
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
}
