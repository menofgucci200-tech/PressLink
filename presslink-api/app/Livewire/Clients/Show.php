<?php

namespace App\Livewire\Clients;

use App\Enums\OrderStatus;
use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Show extends Component
{
    public Customer $customer;

    public function mount(Customer $customer): void
    {
        abort_unless(auth()->user()->can('view', $customer), 403);
        $this->customer = $customer;
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

        return view('livewire.clients.show', [
            'orders' => $orders,
            'totalSpentFcfa' => $orders->where('status', '!=', OrderStatus::Annulee)->sum('total_fcfa'),
            'joinedAt' => $joinedAt,
        ]);
    }
}
