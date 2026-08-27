<?php

namespace App\Livewire\Orders;

use App\Actions\Orders\CreateOrderAction;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\ServiceVariant;
use Illuminate\Support\Collection;
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
    /** @var array<int, array{service_id: ?int, service_variant_id: ?int, name: string, color: ?string, unit_price_fcfa: int, quantity: int}> */
    public array $items = [];

    // Étape 2 — formulaire d'ajout d'un article (service + variante + couleur)
    public string $pickerService = '';

    public string $pickerVariant = '';

    public string $pickerCustomName = '';

    public string $pickerCustomPrice = '';

    public string $pickerColor = '';

    public int $pickerQuantity = 1;

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
        abort_unless($this->pressing()->customers()->where('customers.id', $customerId)->exists(), 403);

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

    public function updatedPickerService(): void
    {
        $this->pickerVariant = '';
        $this->pickerCustomName = '';
        $this->pickerCustomPrice = '';
    }

    public function updatedPickerVariant(): void
    {
        $this->pickerCustomName = '';
        $this->pickerCustomPrice = '';
    }

    /**
     * Ajoute un article à la commande — Service + variante (chacune à son
     * propre tarif, ex. Chemise manche courte vs manche longue) et une
     * couleur libre, ou un article entièrement personnalisé via "Autre".
     */
    public function addPickedItem(): void
    {
        $this->validate([
            'pickerService' => ['required', 'string'],
            'pickerColor' => ['nullable', 'string', 'max:50'],
            'pickerQuantity' => ['required', 'integer', 'min:1'],
        ]);

        $serviceId = null;
        $variantId = null;

        if ($this->pickerService === 'other') {
            $name = $this->validatedCustomName();
            $price = $this->validatedCustomPrice();
        } else {
            $service = $this->pressing()->services()->where('is_active', true)->findOrFail((int) $this->pickerService);
            $serviceId = $service->id;
            $activeVariants = $service->variants()->where('is_active', true)->get();

            if ($this->pickerVariant === 'other') {
                $name = $service->name.' · '.$this->validatedCustomName();
                $price = $this->validatedCustomPrice();
            } elseif ($this->pickerVariant !== '') {
                $variant = $activeVariants->firstWhere('id', (int) $this->pickerVariant);
                abort_if($variant === null, 404);
                $variantId = $variant->id;
                $name = $service->name.' · '.$variant->name;
                $price = $variant->price_fcfa;
            } elseif ($activeVariants->isNotEmpty()) {
                $this->addError('pickerVariant', 'Choisissez une variante pour ce service.');

                return;
            } else {
                $name = $service->name;
                $price = $service->price_fcfa;
            }
        }

        $color = $this->pickerColor !== '' ? $this->pickerColor : null;
        $displayName = $color !== null ? "{$name} · {$color}" : $name;

        foreach ($this->items as $i => $item) {
            if ($item['service_id'] === $serviceId && $item['service_variant_id'] === $variantId && $item['name'] === $displayName) {
                $this->items[$i]['quantity'] += $this->pickerQuantity;
                $this->resetPicker();

                return;
            }
        }

        $this->items[] = [
            'service_id' => $serviceId,
            'service_variant_id' => $variantId,
            'name' => $displayName,
            'color' => $color,
            'unit_price_fcfa' => $price,
            'quantity' => $this->pickerQuantity,
        ];

        $this->resetPicker();
    }

    private function validatedCustomName(): string
    {
        $this->validate(['pickerCustomName' => ['required', 'string', 'max:150']]);

        return $this->pickerCustomName;
    }

    private function validatedCustomPrice(): int
    {
        $this->validate(['pickerCustomPrice' => ['required', 'integer', 'min:0']]);

        return (int) $this->pickerCustomPrice;
    }

    private function resetPicker(): void
    {
        $this->reset(['pickerService', 'pickerVariant', 'pickerCustomName', 'pickerCustomPrice', 'pickerColor']);
        $this->pickerQuantity = 1;
    }

    /** @return Collection<int, ServiceVariant> */
    public function getPickerServiceVariantsProperty()
    {
        if ($this->pickerService === '' || $this->pickerService === 'other') {
            return collect();
        }

        $service = Service::find((int) $this->pickerService);

        return $service?->variants()->where('is_active', true)->orderBy('name')->get() ?? collect();
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
        $customer = $pressing->customers()->findOrFail($this->selectedCustomerId);

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

        $selectedCustomer = $this->selectedCustomerId ? $pressing->customers()->find($this->selectedCustomerId) : null;
        $services = $pressing->services()
            ->where('is_active', true)
            ->withCount(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('livewire.orders.create', [
            'clients' => $clients,
            'selectedCustomer' => $selectedCustomer,
            'services' => $services,
        ]);
    }
}
