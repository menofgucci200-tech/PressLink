<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vue Commandes plateforme — Super Admin. Utile pour du support :
 * retrouver n'importe quelle commande sans connaître son pressing
 * d'origine (au-delà du périmètre strict MVP Phase 7).
 */
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    #[Layout('layouts.admin', ['active' => 'orders', 'title' => 'Commandes'])]
    public function render()
    {
        $query = Order::with(['pressing', 'customer']);

        if ($this->search !== '') {
            $term = $this->search;
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"))
                ->orWhereHas('pressing', fn ($p) => $p->where('name', 'like', "%{$term}%")));
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return view('livewire.admin.orders.index', [
            'orders' => $query->latest()->paginate(20),
            'statuses' => OrderStatus::cases(),
        ]);
    }
}
