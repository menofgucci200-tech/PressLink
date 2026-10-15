<?php

namespace App\Livewire\Group;

use App\Enums\ActivityAction;
use App\Enums\PressingRole;
use App\Livewire\Concerns\ShowsToasts;
use App\Livewire\Group\Concerns\InteractsWithGroup;
use App\Models\ActivityLog;
use App\Models\Pressing;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Équipe du groupe : un tableau membres × pressings pour affecter un
 * employé à plusieurs sites, changer son rôle ou le retirer, sans passer
 * pressing par pressing. Les nouveaux membres se créent toujours depuis
 * l'Équipe d'un pressing (identifiant et mot de passe).
 */
class Team extends Component
{
    use InteractsWithGroup, ShowsToasts;

    /** Valeur « pas dans ce pressing ». */
    public const NONE = 'aucun';

    public function assign(int $userId, int $pressingId, string $role): void
    {
        abort_unless(in_array($pressingId, $this->groupPressingIds(), true), 403);

        if ($userId === auth()->id()) {
            $this->toast('Vous ne pouvez pas modifier votre propre accès depuis cet écran.', 'warning');

            return;
        }

        $pressing = Pressing::findOrFail($pressingId);
        $member = User::where('is_super_admin', false)->findOrFail($userId);

        // Seuls les membres déjà présents dans l'un des pressings du groupe.
        abort_unless($member->pressings()->whereIn('pressings.id', $this->groupPressingIds())->exists(), 403);

        $current = $pressing->staff()->where('users.id', $member->id)->first()?->pivot;
        $target = PressingRole::tryFrom($role);

        if ($target === null) {
            if ($current && $current->is_active) {
                $pressing->staff()->updateExistingPivot($member->id, ['is_active' => false]);
                ActivityLog::record($pressing->id, ActivityAction::TeamChanged, "{$member->name} retiré de l'équipe");
                $this->toast("{$member->name} n'a plus accès à {$pressing->name}.");
            }

            return;
        }

        $alreadyActiveWithRole = $current && $current->is_active && $current->role === $target;
        if ($alreadyActiveWithRole) {
            return;
        }

        $subscription = $pressing->subscription;
        $needsSeat = ! $current || ! $current->is_active || $current->role !== $target;
        if ($needsSeat && $subscription !== null) {
            $allowed = $target === PressingRole::Admin ? $subscription->allowsNewAdmin() : $subscription->allowsNewEmployee();
            if (! $allowed) {
                $this->toast("Limite de la formule de {$pressing->name} atteinte pour ce rôle.", 'error');

                return;
            }
        }

        if ($current) {
            $pressing->staff()->updateExistingPivot($member->id, ['role' => $target->value, 'is_active' => true]);
        } else {
            $pressing->staff()->attach($member->id, ['role' => $target->value, 'is_active' => true]);
        }

        ActivityLog::record($pressing->id, ActivityAction::TeamChanged, "{$member->name} : {$target->label()}");
        $this->toast("{$member->name} est maintenant {$target->label()} à {$pressing->name}.");
    }

    #[Layout('layouts.dashboard', ['active' => 'group-team', 'title' => 'Équipe du groupe'])]
    public function render()
    {
        $pressings = $this->groupPressings();
        $ids = $pressings->pluck('id');

        $members = User::query()
            ->where('is_super_admin', false)
            ->whereHas('pressings', fn ($q) => $q->whereIn('pressings.id', $ids))
            ->with(['pressings' => fn ($q) => $q->whereIn('pressings.id', $ids)])
            ->orderBy('name')
            ->get();

        return view('livewire.group.team', [
            'pressings' => $pressings,
            'members' => $members,
            'roles' => PressingRole::cases(),
        ]);
    }
}
