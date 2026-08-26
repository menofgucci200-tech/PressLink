<?php

namespace App\Livewire\Clients;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showCreateForm = false;

    public string $firstName = '';

    public string $lastName = '';

    public string $phone = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function createClient(): void
    {
        $pressing = auth()->user()->currentPressing();

        $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/'],
        ]);

        DB::transaction(function () use ($pressing) {
            $customer = Customer::firstOrCreate(
                ['phone' => $this->phone],
                [
                    'first_name' => $this->firstName,
                    'last_name' => $this->lastName,
                    'password' => Customer::DEFAULT_WALK_IN_PASSWORD,
                    'phone_verified_at' => now(),
                ],
            );

            $pressing->customers()->syncWithoutDetaching([$customer->id => ['joined_at' => now()]]);
        });

        $this->reset(['firstName', 'lastName', 'phone', 'showCreateForm']);
    }

    #[Layout('layouts.dashboard', ['active' => 'clients', 'title' => 'Clients'])]
    public function render()
    {
        $pressing = auth()->user()->currentPressing();

        $clients = collect();

        if ($pressing !== null) {
            $query = $pressing->customers()->withCount([
                'orders' => fn ($q) => $q->where('pressing_id', $pressing->id),
            ]);

            if ($this->search !== '') {
                $term = $this->search;
                $query->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            }

            $clients = $query->orderByDesc('pressing_customers.joined_at')->paginate(15);
        }

        return view('livewire.clients.index', ['clients' => $clients]);
    }
}
