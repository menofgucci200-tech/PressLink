<?php

namespace App\Livewire\Team;

use App\Enums\PressingRole;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Gestion de l'équipe du pressing — Phase 2 du dashboard (RB-08).
 *
 * Pas d'infrastructure d'envoi d'email/SMS au MVP : l'admin crée le
 * compte employé directement et le mot de passe généré est affiché une
 * seule fois pour qu'il le transmette lui-même.
 */
class Index extends Component
{
    public bool $showCreateForm = false;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = 'employee';

    public ?string $generatedPassword = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->isAdminOf($pressing), 403);
    }

    public function inviteEmployee(): void
    {
        $this->generatedPassword = null;

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/', 'unique:users,phone'],
            'role' => ['required', Rule::enum(PressingRole::class)],
        ]);

        $pressing = auth()->user()->currentPressing();
        $password = Str::password(10);

        $employee = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => $password,
        ]);

        $pressing->staff()->attach($employee, ['role' => $this->role, 'is_active' => true]);

        $this->generatedPassword = $password;
        $this->reset(['name', 'email', 'phone', 'role', 'showCreateForm']);
    }

    public function toggleActive(int $memberId): void
    {
        $this->errorMessage = null;
        $this->generatedPassword = null;

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
