<?php

namespace App\Livewire\Sales\Pressings;

use App\Actions\Pressings\SignUpPressing;
use App\Livewire\Concerns\ActsAsSalesAgent;
use App\Models\Pressing;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Le commercial inscrit un pressing sur le terrain : le pressing, le compte
 * de son gérant (mot de passe provisoire) et son mois offert, qui commence
 * tout de suite. Le pressing lui est rattaché.
 */
class Create extends Component
{
    use ActsAsSalesAgent;

    public string $pressingName = '';

    public string $city = '';

    public string $address = '';

    public string $pressingPhone = '';

    public string $ownerName = '';

    public string $ownerPhone = '';

    public string $password = '';

    public bool $termsShown = false;

    public ?int $createdPressingId = null;

    public function mount(): void
    {
        $this->agent();
    }

    public function create(SignUpPressing $signUp): void
    {
        $agent = $this->agent();
        $this->resetErrorBag();
        $normalized = fn (string $phone) => $phone === '' ? '' : PhoneNumber::normalize($phone);

        Validator::make([
            'pressingName' => $this->pressingName,
            'city' => $this->city,
            'address' => $this->address,
            'pressingPhone' => $normalized($this->pressingPhone),
            'ownerName' => $this->ownerName,
            'ownerPhone' => $normalized($this->ownerPhone),
            'password' => $this->password,
            'termsShown' => $this->termsShown,
        ], [
            'pressingName' => ['required', 'string', 'min:2', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'pressingPhone' => ['required', 'string', new IvorianPhone(mobileOnly: false)],
            'ownerName' => ['required', 'string', 'min:2', 'max:100'],
            'ownerPhone' => ['required', 'string', new IvorianPhone, 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
            'termsShown' => ['accepted'],
        ], [
            'pressingName.required' => 'Indiquez le nom du pressing.',
            'city.required' => 'Indiquez la commune ou le quartier.',
            'pressingPhone.required' => 'Indiquez le téléphone du pressing.',
            'ownerName.required' => 'Indiquez le nom du gérant.',
            'ownerPhone.required' => 'Indiquez le numéro du gérant : il lui sert à se connecter.',
            'ownerPhone.unique' => 'Ce numéro a déjà un compte PressLink. Si ce pressing est déjà inscrit, signalez-le plutôt.',
            'password.required' => 'Choisissez un mot de passe provisoire.',
            'password.min' => 'Le mot de passe doit faire au moins 8 caractères.',
            'termsShown.accepted' => 'Le gérant doit avoir pris connaissance des conditions générales.',
        ])->validate();

        $owner = $signUp->handle([
            'pressing_name' => trim($this->pressingName),
            'city' => trim($this->city),
            'address' => trim($this->address),
            'pressing_phone' => PhoneNumber::normalize($this->pressingPhone),
            'owner_name' => trim($this->ownerName),
            'owner_phone' => PhoneNumber::normalize($this->ownerPhone),
            'password' => $this->password,
        ], $agent, temporaryPassword: true);

        $this->createdPressingId = $owner->pressings()->value('pressings.id');
        $this->reset(['pressingName', 'city', 'address', 'pressingPhone', 'ownerName', 'ownerPhone', 'password', 'termsShown']);
    }

    public function another(): void
    {
        $this->createdPressingId = null;
    }

    #[Layout('layouts.sales', ['active' => 'create', 'title' => 'Inscrire un pressing — PressLink'])]
    public function render(): View
    {
        $created = $this->createdPressingId ? $this->ownPressing($this->createdPressingId)->load(['admins', 'subscription']) : null;

        return view('livewire.sales.pressings.create', ['created' => $created]);
    }
}
