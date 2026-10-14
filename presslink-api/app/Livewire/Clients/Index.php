<?php

namespace App\Livewire\Clients;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ShowsToasts, WithPagination;

    /**
     * Synchronisé avec `?search=` dans l'URL : permet à la recherche
     * globale de l'en-tête d'atterrir ici avec le terme déjà pré-rempli.
     */
    #[Url]
    public string $search = '';

    /** Tri : 'recent' (derniers inscrits), 'nom' ou 'commandes'. */
    #[Url(as: 'tri', except: 'recent')]
    public string $sort = 'recent';

    #[Url(as: 'sens', except: 'desc')]
    public string $direction = 'desc';

    public bool $showCreateForm = false;

    public string $firstName = '';

    public string $lastName = '';

    public string $phone = '';

    public ?string $errorMessage = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        $defaults = ['nom' => 'asc', 'commandes' => 'desc'];
        if (! isset($defaults[$column])) {
            return;
        }

        $this->direction = $this->sort === $column
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : $defaults[$column];
        $this->sort = $column;
        $this->resetPage();
    }

    public function createClient(): void
    {
        $this->errorMessage = null;
        $pressing = auth()->user()->currentPressing();

        $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/'],
        ]);

        $subscription = $pressing->subscription;
        $alreadyLinked = $pressing->customers()->where('phone', $this->phone)->exists();

        if (! $alreadyLinked && $subscription !== null && ! $subscription->allowsNewClient()) {
            $this->errorMessage = "Limite atteinte : votre formule {$subscription->plan->label()} autorise jusqu'à {$subscription->plan->clientsLimit()} client(s).";

            return;
        }

        DB::transaction(function () use ($pressing) {
            $customer = Customer::firstOrCreate(
                ['phone' => $this->phone],
                [
                    'first_name' => $this->firstName,
                    'last_name' => $this->lastName,
                    'password' => Customer::DEFAULT_WALK_IN_PASSWORD,
                    'must_change_password' => true,
                    'phone_verified_at' => now(),
                ],
            );

            $pressing->customers()->syncWithoutDetaching([$customer->id => ['joined_at' => now()]]);
        });

        $this->toast("{$this->firstName} {$this->lastName} a été ajouté à vos clients. Mot de passe par défaut : ".Customer::DEFAULT_WALK_IN_PASSWORD.'.');
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

            $direction = $this->direction === 'asc' ? 'asc' : 'desc';
            match ($this->sort) {
                'nom' => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction),
                'commandes' => $query->orderBy('orders_count', $direction),
                default => $query->orderByDesc('pressing_customers.joined_at'),
            };

            $clients = $query->orderByDesc('customers.id')->paginate(15);
        }

        return view('livewire.clients.index', ['clients' => $clients]);
    }
}
