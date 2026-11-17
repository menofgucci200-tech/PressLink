<?php

namespace App\Livewire\Sales\Pressings;

use App\Livewire\Concerns\ActsAsSalesAgent;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** « Mes pressings » : les pressings apportés par le commercial. */
class Index extends Component
{
    use ActsAsSalesAgent, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Layout('layouts.sales', ['active' => 'pressings', 'title' => 'Mes pressings — PressLink'])]
    public function render(): View
    {
        $search = trim($this->search);

        $pressings = $this->agent()->pressings()
            ->with('subscription')
            ->withCount(['orders as orders_this_month' => fn ($query) => $query->where('created_at', '>=', now()->startOfMonth())])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $search).'%')))
            ->latest('sales_agent_attached_at')
            ->paginate(20);

        return view('livewire.sales.pressings.index', ['pressings' => $pressings]);
    }
}
