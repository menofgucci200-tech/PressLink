<?php

namespace App\Livewire\Account;

use App\Livewire\Concerns\ShowsToasts;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * "Mon compte" — tout membre du staff (admin ou employé) peut y modifier
 * son propre login et son mot de passe, sans passer par le Super Admin.
 */
class Settings extends Component
{
    use ShowsToasts;

    public string $login = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        $this->login = (string) auth()->user()->login;
    }

    public function updateLogin(): void
    {
        $this->validate([
            'login' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'login')->ignore(auth()->id())],
        ]);

        auth()->user()->update(['login' => $this->login]);

        $this->toast('Identifiant de connexion mis à jour.');
    }

    public function updatePassword(): void
    {
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

        auth()->user()->update(['password' => $this->newPassword, 'must_change_password' => false]);

        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);
        $this->toast('Mot de passe mis à jour.');
    }

    public function render()
    {
        // Même page pour l'équipe d'un pressing et pour la console Super Admin.
        return view('livewire.account.settings')->layout(
            auth()->user()->is_super_admin ? 'layouts.admin' : 'layouts.dashboard',
            ['active' => 'account', 'title' => 'Mon compte'],
        );
    }
}
