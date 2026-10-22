<?php

namespace App\Livewire\Admin\Roles;

use App\Enums\ActivityAction;
use App\Enums\Permission;
use App\Enums\PressingRole;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\ActivityLog;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Rôles et accès — Super Admin : crée un rôle (nom + accès cochés) et le
 * rattache à un pressing précis, ou à tous ; modifie les accès des rôles d'un
 * pressing et le rôle de chacun de ses membres. Le rôle Propriétaire garde
 * toujours tous les accès.
 */
class Index extends Component
{
    use ShowsToasts;

    #[Url(as: 'pressing', except: '')]
    public string $pressingId = '';

    public bool $showForm = false;

    public ?int $editingRoleId = null;

    public string $roleName = '';

    /** @var list<string> */
    public array $rolePermissions = [];

    /** À la création : ajouter ce rôle à tous les pressings. */
    public bool $applyToAll = false;

    public ?string $errorMessage = null;

    /** Changement de rôle refusé : redessine le menu sur le rôle actuel. */
    public int $refusals = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        if ($this->pressingId === '' || Pressing::find((int) $this->pressingId) === null) {
            $this->pressingId = (string) (Pressing::orderBy('name')->value('id') ?? '');
        }
    }

    private function pressing(): Pressing
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        return Pressing::findOrFail((int) $this->pressingId);
    }

    public function updatedPressingId(): void
    {
        $this->cancel();
    }

    public function newRole(): void
    {
        $this->resetErrorBag();
        $this->reset(['editingRoleId', 'roleName', 'rolePermissions', 'applyToAll', 'errorMessage']);
        $this->rolePermissions = $this->pressing()->defaultRole(Role::EMPLOYEE)->permissionValues();
        $this->showForm = true;
    }

    public function editRole(int $roleId): void
    {
        $role = $this->pressing()->roles()->findOrFail($roleId);
        abort_if($role->isOwner(), 403);

        $this->resetErrorBag();
        $this->editingRoleId = $role->id;
        $this->roleName = $role->name;
        $this->rolePermissions = $role->permissionValues();
        $this->applyToAll = false;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->reset(['showForm', 'editingRoleId', 'roleName', 'rolePermissions', 'applyToAll', 'errorMessage']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $pressing = $this->pressing();
        $this->roleName = trim($this->roleName);

        $this->validate([
            'roleName' => ['required', 'string', 'max:60'],
            'rolePermissions' => ['array'],
            'rolePermissions.*' => ['in:'.implode(',', Permission::all())],
        ], ['roleName.required' => 'Donnez un nom au rôle (ex. Caissière).']);

        $permissions = array_values(array_intersect(Permission::all(), $this->rolePermissions));
        $sameName = fn (Pressing $p) => $p->roles()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($this->roleName)])
            ->when($this->editingRoleId, fn ($q) => $q->whereKeyNot($this->editingRoleId))
            ->exists();

        if ($this->editingRoleId !== null) {
            $role = $pressing->roles()->findOrFail($this->editingRoleId);
            abort_if($role->isOwner(), 403);

            if ($sameName($pressing)) {
                $this->addError('roleName', 'Ce pressing a déjà un rôle de ce nom.');

                return;
            }

            $role->update(['name' => $this->roleName, 'permissions' => $permissions]);
            ActivityLog::record($pressing->id, ActivityAction::RolesChanged, "Rôle « {$role->name} » modifié par l'équipe PressLink (".count($permissions).' accès)');
            $this->toast("Rôle « {$role->name} » enregistré.");
        } else {
            $targets = $this->applyToAll ? Pressing::orderBy('name')->get() : collect([$pressing]);
            $created = 0;

            foreach ($targets as $target) {
                if ($sameName($target)) {
                    continue;
                }
                $target->roles()->create(['name' => $this->roleName, 'permissions' => $permissions]);
                ActivityLog::record($target->id, ActivityAction::RolesChanged, "Rôle « {$this->roleName} » ajouté par l'équipe PressLink (".count($permissions).' accès)');
                $created++;
            }

            if ($created === 0) {
                $this->addError('roleName', $this->applyToAll ? 'Tous les pressings ont déjà un rôle de ce nom.' : 'Ce pressing a déjà un rôle de ce nom.');

                return;
            }

            $this->toast($this->applyToAll
                ? "Rôle « {$this->roleName} » ajouté à {$created} pressing(s)."
                : "Rôle « {$this->roleName} » ajouté à {$pressing->name}.");
        }

        $this->cancel();
    }

    public function deleteRole(int $roleId): void
    {
        $role = $this->pressing()->roles()->findOrFail($roleId);
        $this->errorMessage = null;

        if ($role->key !== null) {
            $this->errorMessage = 'Les rôles Propriétaire, Gérant et Employé ne se suppriment pas.';

            return;
        }

        if ($role->memberships()->exists()) {
            $this->errorMessage = "Le rôle « {$role->name} » est attribué à des membres : changez d'abord leur rôle.";

            return;
        }

        $role->delete();
        ActivityLog::record($role->pressing_id, ActivityAction::RolesChanged, "Rôle « {$role->name} » supprimé par l'équipe PressLink");
        $this->toast("Rôle « {$role->name} » supprimé.", 'warning');
    }

    /** Attribue un rôle du pressing à l'un de ses membres. */
    public function assignRole(int $memberId, int $roleId): void
    {
        $pressing = $this->pressing();
        $this->errorMessage = null;
        $member = $pressing->staff()->whereKey($memberId)->firstOrFail();
        $role = $pressing->roles()->findOrFail($roleId);

        $isOwnerNow = $member->pivot->role === PressingRole::Admin;
        $activeOwners = $pressing->admins()->wherePivot('is_active', true)->count();
        if ($isOwnerNow && ! $role->isOwner() && $member->pivot->is_active && $activeOwners <= 1) {
            $this->errorMessage = 'Le pressing doit garder au moins un propriétaire actif.';
            $this->refusals++;

            return;
        }

        $pressing->staff()->updateExistingPivot($member->id, [
            'role' => $role->isOwner() ? PressingRole::Admin->value : PressingRole::Employee->value,
            'role_id' => $role->id,
        ]);
        ActivityLog::record($pressing->id, ActivityAction::TeamChanged, "Rôle de {$member->name} : « {$role->name} » (par l'équipe PressLink)");
        $this->toast("{$member->name} est maintenant « {$role->name} ».");
    }

    #[Layout('layouts.admin', ['active' => 'roles', 'title' => 'Rôles et accès'])]
    public function render()
    {
        $pressing = $this->pressingId !== '' ? Pressing::find((int) $this->pressingId) : null;
        $roles = $pressing?->roles()->withCount('memberships')->get() ?? collect();
        $members = $pressing?->staff()->orderBy('name')->get() ?? collect();

        return view('livewire.admin.roles.index', [
            'pressings' => Pressing::orderBy('name')->get(['id', 'name']),
            'pressing' => $pressing,
            'roles' => $roles,
            'members' => $members,
            'memberRoleIds' => $members->mapWithKeys(fn (User $member) => [$member->id => $member->pivot->role_id
                ?? $pressing->defaultRole($member->pivot->role === PressingRole::Admin ? Role::OWNER : Role::EMPLOYEE)->id]),
            'permissionGroups' => Permission::grouped(),
        ]);
    }
}
