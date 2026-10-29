<?php

namespace App\Livewire\Admin\Pressings;

use App\Actions\Auth\ResetPasswordToDefault;
use App\Actions\Pressings\ErasePressingData;
use App\Enums\ActivityAction;
use App\Enums\Feature;
use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\HandlesSubscriptionRequests;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\ActivityLog;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    use HandlesSubscriptionRequests;
    use ShowsToasts;

    public Pressing $pressing;

    public string $plan = '';

    public string $status = '';

    public string $ordersLimit = '';

    public string $trialEndsAt = '';

    public string $currentPeriodEndsAt = '';

    public string $billingCycle = 'monthly';

    /** @var list<string> options prises en plus de la formule (App\Enums\Feature) */
    public array $addons = [];

    /** Commandes ajoutées au quota du mois en cours. */
    public string $extraOrders = '';

    /** @var array{name: string, login: string, phone: string, password: string, roleId: string} nouveau membre de l'équipe */
    public array $newMember = ['name' => '', 'login' => '', 'phone' => '', 'password' => '', 'roleId' => ''];

    public bool $addingMember = false;

    public bool $editingInfo = false;

    /** @var array{name: string, phone: string, email: string, city: string, address: string, description: string} */
    public array $info = ['name' => '', 'phone' => '', 'email' => '', 'city' => '', 'address' => '', 'description' => ''];

    /** Code du pressing ressaisi pour confirmer la suppression. */
    public string $deleteConfirmation = '';

    /** @var list<string> données à effacer (clés de ErasePressingData::PARTS) */
    public array $eraseParts = [];

    /** Code du pressing ressaisi pour confirmer l'effacement de données. */
    public string $eraseConfirmation = '';

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

    /**
     * Efface une partie des données du pressing (commandes, clients, caisse,
     * articles, journal) en gardant le pressing, son équipe et son abonnement.
     */
    public function eraseData(ErasePressingData $erase): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $this->validate(['eraseParts' => ['required', 'array', 'min:1'], 'eraseParts.*' => [Rule::in(array_keys(ErasePressingData::PARTS))]], [
            'eraseParts.required' => 'Cochez au moins un type de données à effacer.',
            'eraseParts.min' => 'Cochez au moins un type de données à effacer.',
        ]);

        if (trim($this->eraseConfirmation) !== $this->pressing->code) {
            $this->addError('eraseConfirmation', 'Saisissez exactement le code '.$this->pressing->code.' pour confirmer.');

            return;
        }

        $counts = $erase->handle($this->pressing, $this->eraseParts);
        $this->reset(['eraseParts', 'eraseConfirmation']);

        $summary = collect($counts)->map(fn (int $count, string $part) => "{$count} {$part}")->implode(', ');
        $this->toast("Données effacées : {$summary}.");
    }

    public function resetStaffPassword(int $userId): void
    {
        $member = $this->pressing->staff()->where('users.id', $userId)->firstOrFail();

        abort_unless(auth()->user()->is_super_admin, 403);

        (new ResetPasswordToDefault)->handle($member);

        $this->toast("Mot de passe de {$member->name} réinitialisé à ".ResetPasswordToDefault::DEFAULT_PASSWORD.'. Il doit maintenant le changer pour sécuriser son compte.');
    }

    /** Nouvelle formule choisie : on propose son nombre de commandes par mois. */
    public function updatedPlan(string $value): void
    {
        $plan = SubscriptionPlan::tryFrom($value);
        if ($plan !== null) {
            $limit = $plan->ordersLimit();
            $this->ordersLimit = $limit !== null ? (string) $limit : '';
        }
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
            'addons' => ['array'],
            'addons.*' => [Rule::enum(Feature::class)],
            'extraOrders' => ['nullable', 'integer', 'min:0'],
        ]);

        $subscription = $this->pressing->subscription;

        $data = [
            'plan' => $this->plan,
            'status' => $this->status,
            'orders_limit' => $this->ordersLimit !== '' ? (int) $this->ordersLimit : null,
            'trial_ends_at' => $this->trialEndsAt ?: null,
            'current_period_ends_at' => $this->currentPeriodEndsAt ?: null,
            'billing_cycle' => $this->billingCycle,
            'addons' => array_values(array_unique($this->addons)),
            'extra_orders' => (int) ($this->extraOrders ?: 0),
            'extra_orders_month' => now()->format('Y-m'),
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
        $this->addons = array_values(array_filter($subscription?->addons ?? [], fn ($value) => Feature::tryFrom($value) !== null));
        $extra = $subscription?->extraOrdersThisMonth() ?? 0;
        $this->extraOrders = $extra > 0 ? (string) $extra : '';
    }

    protected function handleableRequests(): HasMany
    {
        return $this->pressing->subscriptionRequests();
    }

    protected function requestApplied(SubscriptionRequest $request): void
    {
        $this->pressing->refresh();
        $this->fillSubscriptionFields();
    }

    // ── Prolongation en un clic ───────────────────────────────────────────

    /**
     * Geste commercial sans paiement : 7 jours d'essai de plus, ou un mois
     * de période payée offert. Part de la date de fin si elle est encore à
     * venir, sinon d'aujourd'hui ; l'abonnement redevient utilisable.
     */
    public function extend(string $what): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        abort_unless(in_array($what, ['trial', 'month'], true), 404);

        $subscription = $this->pressing->subscription;
        if ($subscription === null) {
            $this->toast("Ce pressing n'a pas encore d'abonnement : enregistrez-en un ci-dessous.", 'error');

            return;
        }

        if ($what === 'trial') {
            $from = $subscription->trial_ends_at?->isFuture() ? $subscription->trial_ends_at->copy() : now();
            $subscription->update(['status' => SubscriptionStatus::Trialing, 'trial_ends_at' => $from->addDays(7)]);
            $message = "Essai prolongé jusqu'au ".$subscription->trial_ends_at->format('d/m/Y').'.';
        } else {
            $end = $subscription->current_period_ends_at;
            $ongoing = $subscription->status === SubscriptionStatus::Active && $end?->isFuture();
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'current_period_starts_at' => $ongoing ? ($subscription->current_period_starts_at ?? now()) : now(),
                'current_period_ends_at' => ($ongoing ? $end->copy() : now())->addMonthNoOverflow(),
            ]);
            $message = 'Un mois offert : période payée jusqu\'au '.$subscription->current_period_ends_at->format('d/m/Y').'.';
        }

        ActivityLog::record($this->pressing->id, ActivityAction::SettingsChanged, $message.' (par l\'équipe PressLink)');
        $this->pressing->refresh();
        $this->fillSubscriptionFields();
        $this->toast($message);
    }

    // ── Équipe ────────────────────────────────────────────────────────────

    public function addMember(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $this->newMember['phone'] = filled($this->newMember['phone']) ? PhoneNumber::normalize($this->newMember['phone']) : '';

        $this->validate([
            'newMember.name' => ['required', 'string', 'max:100'],
            'newMember.login' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,login'],
            'newMember.phone' => ['nullable', 'string', new IvorianPhone, 'unique:users,phone'],
            'newMember.password' => ['required', 'string', 'min:8'],
            'newMember.roleId' => ['required', Rule::exists('roles', 'id')->where('pressing_id', $this->pressing->id)],
        ], [
            'newMember.login.regex' => 'Lettres, chiffres, point, tiret ou tiret bas, sans espace.',
        ], [
            'newMember.name' => 'nom', 'newMember.login' => 'identifiant', 'newMember.phone' => 'téléphone',
            'newMember.password' => 'mot de passe', 'newMember.roleId' => 'rôle',
        ]);

        $role = $this->pressing->roles()->findOrFail((int) $this->newMember['roleId']);
        $member = User::create([
            'name' => $this->newMember['name'],
            'login' => $this->newMember['login'],
            'phone' => $this->newMember['phone'] ?: null,
            'password' => $this->newMember['password'],
        ]);
        $this->pressing->staff()->attach($member, [
            'role' => $role->isOwner() ? PressingRole::Admin->value : PressingRole::Employee->value,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        ActivityLog::record($this->pressing->id, ActivityAction::TeamChanged, "{$member->name} ajouté à l'équipe comme « {$role->name} » (identifiant {$member->login}, par l'équipe PressLink)");
        $this->reset('newMember', 'addingMember');
        $this->toast("{$member->name} ajouté à l'équipe. Transmettez-lui son identifiant et son mot de passe.");
    }

    public function changeMemberRole(int $userId, int $roleId): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $member = $this->pressing->staff()->whereKey($userId)->firstOrFail();
        $role = $this->pressing->roles()->findOrFail($roleId);

        if ($member->pivot->role === PressingRole::Admin && ! $role->isOwner() && $member->pivot->is_active && $this->activeOwnersCount() <= 1) {
            $this->toast('Le pressing doit garder au moins un propriétaire actif.', 'error');

            return;
        }

        $this->pressing->staff()->updateExistingPivot($member->id, [
            'role' => $role->isOwner() ? PressingRole::Admin->value : PressingRole::Employee->value,
            'role_id' => $role->id,
        ]);
        ActivityLog::record($this->pressing->id, ActivityAction::TeamChanged, "Rôle de {$member->name} : « {$role->name} » (par l'équipe PressLink)");
        $this->toast("{$member->name} est maintenant « {$role->name} ».");
    }

    /** Retire un membre (accès coupé, compte gardé) ou le réintègre. */
    public function toggleMember(int $userId): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $member = $this->pressing->staff()->whereKey($userId)->firstOrFail();

        if ($member->pivot->is_active && $member->pivot->role === PressingRole::Admin && $this->activeOwnersCount() <= 1) {
            $this->toast('Impossible de retirer le dernier propriétaire actif.', 'error');

            return;
        }

        $this->pressing->staff()->updateExistingPivot($member->id, ['is_active' => ! $member->pivot->is_active]);
        ActivityLog::record($this->pressing->id, ActivityAction::TeamChanged, ($member->pivot->is_active ? "{$member->name} retiré de l'équipe" : "{$member->name} réintégré dans l'équipe").' (par l\'équipe PressLink)');
        $this->toast($member->pivot->is_active ? "{$member->name} est retiré : son accès est coupé." : "{$member->name} a retrouvé l'accès au pressing.", $member->pivot->is_active ? 'warning' : 'success');
    }

    private function activeOwnersCount(): int
    {
        return $this->pressing->admins()->wherePivot('is_active', true)->count();
    }

    #[Layout('layouts.admin', ['active' => 'pressings', 'title' => 'Pressing'])]
    public function render()
    {
        $this->pressing->loadCount(['staff', 'customers', 'orders', 'services']);

        $pendingRequests = $this->pressing->subscriptionRequests()->pending()->with(['requester', 'pressing'])->latest()->get();
        $this->fillRequestFields($pendingRequests);

        $staff = $this->pressing->staff()->orderByDesc('pressing_users.created_at')->get();
        $roles = $this->pressing->roles()->get();
        if ($this->newMember['roleId'] === '') {
            $this->newMember['roleId'] = (string) $this->pressing->defaultRole(Role::EMPLOYEE)->id;
        }

        return view('livewire.admin.pressings.show', [
            'staff' => $staff,
            'roles' => $roles,
            'memberRoleIds' => $staff->mapWithKeys(fn (User $member) => [$member->id => $member->pivot->role_id
                ?? $this->pressing->defaultRole($member->pivot->role === PressingRole::Admin ? Role::OWNER : Role::EMPLOYEE)->id]),
            'pendingRequests' => $pendingRequests,
            // Demandes traitées : paiements reçus et refus, du plus récent au plus ancien.
            'handledRequests' => $this->pressing->subscriptionRequests()
                ->where('status', '!=', SubscriptionRequest::PENDING)
                ->with('handler')
                ->latest('handled_at')
                ->take(30)
                ->get(),
        ]);
    }
}
