<?php

namespace App\Livewire\Team;

use App\Actions\Auth\ResetPasswordToDefault;
use App\Enums\ActivityAction;
use App\Enums\Permission;
use App\Enums\PressingRole;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\ActivityLog;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\User;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Équipe du pressing et rôles (RB-08).
 *
 * Chaque membre a un nom et un rôle (Propriétaire, Gérant, Employé ou un rôle
 * créé pour le pressing). Le propriétaire coche les accès de chaque rôle ; un
 * membre qui gère l'équipe sans être propriétaire (le gérant) ne gère que les
 * membres dont le rôle n'a pas l'accès Équipe et ne donne jamais plus
 * d'accès qu'il n'en a lui-même.
 *
 * Pas d'envoi d'e-mail/SMS : on choisit le login et le mot de passe du
 * nouveau membre et on les lui transmet directement.
 */
class Index extends Component
{
    use ShowsToasts;

    public bool $showCreateForm = false;

    public string $name = '';

    public string $login = '';

    public string $phone = '';

    public string $password = '';

    public ?int $roleId = null;

    public ?User $createdMember = null;

    public ?string $errorMessage = null;

    // Rôles : création / modification des accès cochés
    public bool $showRoleForm = false;

    public ?int $editingRoleId = null;

    public string $roleName = '';

    /** @var list<string> */
    public array $rolePermissions = [];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user->may(Permission::TeamManage) || $user->may(Permission::RolesManage), 403);

        $this->roleId = $this->pressing()->defaultRole(Role::EMPLOYEE)->id;
    }

    private function pressing(): Pressing
    {
        $pressing = auth()->user()->currentPressing();
        abort_if($pressing === null, 403);

        return $pressing;
    }

    private function isOwner(): bool
    {
        return auth()->user()->isAdminOf($this->pressing());
    }

    /**
     * Rôles que l'utilisateur peut donner : tous pour le propriétaire ; sinon
     * ceux sans accès Équipe, dont les accès sont tous compris dans les siens.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles(): Collection
    {
        $roles = $this->pressing()->roles()->get();

        if ($this->isOwner()) {
            return $roles;
        }

        $mine = auth()->user()->roleIn($this->pressing())?->permissionValues() ?? [];

        return $roles->filter(fn (Role $role) => ! $role->isOwner()
            && ! $role->allows(Permission::TeamManage)
            && array_diff($role->permissionValues(), $mine) === [])->values();
    }

    private function canManageMember(User $member): bool
    {
        if ($member->id === auth()->id()) {
            return false;
        }

        $role = $member->roleIn($this->pressing()) ?? $this->memberRole($member);

        return $role !== null && $this->assignableRoles()->contains('id', $role->id);
    }

    /** Rôle d'un membre, même retiré de l'équipe (roleIn ne voit que les actifs). */
    private function memberRole(User $member): ?Role
    {
        $pivot = $this->pressing()->staff()->whereKey($member->id)->first()?->pivot;
        if ($pivot === null) {
            return null;
        }

        return $pivot->role_id ? Role::find($pivot->role_id)
            : $this->pressing()->defaultRole($pivot->role === PressingRole::Admin ? Role::OWNER : Role::EMPLOYEE);
    }

    // ── Membres ───────────────────────────────────────────────────────────

    public function inviteEmployee(): void
    {
        abort_unless(auth()->user()->may(Permission::TeamManage), 403);
        $this->createdMember = null;
        $this->phone = filled($this->phone) ? PhoneNumber::normalize($this->phone) : '';

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'login' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,login'],
            'phone' => ['nullable', 'string', new IvorianPhone, 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
            'roleId' => ['required', 'integer'],
        ]);

        $pressing = $this->pressing();
        $role = $this->assignableRoles()->firstWhere('id', $this->roleId);
        if ($role === null) {
            $this->addError('roleId', 'Choisissez un rôle que vous pouvez attribuer.');

            return;
        }

        $member = User::create([
            'name' => $this->name,
            'login' => $this->login,
            'phone' => $this->phone ?: null,
            'password' => $this->password,
        ]);

        $pressing->staff()->attach($member, ['role' => $this->legacyRole($role), 'role_id' => $role->id, 'is_active' => true]);

        $this->logTeam("{$member->name} ajouté à l'équipe comme « {$role->name} » (identifiant {$member->login})");

        $this->createdMember = $member;
        $this->reset(['name', 'login', 'phone', 'password', 'showCreateForm']);
        $this->roleId = $pressing->defaultRole(Role::EMPLOYEE)->id;
    }

    /** Change le rôle d'un membre (Gérant, Employé, Caissière…). */
    public function changeRole(int $memberId, int $roleId): void
    {
        abort_unless(auth()->user()->may(Permission::TeamManage), 403);
        $this->errorMessage = null;
        $pressing = $this->pressing();
        $member = $pressing->staff()->findOrFail($memberId);
        $role = $this->assignableRoles()->firstWhere('id', $roleId);

        abort_unless($role !== null && $this->canManageMember($member), 403);

        $current = $this->memberRole($member);
        if ($current?->isOwner() && ! $role->isOwner() && $this->activeOwnersCount() <= 1 && $member->pivot->is_active) {
            $this->errorMessage = 'Le pressing doit garder au moins un propriétaire actif.';

            return;
        }

        $pressing->staff()->updateExistingPivot($member->id, ['role' => $this->legacyRole($role), 'role_id' => $role->id]);
        $this->logTeam("Rôle de {$member->name} : « ".($current?->name ?? '—')." » → « {$role->name} »");
        $this->toast("{$member->name} est maintenant « {$role->name} ».");
    }

    /**
     * Mot de passe oublié par un membre : il repasse à la valeur par défaut
     * et le membre doit ensuite le changer pour sécuriser son compte.
     */
    public function resetMemberPassword(int $memberId): void
    {
        abort_unless(auth()->user()->may(Permission::TeamManage), 403);
        $member = $this->pressing()->staff()->findOrFail($memberId);
        abort_unless($this->canManageMember($member), 403);

        (new ResetPasswordToDefault)->handle($member);
        $this->logTeam("Mot de passe de {$member->name} réinitialisé");

        $this->toast("Mot de passe de {$member->name} réinitialisé à ".ResetPasswordToDefault::DEFAULT_PASSWORD.'. Il doit maintenant le changer pour sécuriser son compte.');
    }

    public function toggleActive(int $memberId): void
    {
        abort_unless(auth()->user()->may(Permission::TeamManage), 403);
        $this->errorMessage = null;
        $this->createdMember = null;

        $pressing = $this->pressing();
        $member = $pressing->staff()->findOrFail($memberId);
        abort_unless($member->id === auth()->id() || $this->canManageMember($member), 403);

        try {
            if ($member->id === auth()->id()) {
                throw new RuntimeException("Vous ne pouvez pas vous retirer vous-même de l'équipe.");
            }

            if ($member->pivot->is_active && $member->pivot->role === PressingRole::Admin && $this->activeOwnersCount() <= 1) {
                throw new RuntimeException('Impossible de retirer le dernier propriétaire actif.');
            }
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $pressing->staff()->updateExistingPivot($member->id, ['is_active' => ! $member->pivot->is_active]);
        $this->logTeam($member->pivot->is_active ? "{$member->name} retiré de l'équipe (accès coupé)" : "{$member->name} réintégré dans l'équipe");

        $this->toast($member->pivot->is_active
            ? "{$member->name} a été retiré de l'équipe : son accès est coupé."
            : "{$member->name} a retrouvé l'accès au pressing.", $member->pivot->is_active ? 'warning' : 'success');
    }

    private function logTeam(string $description): void
    {
        ActivityLog::record($this->pressing()->id, ActivityAction::TeamChanged, $description);
    }

    private function activeOwnersCount(): int
    {
        return $this->pressing()->admins()->wherePivot('is_active', true)->count();
    }

    /** Ancienne colonne `role` : « admin » pour les propriétaires, « employee » sinon. */
    private function legacyRole(Role $role): string
    {
        return $role->isOwner() ? PressingRole::Admin->value : PressingRole::Employee->value;
    }

    // ── Rôles et accès ────────────────────────────────────────────────────

    /** @return list<string> accès que l'utilisateur peut cocher (jamais plus que les siens) */
    private function grantablePermissions(): array
    {
        return auth()->user()->roleIn($this->pressing())?->permissionValues() ?? [];
    }

    public function newRole(): void
    {
        abort_unless(auth()->user()->may(Permission::RolesManage), 403);
        $this->resetErrorBag();
        $this->editingRoleId = null;
        $this->roleName = '';
        $this->rolePermissions = $this->pressing()->defaultRole(Role::EMPLOYEE)->permissionValues();
        $this->showRoleForm = true;
    }

    public function editRole(int $roleId): void
    {
        abort_unless(auth()->user()->may(Permission::RolesManage), 403);
        $role = $this->pressing()->roles()->findOrFail($roleId);
        abort_if($role->isOwner(), 403);

        $this->resetErrorBag();
        $this->editingRoleId = $role->id;
        $this->roleName = $role->name;
        $this->rolePermissions = $role->permissionValues();
        $this->showRoleForm = true;
    }

    public function saveRole(): void
    {
        abort_unless(auth()->user()->may(Permission::RolesManage), 403);
        $pressing = $this->pressing();
        $this->roleName = trim($this->roleName);

        $this->validate([
            'roleName' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->where('pressing_id', $pressing->id)->ignore($this->editingRoleId)],
            'rolePermissions' => ['array'],
            'rolePermissions.*' => [Rule::in(Permission::all())],
        ], [
            'roleName.required' => 'Donnez un nom au rôle (ex. Caissière).',
            'roleName.unique' => 'Ce pressing a déjà un rôle de ce nom.',
        ]);

        $role = $this->editingRoleId !== null ? $pressing->roles()->findOrFail($this->editingRoleId) : null;
        abort_if($role?->isOwner(), 403);

        // On ne donne jamais un accès qu'on n'a pas ; ceux qu'on n'a pas restent tels quels.
        $grantable = $this->grantablePermissions();
        $kept = array_diff($role?->permissionValues() ?? [], $grantable);
        $permissions = array_values(array_intersect(Permission::all(), array_unique([...array_intersect($this->rolePermissions, $grantable), ...$kept])));

        $label = fn (array $values) => implode(', ', array_map(fn (string $value) => Permission::from($value)->label(), $values));

        if ($role === null) {
            $pressing->roles()->create(['name' => $this->roleName, 'permissions' => $permissions]);
            ActivityLog::record($pressing->id, ActivityAction::RolesChanged, "Rôle « {$this->roleName} » créé avec ".count($permissions).' accès',
                null, ['permissions' => $permissions]);
            $this->toast("Rôle « {$this->roleName} » créé.");
        } else {
            $before = $role->permissionValues();
            $oldName = $role->name;
            $role->update(['name' => $this->roleName, 'permissions' => $permissions]);
            $added = array_values(array_diff($permissions, $before));
            $removed = array_values(array_diff($before, $permissions));
            $changes = array_filter([
                $oldName !== $this->roleName ? "renommé en « {$this->roleName} »" : null,
                $added !== [] ? 'accès ajoutés : '.$label($added) : null,
                $removed !== [] ? 'accès retirés : '.$label($removed) : null,
            ]);
            if ($changes !== []) {
                ActivityLog::record($pressing->id, ActivityAction::RolesChanged, "Rôle « {$oldName} » ".implode(' ; ', $changes),
                    null, ['added' => $added, 'removed' => $removed]);
            }
            $this->toast("Accès du rôle « {$this->roleName} » enregistrés.");
        }

        auth()->user()->forgetRoles();
        $this->reset(['showRoleForm', 'editingRoleId', 'roleName', 'rolePermissions']);
    }

    public function deleteRole(int $roleId): void
    {
        abort_unless(auth()->user()->may(Permission::RolesManage), 403);
        $role = $this->pressing()->roles()->findOrFail($roleId);

        if ($role->key !== null) {
            $this->errorMessage = 'Les rôles Propriétaire, Gérant et Employé ne se suppriment pas.';

            return;
        }

        if ($role->memberships()->exists()) {
            $this->errorMessage = "Le rôle « {$role->name} » est attribué à des membres : changez d'abord leur rôle.";

            return;
        }

        $role->delete();
        ActivityLog::record($this->pressing()->id, ActivityAction::RolesChanged, "Rôle « {$role->name} » supprimé");
        $this->toast("Rôle « {$role->name} » supprimé.", 'warning');
    }

    #[Layout('layouts.dashboard', ['active' => 'team', 'title' => 'Équipe'])]
    public function render()
    {
        $pressing = $this->pressing();
        $members = $pressing->staff()->orderByDesc('pressing_users.created_at')->get();
        $roles = $pressing->roles()->withCount('memberships')->get();

        return view('livewire.team.index', [
            'members' => $members,
            'memberRoles' => $members->mapWithKeys(fn (User $member) => [$member->id => $this->memberRole($member)]),
            'manageable' => $members->mapWithKeys(fn (User $member) => [$member->id => $this->canManageMember($member)]),
            'roles' => $roles,
            'assignableRoles' => $this->assignableRoles(),
            'canManageTeam' => auth()->user()->may(Permission::TeamManage),
            'canManageRoles' => auth()->user()->may(Permission::RolesManage),
            'rolesLocked' => auth()->user()->featureLockedFor(Permission::RolesManage) !== null
                && auth()->user()->may(Permission::SubscriptionManage),
            'grantable' => $this->grantablePermissions(),
            'permissionGroups' => Permission::grouped(),
        ]);
    }
}
