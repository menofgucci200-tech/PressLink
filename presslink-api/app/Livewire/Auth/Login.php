<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Connexion staff (employé/admin) — login, téléphone ou email + mot de
 * passe. Cahier §3.2 / User Flows §9.
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

        $field = match (true) {
            str_contains($this->login, '@') => 'email',
            User::where('login', $this->login)->exists() => 'login',
            default => 'phone',
        };

        if (! Auth::attempt([$field => $this->login, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'login' => 'Identifiants incorrects.',
            ]);
        }

        if (! $this->allowsUser(Auth::user())) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => $this->deniedMessage(),
            ]);
        }

        session()->regenerate();

        $this->redirect(Auth::user()->is_super_admin ? '/admin' : route('dashboard'), navigate: false);
    }

    /**
     * Le portail accepte-t-il ce compte ? (L'espace pressing accepte tout
     * compte staff ; un super admin y est simplement renvoyé vers /admin.)
     */
    protected function allowsUser(User $user): bool
    {
        return true;
    }

    protected function deniedMessage(): string
    {
        return 'Identifiants incorrects.';
    }

    #[Layout('layouts.guest')]
    public function render()
    {
        return view('livewire.auth.login')->title('Espace pressing — PressLink');
    }
}
