<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    /**
     * Synchronisé avec `?search=` dans l'URL : permet à la recherche
     * globale de l'en-tête d'atterrir ici avec le terme déjà pré-rempli.
     */
    #[Url]
    public string $search = '';

    /** Permet de deep-linker depuis le dashboard (ex. tâches "à traiter"). */
    #[Url]
    public string $status = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
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
            $filters = [
                'search' => $this->search ?: null,
                'date_from' => $this->dateFrom ?: null,
                'date_to' => $this->dateTo ?: null,
            ];

            $orders = $pressing->filteredOrders([...$filters, 'status' => $this->status ?: null])
                ->withReadySince()
                ->latest()
                ->paginate(15);

            // Compteurs des filtres de statut : mêmes recherche et dates que la
            // liste, pour annoncer ce qu'on obtient en cliquant sur un statut.
            $counts = $pressing->filteredOrders($filters)
                ->reorder()
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
