<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Models\Pressing;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    #[Layout('layouts.dashboard', ['active' => 'orders', 'title' => 'Commandes'])]
    public function render()
    {
        /** @var Pressing|null $pressing */
        $pressing = auth()->user()->currentPressing();

        $orders = collect();
        $counts = [];

        if ($pressing !== null) {
            $query = $pressing->orders()->with('customer')->latest();

            if ($this->status !== '') {
                $query->where('status', $this->status);
            }

            if ($this->search !== '') {
                $term = $this->search;
                $query->where(function ($q) use ($term) {
                    $q->where('order_number', 'like', "%{$term}%")
                        ->orWhereHas('customer', function ($c) use ($term) {
                            $c->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%")
                                ->orWhere('phone', 'like', "%{$term}%");
                        });
                });
            }

            $orders = $query->paginate(15);

            $counts = $pressing->orders()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->all();
        }

        return view('livewire.orders.index', [
            'orders' => $orders,
            'counts' => $counts,
            'statuses' => OrderStatus::cases(),
        ]);
    }
}
