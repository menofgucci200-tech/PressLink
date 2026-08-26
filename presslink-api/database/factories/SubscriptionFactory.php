<?php

namespace Database\Factories;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Pressing;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $plan = SubscriptionPlan::Pro;

        return [
            'pressing_id' => Pressing::factory(),
            'plan' => $plan,
            'status' => SubscriptionStatus::Trialing,
            'billing_cycle' => 'monthly',
            'orders_limit' => $plan->ordersLimit(),
            'orders_used' => 0,
            'trial_ends_at' => now()->addDays(14),
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addDays(14),
        ];
    }

    public function active(SubscriptionPlan $plan = SubscriptionPlan::Pro): static
    {
        return $this->state(fn () => [
            'plan' => $plan,
            'status' => SubscriptionStatus::Active,
            'orders_limit' => $plan->ordersLimit(),
            'trial_ends_at' => null,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);
    }
}
