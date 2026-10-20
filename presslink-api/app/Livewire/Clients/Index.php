<?php

namespace App\Livewire\Clients;

use App\Enums\Permission;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Customer;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
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

    /** Numéro déjà enregistré ailleurs : nom connu, à confirmer par le staff. */
    public ?int $knownCustomerId = null;

    public string $knownCustomerName = '';

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
        $this->reset(['knownCustomerId', 'knownCustomerName']);
        $pressing = auth()->user()->currentPressing();
        abort_unless(auth()->user()->may(Permission::OrdersCreate, $pressing), 403);

        if (! $pressing->acceptsCustomers()) {
            $this->errorMessage = $pressing->closedMessage();

            return;
        }

        $this->phone = PhoneNumber::normalize($this->phone);

        $this->validate(['phone' => ['required', 'string', new IvorianPhone]]);

        $known = Customer::where('phone', $this->phone)->first();

        if ($known !== null && $pressing->customers()->whereKey($known->id)->exists()) {
            $this->errorMessage = $known->fullName().' fait déjà partie de vos clients.';

            return;
        }

        // Numéro déjà enregistré (par un autre pressing ou par le client) :
        // le staff confirme qu'il s'agit bien de son client.
        if ($known !== null) {
            $this->knownCustomerId = $known->id;
            $this->knownCustomerName = $known->fullName();

            return;
        }

        $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
        ]);

        if (! $this->allowsAnotherClient()) {
            return;
        }

        DB::transaction(function () use ($pressing) {
            $customer = Customer::create(['phone' => $this->phone, 'first_name' => $this->firstName, 'last_name' => $this->lastName]);
            $pressing->customers()->attach($customer->id, ['joined_at' => now()]);
        });

        $this->toast("{$this->firstName} {$this->lastName} a été ajouté à vos clients. Il retrouvera ses commandes en créant son compte PressLink avec ce numéro.");
        $this->reset(['firstName', 'lastName', 'phone', 'showCreateForm']);
    }

    /** « Oui, c'est mon client » : ajouté au fichier avec le nom déjà connu. */
    public function confirmKnownCustomer(): void
    {
        $customer = Customer::findOrFail($this->knownCustomerId);
        $pressing = auth()->user()->currentPressing();
        abort_unless(auth()->user()->may(Permission::OrdersCreate, $pressing), 403);

        if (! $pressing->acceptsCustomers()) {
            $this->errorMessage = $pressing->closedMessage();

            return;
        }

        if (! $this->allowsAnotherClient()) {
            return;
        }

        auth()->user()->currentPressing()->customers()->syncWithoutDetaching([$customer->id => ['joined_at' => now()]]);

        $this->toast($customer->fullName().' a été ajouté à vos clients.');
        $this->reset(['firstName', 'lastName', 'phone', 'showCreateForm', 'knownCustomerId', 'knownCustomerName']);
    }

    /** « Corriger le numéro » : on revient au formulaire. */
    public function dismissKnownCustomer(): void
    {
        $this->reset(['knownCustomerId', 'knownCustomerName']);
    }

    private function allowsAnotherClient(): bool
    {
        $subscription = auth()->user()->currentPressing()->subscription;

        if ($subscription !== null && ! $subscription->allowsNewClient()) {
            $this->errorMessage = "Limite atteinte : votre formule {$subscription->plan->label()} autorise jusqu'à {$subscription->plan->clientsLimit()} client(s).";

            return false;
        }

        return true;
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
