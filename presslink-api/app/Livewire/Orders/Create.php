<?php

namespace App\Livewire\Orders;

use App\Actions\Orders\CreateOrderAction;
use App\Models\Customer;
use App\Models\Pressing;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Wizard de création de commande — 4 étapes (Cahier §6.1 / UI haute fidélité §10-14).
 */
class Create extends Component
{
    public int $step = 1;

    // Étape 1 — client
    public string $clientSearch = '';

    public ?int $selectedCustomerId = null;

    public bool $showNewClientForm = false;

    public string $newFirstName = '';

    public string $newLastName = '';

    public string $newPhone = '';

    // Étape 2 — articles
    /** @var array<int, array{service_id: int, name: string, unit_price_fcfa: int, quantity: int}> */
    public array $items = [];

    // Étape 3 — détails
    public string $expectedAt = '';

    public string $notes = '';

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->expectedAt = now()->addDays(2)->format('Y-m-d');
    }

    public function pickCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->showNewClientForm = false;
    }

    public function createAndPickCustomer(): void
    {
        $pressing = $this->pressing();

        $this->validate([
            'newFirstName' => ['required', 'string', 'max:100'],
            'newLastName' => ['required', 'string', 'max:100'],
            'newPhone' => ['required', 'string', 'regex:/^\+2250[0-9]{9}$/'],
        ]);

        $customer = DB::transaction(function () use ($pressing) {
            $customer = Customer::firstOrCreate(
                ['phone' => $this->newPhone],
                [
                    'first_name' => $this->newFirstName,
                    'last_name' => $this->newLastName,
                    'password' => Customer::DEFAULT_WALK_IN_PASSWORD,
                    'phone_verified_at' => now(),
                ],
            );

            $pressing->customers()->syncWithoutDetaching([$customer->id => ['joined_at' => now()]]);

            return $customer;
        });

        $this->selectedCustomerId = $customer->id;
        $this->showNewClientForm = false;
        $this->reset(['newFirstName', 'newLastName', 'newPhone']);
    }

    public function addItem(int $serviceId): void
    {
        $service = $this->pressing()->services()->findOrFail($serviceId);

        foreach ($this->items as $i => $item) {
            if ($item['service_id'] === $service->id) {
                $this->items[$i]['quantity']++;

                return;
            }
        }

        $this->items[] = [
            'service_id' => $service->id,
            'name' => $service->name,
            'unit_price_fcfa' => $service->price_fcfa,
            'quantity' => 1,
        ];
    }

    public function incrementItem(int $index): void
    {
        $this->items[$index]['quantity']++;
    }

    public function decrementItem(int $index): void
    {
        if ($this->items[$index]['quantity'] <= 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);

            return;
        }

        $this->items[$index]['quantity']--;
    }

    public function getTotalProperty(): int
    {
        return collect($this->items)->sum(fn ($item) => $item['unit_price_fcfa'] * $item['quantity']);
    }

    public function goToStep(int $step): void
    {
        $this->step = $step;
    }

    public function next(): void
    {
        if ($this->step === 1 && $this->selectedCustomerId === null) {
            return;
        }

        if ($this->step === 2 && $this->items === []) {
            return;
        }

        $this->step = min(4, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function create(): void
    {
        $this->errorMessage = null;
        $pressing = $this->pressing();
        $customer = Customer::findOrFail($this->selectedCustomerId);

        try {
            $order = (new CreateOrderAction)->handle(
                pressing: $pressing,
                customer: $customer,
                items: $this->items,
                expectedAt: $this->expectedAt,
                notes: $this->notes ?: null,
            );
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->redirect(route('orders.show', $order), navigate: false);
    }

    private function pressing(): Pressing
    {
        return auth()->user()->currentPressing();
    }

    #[Layout('layouts.dashboard', ['active' => 'orders', 'title' => 'Nouvelle commande'])]
    public function render()
    {
        $pressing = $this->pressing();

        $clients = collect();
        if ($this->clientSearch !== '') {
            $term = $this->clientSearch;
            $clients = $pressing->customers()
                ->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"))
                ->limit(6)->get();
        } else {
            $clients = $pressing->customers()->orderByDesc('pressing_customers.joined_at')->limit(6)->get();
        }

        $selectedCustomer = $this->selectedCustomerId ? Customer::find($this->selectedCustomerId) : null;
        $services = $pressing->services()->where('is_active', true)->orderBy('name')->get();

        return view('livewire.orders.create', [
            'clients' => $clients,
            'selectedCustomer' => $selectedCustomer,
            'services' => $services,
        ]);
    }
}
