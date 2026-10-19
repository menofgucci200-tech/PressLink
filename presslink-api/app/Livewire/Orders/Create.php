<?php

namespace App\Livewire\Orders;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
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

    /**
     * Numéro déjà enregistré (par un autre pressing ou par le client) : on
     * montre le nom connu et le staff confirme qu'il s'agit bien de son
     * client, ce qui rattrape aussi une erreur de saisie du numéro.
     */
    public ?int $knownCustomerId = null;

    public string $knownCustomerName = '';

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

    // Étape 3 — paiement : déjà tout payé, acompte, ou tout au retrait.
    public string $paymentChoice = '';

    public string $depositAmount = '';

    public string $paymentMethod = 'especes';

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->expectedAt = now()->addDays(2)->format('Y-m-d');
    }

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
    }

    public function createAndPickCustomer(): void
    {
        $pressing = $this->pressing();
        $this->newPhone = PhoneNumber::normalize($this->newPhone);
        $this->reset(['knownCustomerId', 'knownCustomerName']);

        $this->validate(['newPhone' => ['required', 'string', new IvorianPhone]]);

        $known = Customer::where('phone', $this->newPhone)->first();

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
        $customer = Customer::findOrFail($this->knownCustomerId);
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
        $this->reset(['newFirstName', 'newLastName', 'newPhone', 'knownCustomerId', 'knownCustomerName']);

        if ($customer->is_active === false) {
            $this->dispatch('toast', type: 'error', message: CreateOrderAction::INACTIVE_CUSTOMER_MESSAGE);

            return;
        }

        $this->selectedCustomerId = $customer->id;
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

    /** Montant encaissé au dépôt, selon le choix de paiement. */
    public function getAmountPaidNowProperty(): int
    {
        return match ($this->paymentChoice) {
            'full' => $this->total,
            'partial' => max(0, (int) $this->depositAmount),
            default => 0,
        };
    }

    public function updatedPaymentChoice(): void
    {
        $this->resetErrorBag(['paymentChoice', 'depositAmount']);
        if ($this->paymentChoice !== 'partial') {
            $this->depositAmount = '';
        }
    }

    private function validatePayment(): bool
    {
        if ($this->total === 0) {
            return true;
        }

        $this->validate([
            'paymentChoice' => ['required', 'in:full,partial,later'],
            'paymentMethod' => ['required', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
        ], [
            'paymentChoice.required' => 'Indiquez si le client a déjà payé.',
        ]);

        if ($this->paymentChoice === 'partial') {
            $this->validate([
                'depositAmount' => ['required', 'integer', 'min:1', 'max:'.($this->total - 1)],
            ], [
                'depositAmount.required' => 'Indiquez le montant versé par le client.',
                'depositAmount.integer' => 'Le montant versé doit être un nombre entier de francs.',
                'depositAmount.min' => 'Le montant versé doit être supérieur à 0 F.',
                'depositAmount.max' => 'Pour un acompte, le montant doit rester inférieur au total ('.number_format($this->total, 0, ',', ' ').' F). Sinon, choisissez « Déjà payée ».',
            ]);
        }

        return true;
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

        if ($this->step === 3) {
            $this->validatePayment();
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
        $customer = $pressing->customers()->find($this->selectedCustomerId);
        abort_if($customer === null, 403);

        // L'étape 3 impose un choix ; sans choix (appel direct), rien n'est
        // considéré comme payé : tout reste à régler au retrait.
        if ($this->paymentChoice !== '') {
            $this->validatePayment();
        }

        try {
            $order = (new CreateOrderAction)->handle(
                pressing: $pressing,
                customer: $customer,
                items: $this->items,
                expectedAt: $this->expectedAt,
                notes: $this->notes ?: null,
                amountPaidFcfa: $this->amountPaidNow,
                paymentMethod: PaymentMethod::from($this->paymentMethod),
            );
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        // La fiche propose aussitôt d'imprimer le reçu à remettre au client.
        session()->flash('order_created', true);
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
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
