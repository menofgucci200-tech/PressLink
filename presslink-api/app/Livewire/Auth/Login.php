<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Connexion staff (employé/admin) — téléphone ou email + mot de passe.
 * Cahier §3.2 / User Flows §9.
 */
class Login extends Component
{
    public string $login = '';

    public string $password = '';

    public bool $remember = false;

    public function authenticate(): void
    {
        $this->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = str_contains($this->login, '@') ? 'email' : 'phone';

        if (! Auth::attempt([$field => $this->login, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'login' => 'Identifiants incorrects.',
            ]);
        }

        session()->regenerate();

        $this->redirect(Auth::user()->is_super_admin ? '/admin' : '/', navigate: false);
    }

    #[Layout('layouts.guest')]
    public function render()
    {
        return view('livewire.auth.login');
    }
}
