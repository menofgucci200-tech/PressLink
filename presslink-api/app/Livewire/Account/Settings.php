<?php

namespace App\Livewire\Account;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Mon compte" — tout membre du staff (admin ou employé) peut y modifier
 * son propre login et son mot de passe, sans passer par le Super Admin.
 */
class Settings extends Component
{
    public string $login = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public ?string $successMessage = null;

    public function mount(): void
    {
        $this->login = (string) auth()->user()->login;
    }

    public function updateLogin(): void
    {
        $this->successMessage = null;

        $this->validate([
            'login' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'login')->ignore(auth()->id())],
        ]);

        auth()->user()->update(['login' => $this->login]);

        $this->successMessage = 'Identifiant de connexion mis à jour.';
    }

    public function updatePassword(): void
    {
        $this->successMessage = null;

        $this->validate([
            'currentPassword' => ['required', 'string', 'current_password'],
            'newPassword' => ['required', 'string', 'min:8'],
        ]);

        // La règle `confirmed` de Laravel attend une clé `{champ}_confirmation`
        // dans les données validées ; les propriétés Livewire étant en
        // camelCase ici, on vérifie la correspondance manuellement.
        if ($this->newPassword !== $this->newPasswordConfirmation) {
            $this->addError('newPasswordConfirmation', 'La confirmation ne correspond pas au nouveau mot de passe.');

            return;
        }

        auth()->user()->update(['password' => $this->newPassword]);

        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);
        $this->successMessage = 'Mot de passe mis à jour.';
    }

    #[Layout('layouts.dashboard', ['active' => 'account', 'title' => 'Mon compte'])]
    public function render()
    {
        return view('livewire.account.settings');
    }
}
