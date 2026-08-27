<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $showExportMenu = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    #[Layout('layouts.dashboard', ['active' => 'orders', 'title' => 'Commandes'])]
    public function render()
    {
        $pressing = auth()->user()->currentPressing();

        $orders = collect();
        $counts = [];

        if ($pressing !== null) {
            $orders = $pressing->filteredOrders([
                'status' => $this->status ?: null,
                'search' => $this->search ?: null,
                'date_from' => $this->dateFrom ?: null,
                'date_to' => $this->dateTo ?: null,
            ])->latest()->paginate(15);

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
