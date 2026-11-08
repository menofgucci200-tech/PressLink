<?php

namespace App\Livewire\Concerns;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\Permission;
use App\Models\Customer;
use App\Models\Pressing;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;

/**
 * Choix du client au comptoir : recherche, création, numéro déjà connu
 * (écran de commande et dépôt rapide).
 */
trait PicksOrderCustomer
{
    public string $clientSearch = '';

    public ?int $selectedCustomerId = null;

    public bool $showNewClientForm = false;

    public string $newFirstName = '';

    public string $newLastName = '';

    public string $newPhone = '';

    /**
     * Numéro déjà enregistré (par un autre pressing ou par le client) : on
     * montre le nom connu et le staff confirme qu'il s'agit bien de son
     * client, ce qui rattrape aussi une erreur de saisie du numéro.
     */
    #[Locked]
    public ?int $knownCustomerId = null;

    public string $knownCustomerName = '';

    abstract private function pressing(): Pressing;

    /** Le client vient d'être choisi : la suite de l'écran prend le relais. */
    abstract protected function customerPicked(): void;

    public function pickCustomer(int $customerId): void
    {
        $customer = $this->pressing()->customers()->where('customers.id', $customerId)->first();
        abort_if($customer === null, 403);

        if ($customer->is_active === false) {
            $this->dispatch('toast', type: 'error', message: CreateOrderAction::INACTIVE_CUSTOMER_MESSAGE);

            return;
        }

        $this->selectedCustomerId = $customerId;
        $this->showNewClientForm = false;
        $this->clientSearch = '';
        $this->customerPicked();
    }

    /** Touche Entrée dans la recherche : prend le premier client trouvé. */
    public function pickFirstCustomer(): void
    {
        $first = $this->searchCustomers($this->pressing())->first();

        if ($first !== null) {
            $this->pickCustomer($first->id);
        } elseif (trim($this->clientSearch) !== '') {
            $this->startNewCustomer();
        }
    }

    public function clearCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->dispatch('comptoir-focus', target: 'client');
    }

    /** Ouvre la création de client, préremplie avec ce qui a été tapé (numéro ou nom). */
    public function startNewCustomer(): void
    {
        $typed = trim($this->clientSearch);

        if (preg_match('/^[+\d\s.\-]{8,}$/', $typed)) {
            $this->newPhone = PhoneNumber::normalize($typed);
        } elseif ($typed !== '') {
            $parts = preg_split('/\s+/', $typed, 2);
            $this->newFirstName = $parts[0];
            $this->newLastName = $parts[1] ?? '';
        }

        $this->showNewClientForm = true;
    }

    public function createAndPickCustomer(): void
    {
        $pressing = $this->pressing();
        abort_unless(auth()->user()->may(Permission::OrdersCreate, $pressing), 403);

        if (! $pressing->acceptsCustomers()) {
            $this->addError('newPhone', $pressing->closedMessage());

            return;
        }

        $this->newPhone = PhoneNumber::normalize($this->newPhone);
        $this->reset(['knownCustomerId', 'knownCustomerName']);

        $this->validate(['newPhone' => ['required', 'string', new IvorianPhone]]);

        $known = Customer::inDirectoryOf($pressing)->where('phone', $this->newPhone)->first();

        if ($known !== null) {
            // Déjà dans le fichier de ce pressing : on le choisit directement.
            if ($pressing->customers()->whereKey($known->id)->exists()) {
                $this->pickCreatedCustomer($known);

                return;
            }

            $this->knownCustomerId = $known->id;
            $this->knownCustomerName = $known->fullName();

            return;
        }

        $this->validate([
            'newFirstName' => ['required', 'string', 'max:100'],
            'newLastName' => ['required', 'string', 'max:100'],
        ]);

        $customer = DB::transaction(function () use ($pressing) {
            $customer = Customer::create([
                'phone' => $this->newPhone,
                'sandbox_pressing_id' => $pressing->customerDirectoryId(),
                'first_name' => $this->newFirstName,
                'last_name' => $this->newLastName,
            ]);
            $pressing->customers()->attach($customer->id, ['joined_at' => now()]);

            return $customer;
        });

        $this->pickCreatedCustomer($customer);
    }

    /** « Oui, c'est mon client » : rattaché au pressing avec le nom déjà connu. */
    public function confirmKnownCustomer(): void
    {
        abort_unless(auth()->user()->may(Permission::OrdersCreate, $this->pressing()), 403);

        if (! $this->pressing()->acceptsCustomers()) {
            $this->addError('newPhone', $this->pressing()->closedMessage());

            return;
        }

        // Le client confirmé doit être celui du numéro saisi (jamais un autre id).
        $customer = Customer::inDirectoryOf($this->pressing())->whereKey($this->knownCustomerId)->where('phone', PhoneNumber::normalize($this->newPhone))->firstOrFail();
        $this->pressing()->customers()->syncWithoutDetaching([$customer->id => ['joined_at' => now()]]);

        $this->pickCreatedCustomer($customer);
    }

    /** « Corriger le numéro » : on revient au formulaire. */
    public function dismissKnownCustomer(): void
    {
        $this->reset(['knownCustomerId', 'knownCustomerName']);
    }

    private function pickCreatedCustomer(Customer $customer): void
    {
        $this->showNewClientForm = false;
        $this->reset(['newFirstName', 'newLastName', 'newPhone', 'knownCustomerId', 'knownCustomerName', 'clientSearch']);

        if ($customer->is_active === false) {
            $this->dispatch('toast', type: 'error', message: CreateOrderAction::INACTIVE_CUSTOMER_MESSAGE);

            return;
        }

        $this->selectedCustomerId = $customer->id;
        $this->customerPicked();
    }

    /** @return Collection<int, Customer> */
    private function searchCustomers(Pressing $pressing): Collection
    {
        $term = trim($this->clientSearch);

        if ($term === '') {
            return $pressing->customers()->orderByDesc('pressing_customers.joined_at')->limit(6)->get();
        }

        // « Awa Tra » : chaque mot doit se retrouver dans le prénom ou le nom ;
        // « 07 08 12 44 » : on compare les chiffres au numéro.
        $words = preg_split('/\s+/', $term) ?: [];
        $digits = preg_replace('/\D/', '', $term);

        return $pressing->customers()
            ->where(function ($q) use ($words, $digits) {
                $q->where(function ($names) use ($words) {
                    foreach ($words as $word) {
                        $names->where(fn ($n) => $n->where('first_name', 'like', "%{$word}%")->orWhere('last_name', 'like', "%{$word}%"));
                    }
                });

                if (strlen($digits) >= 3) {
                    $q->orWhere('phone', 'like', "%{$digits}%");
                }
            })
            ->limit(6)->get();
    }
}
