<?php

namespace App\Livewire\Auth;

use App\Actions\Pressings\SignUpPressing;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Inscription d'un pressing depuis le site, en deux étapes : le pressing,
 * puis le compte du gérant. Le compte est créé avec un essai gratuit et le
 * gérant arrive connecté sur son tableau de bord.
 */
class PressingSignup extends Component
{
    /** Inscriptions acceptées par heure depuis une même adresse. */
    public const MAX_PER_HOUR = 3;

    public int $step = 1;

    // Étape 1 : le pressing
    public string $pressingName = '';

    public string $city = '';

    public string $pressingPhone = '';

    // Étape 2 : le compte du gérant
    public string $ownerName = '';

    public string $ownerPhone = '';

    public string $email = '';

    public string $password = '';

    public bool $acceptTerms = false;

    /** Champ invisible : rempli seulement par les robots. */
    public string $website = '';

    public function next(): void
    {
        $this->validateStep(1);
        $this->ownerPhone = $this->ownerPhone !== '' ? $this->ownerPhone : $this->pressingPhone;
        $this->step = 2;
    }

    public function back(): void
    {
        $this->step = 1;
    }

    public function register(SignUpPressing $signUp): void
    {
        $this->validateStep(1);
        $this->validateStep(2);

        if ($this->website !== '') {
            return;
        }

        $throttleKey = 'inscription-pressing:'.request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PER_HOUR)) {
            throw ValidationException::withMessages([
                'ownerPhone' => 'Trop d\'inscriptions depuis cette connexion. Réessayez dans une heure, ou appelez-nous.',
            ]);
        }
        RateLimiter::hit($throttleKey, 3600);

        $owner = $signUp->handle([
            'pressing_name' => trim($this->pressingName),
            'city' => trim($this->city),
            'pressing_phone' => PhoneNumber::normalize($this->pressingPhone),
            'owner_name' => trim($this->ownerName),
            'owner_phone' => PhoneNumber::normalize($this->ownerPhone),
            'email' => trim($this->email),
            'password' => $this->password,
        ]);

        Auth::login($owner, remember: true);
        session()->regenerate();
        session()->flash('toast', ['type' => 'success', 'message' => 'Bienvenue sur PressLink ! Votre pressing est prêt : suivez le guide de démarrage.']);

        $this->redirect(route('dashboard'), navigate: false);
    }

    /** Les numéros sont vérifiés au format international ; les champs gardent la saisie. */
    private function validateStep(int $step): void
    {
        $normalized = fn (string $phone) => $phone === '' ? '' : PhoneNumber::normalize($phone);

        [$data, $rules] = $step === 1
            ? [
                ['pressingName' => $this->pressingName, 'city' => $this->city, 'pressingPhone' => $normalized($this->pressingPhone)],
                [
                    'pressingName' => ['required', 'string', 'min:2', 'max:150'],
                    'city' => ['required', 'string', 'max:100'],
                    'pressingPhone' => ['required', 'string', new IvorianPhone(mobileOnly: false)],
                ],
            ]
            : [
                ['ownerName' => $this->ownerName, 'ownerPhone' => $normalized($this->ownerPhone), 'email' => $this->email, 'password' => $this->password, 'acceptTerms' => $this->acceptTerms],
                [
                    'ownerName' => ['required', 'string', 'min:2', 'max:100'],
                    'ownerPhone' => ['required', 'string', new IvorianPhone, 'unique:users,phone'],
                    'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
                    'password' => ['required', 'string', 'min:8'],
                    'acceptTerms' => ['accepted'],
                ],
            ];

        Validator::make($data, $rules, [
            'pressingName.required' => 'Indiquez le nom de votre pressing.',
            'city.required' => 'Indiquez votre commune ou votre quartier.',
            'pressingPhone.required' => 'Indiquez le téléphone du pressing.',
            'ownerName.required' => 'Indiquez votre nom.',
            'ownerPhone.required' => 'Indiquez votre numéro : il vous servira à vous connecter.',
            'ownerPhone.unique' => 'Ce numéro a déjà un compte PressLink : connectez-vous avec lui.',
            'email.unique' => 'Cette adresse e-mail a déjà un compte PressLink.',
            'password.required' => 'Choisissez un mot de passe.',
            'password.min' => 'Le mot de passe doit faire au moins 8 caractères.',
            'acceptTerms.accepted' => 'Acceptez les conditions générales pour continuer.',
        ], [
            'email' => 'adresse e-mail',
        ])->validate();
    }

    #[Layout('layouts.guest')]
    public function render()
    {
        return view('livewire.auth.pressing-signup')->title('Inscrire mon pressing — PressLink');
    }
}
