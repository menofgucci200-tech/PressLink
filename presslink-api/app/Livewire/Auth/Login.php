<?php

namespace App\Livewire\Auth;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

        // Pas d'essais de mots de passe à la chaîne : 5 par minute pour un
        // identifiant depuis une même adresse.
        $throttleKey = 'connexion:'.Str::lower(trim($this->login)).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($throttleKey).' secondes.',
            ]);
        }

        $field = match (true) {
            str_contains($this->login, '@') => 'email',
            User::where('login', $this->login)->exists() => 'login',
            default => 'phone',
        };
        // « 07 08 12 44 00 » comme « +2250708124400 ».
        $identifier = $field === 'phone' ? PhoneNumber::normalize($this->login) : $this->login;

        if (! Auth::attempt([$field => $identifier, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

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

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        // Journal de chaque pressing du membre : qui s'est connecté, et quand.
        $user = Auth::user();
        if (! $user->is_super_admin) {
            foreach ($user->pressings()->wherePivot('is_active', true)->get() as $pressing) {
                ActivityLog::record($pressing->id, ActivityAction::StaffLogin, "Connexion de {$user->name}");
            }
        }

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
