<?php

namespace App\Livewire\Admin\Pressings;

use App\Enums\PressingStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Pressing;
use App\Models\Subscription;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Détail d'un pressing côté Super Admin — Phase 7 : suspendre/réactiver le
 * pressing et gérer basiquement son abonnement (plan, statut, quota,
 * dates), sans automatisation de paiement au MVP.
 */
class Show extends Component
{
    public Pressing $pressing;

    public string $plan = '';

    public string $status = '';

    public string $ordersLimit = '';

    public string $trialEndsAt = '';

    public string $currentPeriodEndsAt = '';

    public bool $saved = false;

    public function mount(Pressing $pressing): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $this->pressing = $pressing;
        $this->fillSubscriptionFields();
    }

    public function togglePressingStatus(): void
    {
        $this->pressing->update([
            'status' => $this->pressing->status === PressingStatus::Active
                ? PressingStatus::Suspended
                : PressingStatus::Active,
        ]);
        $this->pressing->refresh();
    }

    public function saveSubscription(): void
    {
        $this->saved = false;

        $this->validate([
            'plan' => ['required', Rule::enum(SubscriptionPlan::class)],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'ordersLimit' => ['nullable', 'integer', 'min:0'],
            'trialEndsAt' => ['nullable', 'date'],
            'currentPeriodEndsAt' => ['nullable', 'date'],
        ]);

        $data = [
            'plan' => $this->plan,
            'status' => $this->status,
            'orders_limit' => $this->ordersLimit !== '' ? (int) $this->ordersLimit : null,
            'trial_ends_at' => $this->trialEndsAt ?: null,
            'current_period_ends_at' => $this->currentPeriodEndsAt ?: null,
        ];

        $subscription = $this->pressing->subscription;

        if ($subscription === null) {
            $subscription = Subscription::create([...$data, 'pressing_id' => $this->pressing->id, 'current_period_starts_at' => now()]);
        } else {
            $subscription->update($data);
        }

        $this->pressing->refresh();
        $this->fillSubscriptionFields();
        $this->saved = true;
    }

    private function fillSubscriptionFields(): void
    {
        $subscription = $this->pressing->subscription;

        $this->plan = $subscription?->plan->value ?? SubscriptionPlan::Starter->value;
        $this->status = $subscription?->status->value ?? SubscriptionStatus::Trialing->value;
        $this->ordersLimit = $subscription?->orders_limit !== null ? (string) $subscription->orders_limit : '';
        $this->trialEndsAt = $subscription?->trial_ends_at?->format('Y-m-d') ?? '';
        $this->currentPeriodEndsAt = $subscription?->current_period_ends_at?->format('Y-m-d') ?? '';
    }

    #[Layout('layouts.admin', ['active' => 'pressings', 'title' => 'Pressing'])]
    public function render()
    {
        $this->pressing->loadCount(['staff', 'customers', 'orders']);

        return view('livewire.admin.pressings.show', [
            'staff' => $this->pressing->staff()->orderByDesc('pressing_users.created_at')->get(),
        ]);
    }
}
