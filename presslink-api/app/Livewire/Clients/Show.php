<?php

namespace App\Livewire\Clients;

use App\Actions\Auth\ResetPasswordToDefault;
use App\Enums\OrderStatus;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Show extends Component
{
    use ShowsToasts;

    public Customer $customer;

    public function mount(Customer $customer): void
    {
        abort_unless(auth()->user()->can('view', $customer), 403);
        $this->customer = $customer;
    }

    /**
     * Client qui a oublié son mot de passe, au comptoir : réservé aux
     * administrateurs du pressing (le compte client est commun à tous les
     * pressings qu'il a rejoints).
     */
    public function resetPassword(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->isAdminOf($pressing) && auth()->user()->can('view', $this->customer), 403);

        (new ResetPasswordToDefault)->handle($this->customer);

        $this->toast('Mot de passe de '.$this->customer->fullName().' réinitialisé à '.ResetPasswordToDefault::DEFAULT_PASSWORD.'. Il doit maintenant le changer pour sécuriser son compte.');
    }

    #[Layout('layouts.dashboard', ['active' => 'clients', 'title' => 'Client'])]
    public function render()
    {
        $pressing = auth()->user()->currentPressing();

        $orders = $this->customer->orders()
            ->where('pressing_id', $pressing->id)
            ->orderByDesc('dropped_off_at')
            ->get();

        $joinedAt = $this->customer->pressings()
            ->where('pressings.id', $pressing->id)
            ->first()
            ?->pivot
            ?->joined_at;

        $pressingForRole = auth()->user()->currentPressing();

        return view('livewire.clients.show', [
            'canResetPassword' => $pressingForRole && auth()->user()->isAdminOf($pressingForRole),
            'orders' => $orders,
            'totalSpentFcfa' => $orders->where('status', '!=', OrderStatus::Annulee)->sum('total_fcfa'),
            'joinedAt' => $joinedAt,
        ]);
    }
}
