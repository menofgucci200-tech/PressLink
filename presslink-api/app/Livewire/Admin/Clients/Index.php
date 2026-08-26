<?php

namespace App\Livewire\Admin\Clients;

use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vue Clients plateforme — Super Admin, pour retrouver un client sans
 * connaître son pressing (au-delà du périmètre strict MVP Phase 7).
 */
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[Layout('layouts.admin', ['active' => 'clients', 'title' => 'Clients'])]
    public function render()
    {
        $query = Customer::withCount(['pressings', 'orders']);

        if ($this->search !== '') {
            $term = $this->search;
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"));
        }

        return view('livewire.admin.clients.index', [
            'customers' => $query->latest()->paginate(20),
        ]);
    }
}
