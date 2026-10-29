<?php

namespace App\Livewire\Auth;

use App\Actions\Sandbox\StartSandbox;
use App\Enums\PressingRole;
use App\Models\SandboxTrial;
use App\Models\User;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Découvrir avec un pressing fictif » : le visiteur donne le nom de son
 * pressing et son WhatsApp, et arrive connecté sur un pressing de test déjà
 * rempli, pour StartSandbox::DAYS jours. Le navigateur garde le test en
 * mémoire (cookie chiffré) : le visiteur peut le reprendre tant qu'il court.
 */
class SandboxStart extends Component
{
    /** Tests acceptés par heure depuis une même adresse. */
    public const MAX_PER_HOUR = 3;

    /** Cookie qui retient le test en cours sur ce navigateur. */
    public const COOKIE = 'presslink_test';

    public string $pressingName = '';

    public string $phone = '';

    /** Champ invisible : rempli seulement par les robots. */
    public string $website = '';

    /** Le visiteur arrive ici parce que son test vient de se terminer. */
    public bool $ended = false;

    public function mount(): void
    {
        $this->ended = (bool) session('sandbox_ended', false);
    }

    public function start(StartSandbox $startSandbox): void
    {
        $phone = $this->phone === '' ? '' : PhoneNumber::normalize($this->phone);

        Validator::make(['pressingName' => $this->pressingName, 'phone' => $phone], [
            'pressingName' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', new IvorianPhone],
        ], [
            'pressingName.required' => 'Indiquez le nom de votre pressing.',
            'phone.required' => 'Indiquez votre numéro WhatsApp.',
        ])->validate();

        if ($this->website !== '') {
            return;
        }

        $throttleKey = 'test-pressing:'.request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PER_HOUR)) {
            throw ValidationException::withMessages([
                'phone' => 'Trop de tests lancés depuis cette connexion. Réessayez dans une heure, ou écrivez-nous.',
            ]);
        }
        RateLimiter::hit($throttleKey, 3600);

        ['manager' => $manager, 'trial' => $trial] = $startSandbox->handle(trim($this->pressingName), $phone, request()->ip());

        Cookie::queue(self::COOKIE, (string) $trial->id, StartSandbox::DAYS * 24 * 60);
        $this->logIn($manager, 'Bienvenue dans votre pressing de test ! Les clients et les commandes sont fictifs : essayez tout librement.');
    }

    /** Reprendre le test en cours sur ce navigateur. */
    public function resume(): void
    {
        $trial = $this->runningTrial();
        $manager = $trial?->pressing?->staff()->wherePivot('role', PressingRole::Admin->value)->first();

        if ($manager === null) {
            $this->redirectRoute('sandbox.start', navigate: true);

            return;
        }

        $this->logIn($manager, 'Vous reprenez votre pressing de test.');
    }

    private function logIn(User $manager, string $message): void
    {
        Auth::login($manager, remember: true);
        session()->regenerate();
        session()->flash('toast', ['type' => 'success', 'message' => $message]);

        $this->redirect(route('dashboard'), navigate: false);
    }

    /** Test en cours retenu par ce navigateur, s'il y en a un. */
    private function runningTrial(): ?SandboxTrial
    {
        $id = request()->cookie(self::COOKIE);
        $trial = is_numeric($id) ? SandboxTrial::with('pressing')->find((int) $id) : null;

        return $trial?->isRunning() ? $trial : null;
    }

    #[Layout('layouts.guest')]
    public function render()
    {
        return view('livewire.auth.sandbox-start', [
            'runningTrial' => $this->runningTrial(),
        ])->title('Tester PressLink avec un pressing fictif — PressLink');
    }
}
