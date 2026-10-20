<?php

namespace App\Livewire\Admin\Pressings;

use App\Actions\Auth\ResetPasswordToDefault;
use App\Enums\PressingStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
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
    use ShowsToasts;

    public Pressing $pressing;

    public string $plan = '';

    public string $status = '';

    public string $ordersLimit = '';

    public string $trialEndsAt = '';

    public string $currentPeriodEndsAt = '';

    public string $billingCycle = 'monthly';

    public bool $setupFeePaid = false;

    public bool $editingInfo = false;

    /** @var array{name: string, phone: string, email: string, city: string, address: string, description: string} */
    public array $info = ['name' => '', 'phone' => '', 'email' => '', 'city' => '', 'address' => '', 'description' => ''];

    /** Code du pressing ressaisi pour confirmer la suppression. */
    public string $deleteConfirmation = '';

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
        $this->toast($this->pressing->status === PressingStatus::Active
            ? "{$this->pressing->name} est réactivé."
            : "{$this->pressing->name} est suspendu.", $this->pressing->status === PressingStatus::Active ? 'success' : 'warning');
    }

    public function editInfo(): void
    {
        $this->info = [
            'name' => $this->pressing->name,
            'phone' => (string) $this->pressing->phone,
            'email' => (string) $this->pressing->email,
            'city' => (string) $this->pressing->city,
            'address' => (string) $this->pressing->address,
            'description' => (string) $this->pressing->description,
        ];
        $this->resetErrorBag();
        $this->editingInfo = true;
    }

    public function cancelEditInfo(): void
    {
        $this->editingInfo = false;
        $this->resetErrorBag();
    }

    public function saveInfo(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $this->info['phone'] = PhoneNumber::normalize($this->info['phone'] ?? '');

        $this->info['phone'] = PhoneNumber::normalize($this->info['phone']);
        $this->validate([
            'info.name' => ['required', 'string', 'max:150'],
            'info.phone' => ['required', 'string', new IvorianPhone(mobileOnly: false)],
            'info.email' => ['nullable', 'email', 'max:255'],
            'info.city' => ['nullable', 'string', 'max:100'],
            'info.address' => ['nullable', 'string', 'max:255'],
            'info.description' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'info.name' => 'nom', 'info.phone' => 'téléphone', 'info.email' => 'email',
            'info.city' => 'ville', 'info.address' => 'adresse', 'info.description' => 'description',
        ]);

        $this->pressing->update(array_map(fn (string $value) => trim($value) === '' ? null : trim($value), $this->info));

        $this->editingInfo = false;
        $this->toast('Informations du pressing enregistrées.');
    }

    /**
     * Suppression définitive : commandes, services, abonnement et
     * rattachements partent en cascade. Les comptes de l'équipe sont
     * conservés (ils peuvent appartenir à d'autres pressings).
     */
    public function deletePressing(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        if (trim($this->deleteConfirmation) !== $this->pressing->code) {
            $this->addError('deleteConfirmation', 'Saisissez exactement le code '.$this->pressing->code.' pour confirmer.');

            return;
        }

        $name = $this->pressing->name;
        $this->pressing->delete();

        $this->flashToast("Le pressing « {$name} » a été supprimé.");
        $this->redirectRoute('admin.pressings.index', navigate: true);
    }

    public function resetStaffPassword(int $userId): void
    {
        $member = $this->pressing->staff()->where('users.id', $userId)->firstOrFail();

        abort_unless(auth()->user()->is_super_admin, 403);

        (new ResetPasswordToDefault)->handle($member);

        $this->toast("Mot de passe de {$member->name} réinitialisé à ".ResetPasswordToDefault::DEFAULT_PASSWORD.'. Il doit maintenant le changer pour sécuriser son compte.');
    }

    public function saveSubscription(): void
    {
        $this->validate([
            'plan' => ['required', Rule::enum(SubscriptionPlan::class)],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'ordersLimit' => ['nullable', 'integer', 'min:0'],
            'trialEndsAt' => ['nullable', 'date'],
            'currentPeriodEndsAt' => ['nullable', 'date'],
            'billingCycle' => ['required', Rule::in(['monthly', 'annual'])],
            'setupFeePaid' => ['boolean'],
        ]);

        $subscription = $this->pressing->subscription;
        $plan = SubscriptionPlan::from($this->plan);

        $data = [
            'plan' => $this->plan,
            'status' => $this->status,
            'orders_limit' => $this->ordersLimit !== '' ? (int) $this->ordersLimit : null,
            'trial_ends_at' => $this->trialEndsAt ?: null,
            'current_period_ends_at' => $this->currentPeriodEndsAt ?: null,
            'billing_cycle' => $this->billingCycle,
            // On garde la date de règlement d'origine tant que la case reste cochée.
            'setup_fee_paid_at' => $this->setupFeePaid ? ($subscription?->setup_fee_paid_at ?? now()) : null,
            'setup_fee_fcfa' => $subscription?->setup_fee_fcfa ?? $plan->setupFeeFcfa(),
        ];

        if ($subscription === null) {
            $subscription = Subscription::create([...$data, 'pressing_id' => $this->pressing->id, 'current_period_starts_at' => now()]);
        } else {
            $subscription->update($data);
        }

        $this->pressing->refresh();
        $this->fillSubscriptionFields();
        $this->toast('Abonnement mis à jour.');
    }

    private function fillSubscriptionFields(): void
    {
        $subscription = $this->pressing->subscription;

        $this->plan = $subscription?->plan->value ?? SubscriptionPlan::Starter->value;
        $this->status = $subscription?->status->value ?? SubscriptionStatus::Trialing->value;
        $this->ordersLimit = $subscription?->orders_limit !== null ? (string) $subscription->orders_limit : '';
        $this->trialEndsAt = $subscription?->trial_ends_at?->format('Y-m-d') ?? '';
        $this->currentPeriodEndsAt = $subscription?->current_period_ends_at?->format('Y-m-d') ?? '';
        $this->billingCycle = $subscription?->billing_cycle === 'annual' ? 'annual' : 'monthly';
        $this->setupFeePaid = $subscription?->isSetupFeePaid() ?? false;
    }

    #[Layout('layouts.admin', ['active' => 'pressings', 'title' => 'Pressing'])]
    public function render()
    {
        $this->pressing->loadCount(['staff', 'customers', 'orders', 'services']);

        return view('livewire.admin.pressings.show', [
            'staff' => $this->pressing->staff()->orderByDesc('pressing_users.created_at')->get(),
        ]);
    }
}
