<?php

namespace App\Livewire\Sales\Pressings;

use App\Livewire\Concerns\ActsAsSalesAgent;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Pressing;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Fiche d'un pressing apporté par le commercial : coordonnées (modifiables),
 * gérant, abonnement, activité et paiements de l'abonnement. Pas d'accès aux
 * clients ni à la caisse du pressing, pas de suppression ni de changement
 * d'abonnement.
 */
class Show extends Component
{
    use ActsAsSalesAgent, ShowsToasts;

    #[Locked]
    public int $pressingId;

    public bool $editing = false;

    public string $name = '';

    public string $phone = '';

    public string $city = '';

    public string $address = '';

    public function mount(Pressing $pressing): void
    {
        $this->pressingId = $this->ownPressing($pressing->id)->id;
    }

    public function edit(): void
    {
        $pressing = $this->ownPressing($this->pressingId);
        $this->name = $pressing->name;
        $this->phone = PhoneNumber::national($pressing->phone);
        $this->city = (string) $pressing->city;
        $this->address = (string) $pressing->address;
        $this->resetErrorBag();
        $this->editing = true;
    }

    public function save(): void
    {
        $pressing = $this->ownPressing($this->pressingId);
        $this->phone = $this->phone === '' ? '' : PhoneNumber::normalize($this->phone);

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', new IvorianPhone(mobileOnly: false)],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $pressing->update([
            'name' => trim($this->name),
            'phone' => $this->phone,
            'city' => trim($this->city) ?: null,
            'address' => trim($this->address) ?: null,
        ]);

        $this->editing = false;
        $this->toast('Coordonnées du pressing enregistrées.');
    }

    #[Layout('layouts.sales', ['active' => 'pressings', 'title' => 'Pressing — PressLink'])]
    public function render(): View
    {
        $pressing = $this->ownPressing($this->pressingId)->load(['admins', 'subscription']);
        $orders = $pressing->orders();

        return view('livewire.sales.pressings.show', [
            'pressing' => $pressing,
            'activity' => [
                'ordersThisMonth' => (clone $orders)->where('created_at', '>=', now()->startOfMonth())->count(),
                'ordersTotal' => (clone $orders)->count(),
                'lastOrderAt' => (clone $orders)->max('created_at'),
                'team' => $pressing->staff()->wherePivot('is_active', true)->count(),
            ],
            'payments' => $pressing->subscriptionRequests()->subscriptionPayments()->latest('handled_at')->get(),
        ]);
    }
}
