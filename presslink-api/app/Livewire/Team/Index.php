<?php

namespace App\Livewire\Team;

use App\Enums\PressingRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Gestion de l'équipe du pressing — Phase 2 du dashboard (RB-08).
 *
 * Pas d'infrastructure d'envoi d'email/SMS au MVP : l'admin choisit
 * lui-même le login et le mot de passe du nouveau membre (employé ou
 * co-administrateur) et les lui transmet directement.
 */
class Index extends Component
{
    public bool $showCreateForm = false;

    public string $name = '';

    public string $login = '';

    public string $phone = '';

    public string $password = '';

    public string $role = 'employee';

    public ?User $createdMember = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->isAdminOf($pressing), 403);
    }

    public function inviteEmployee(): void
    {
        $this->createdMember = null;

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'login' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,login'],
            'phone' => ['nullable', 'string', 'regex:/^\+2250[0-9]{9}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(PressingRole::class)],
        ]);

        $pressing = auth()->user()->currentPressing();

        $member = User::create([
            'name' => $this->name,
            'login' => $this->login,
            'phone' => $this->phone ?: null,
            'password' => $this->password,
        ]);

        $pressing->staff()->attach($member, ['role' => $this->role, 'is_active' => true]);

        $this->createdMember = $member;
        $this->reset(['name', 'login', 'phone', 'password', 'role', 'showCreateForm']);
    }

    public function toggleActive(int $memberId): void
    {
        $this->errorMessage = null;
        $this->createdMember = null;

        $pressing = auth()->user()->currentPressing();
        $member = $pressing->staff()->findOrFail($memberId);

        try {
            if ($member->id === auth()->id()) {
                throw new RuntimeException("Vous ne pouvez pas vous retirer vous-même de l'équipe.");
            }

            if ($member->pivot->is_active && $member->pivot->role === PressingRole::Admin) {
                $activeAdmins = $pressing->admins()->wherePivot('is_active', true)->count();

                if ($activeAdmins <= 1) {
                    throw new RuntimeException('Impossible de retirer le dernier administrateur actif.');
                }
            }
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $pressing->staff()->updateExistingPivot($member->id, ['is_active' => ! $member->pivot->is_active]);
    }

    #[Layout('layouts.dashboard', ['active' => 'team', 'title' => 'Équipe'])]
    public function render()
    {
        $pressing = auth()->user()->currentPressing();

        return view('livewire.team.index', [
            'members' => $pressing->staff()->orderByDesc('pressing_users.created_at')->get(),
        ]);
    }
}
