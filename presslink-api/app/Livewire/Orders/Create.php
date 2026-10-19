<?php

namespace App\Livewire\Orders;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Prestation;
use App\Models\Service;
use App\Models\ServiceVariant;
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

    // Étape 2 — services et pièces
    /** @var array<int, array{service_id: ?int, service_variant_id: ?int, prestation_id: ?int, prestation_name: ?string, base_name: string, name: string, color: ?string, unit_price_fcfa: int, quantity: int}> */
    public array $items = [];

    /** Service (Repassage, Nettoyage…) dont on choisit les pièces. */
    public ?int $prestationId = null;

    /** Pièce à variantes dont on attend le choix de la variante. */
    public ?int $variantPieceId = null;

    public bool $showCustomForm = false;

    public string $customName = '';

    public string $customPrice = '';

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
        $this->validate([
            'newFirstName' => ['required', 'string', 'max:100'],
            'newLastName' => ['required', 'string', 'max:100'],
            'newPhone' => ['required', 'string', PhoneNumber::RULE],
        ]);

        $customer = DB::transaction(function () use ($pressing) {
            $customer = Customer::firstOrCreate(
                ['phone' => $this->newPhone],
                [
                    'first_name' => $this->newFirstName,
                    'last_name' => $this->newLastName,
                    'password' => Customer::DEFAULT_WALK_IN_PASSWORD,
                    'must_change_password' => true,
                    'phone_verified_at' => now(),
                ],
            );

            $pressing->customers()->syncWithoutDetaching([$customer->id => ['joined_at' => now()]]);

            return $customer;
        });

        $this->showNewClientForm = false;
        $this->reset(['newFirstName', 'newLastName', 'newPhone']);

        if ($customer->is_active === false) {
            $this->dispatch('toast', type: 'error', message: CreateOrderAction::INACTIVE_CUSTOMER_MESSAGE);

            return;
        }

        $this->selectedCustomerId = $customer->id;
    }

    public function selectPrestation(int $prestationId): void
    {
        $prestation = $this->pressing()->prestations()->where('is_active', true)->find($prestationId);
        abort_if($prestation === null, 404);

        $this->prestationId = $prestation->id;
        $this->variantPieceId = null;
        $this->showCustomForm = false;
    }

    /**
     * Touche sur une pièce : ajoute 1 au service choisi. Une pièce à variantes
     * (Chemise manche courte / longue…) demande d'abord laquelle.
     */
    public function addPiece(int $pieceId): void
    {
        $prestation = $this->selectedPrestation();
        if ($prestation === null) {
            return;
        }

        $piece = $this->pressing()->services()->where('is_active', true)->find($pieceId);
        abort_if($piece === null, 404);

        if ($this->pricedVariants($piece, $prestation)->isNotEmpty()) {
            $this->variantPieceId = $piece->id;

            return;
        }

        $price = $piece->priceFor($prestation);
        if ($price === null) {
            $this->dispatch('toast', type: 'error', message: "« {$piece->name} » n'est pas proposé pour le service {$prestation->name}.");

            return;
        }

        $this->pushItem($piece, null, $prestation, $piece->name, $price);
    }

    public function addVariant(int $pieceId, int $variantId): void
    {
        $prestation = $this->selectedPrestation();
        if ($prestation === null) {
            return;
        }

        $piece = $this->pressing()->services()->where('is_active', true)->find($pieceId);
        $variant = $piece?->variants()->where('is_active', true)->find($variantId);
        abort_if($piece === null || $variant === null, 404);
        $price = $piece->priceFor($prestation, $variant);
        abort_if($price === null, 404);

        $this->pushItem($piece, $variant, $prestation, $piece->name.' · '.$variant->name, $price);
        $this->variantPieceId = null;
    }

    /** Pièce hors catalogue (« Autre pièce »), au prix saisi, pour le service choisi. */
    public function addCustomPiece(): void
    {
        $prestation = $this->selectedPrestation();
        if ($prestation === null) {
            return;
        }

        $this->validate([
            'customName' => ['required', 'string', 'max:150'],
            'customPrice' => ['required', 'integer', 'min:0'],
        ], [
            'customName.required' => 'Indiquez le nom de la pièce.',
            'customPrice.required' => 'Indiquez le prix.',
            'customPrice.integer' => 'Le prix doit être un nombre entier de francs.',
        ]);

        $this->pushItem(null, null, $prestation, trim($this->customName), (int) $this->customPrice);
        $this->reset(['customName', 'customPrice', 'showCustomForm']);
    }

    private function pushItem(?Service $piece, ?ServiceVariant $variant, Prestation $prestation, string $baseName, int $price): void
    {
        foreach ($this->items as $i => $item) {
            if ($item['service_id'] === $piece?->id
                && $item['service_variant_id'] === $variant?->id
                && $item['prestation_id'] === $prestation->id
                && $item['base_name'] === $baseName
                && $item['unit_price_fcfa'] === $price
                && $item['color'] === null) {
                $this->items[$i]['quantity']++;

                return;
            }
        }

        $this->items[] = [
            'service_id' => $piece?->id,
            'service_variant_id' => $variant?->id,
            'prestation_id' => $prestation->id,
            'prestation_name' => $prestation->name,
            'base_name' => $baseName,
            'name' => $this->displayName($baseName, $prestation->name, null),
            'color' => null,
            'unit_price_fcfa' => $price,
            'quantity' => 1,
        ];
    }

    private function displayName(string $baseName, ?string $prestationName, ?string $color): string
    {
        return collect([$baseName, $prestationName, $color])->filter(fn ($part) => filled($part))->implode(' · ');
    }

    /** Couleur (facultative) d'une ligne : « Chemise · Repassage · Bleu ». */
    public function setItemColor(int $index, string $color): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $color = trim(mb_substr($color, 0, 50));
        $this->items[$index]['color'] = $color !== '' ? $color : null;
        $this->items[$index]['name'] = $this->displayName($this->items[$index]['base_name'], $this->items[$index]['prestation_name'], $this->items[$index]['color']);
    }

    /** Retire une unité de la pièce (hors variantes : voir la liste de la commande). */
    public function decrementPiece(int $pieceId): void
    {
        foreach (array_reverse(array_keys($this->items)) as $i) {
            if ($this->items[$i]['service_id'] === $pieceId && $this->items[$i]['prestation_id'] === $this->prestationId && $this->items[$i]['service_variant_id'] === null) {
                $this->decrementItem($i);

                return;
            }
        }
    }

    private function selectedPrestation(): ?Prestation
    {
        return $this->prestationId === null
            ? null
            : $this->pressing()->prestations()->where('is_active', true)->find($this->prestationId);
    }

    /**
     * Variantes actives de la pièce proposées pour ce service (celles qui y ont un prix).
     *
     * @return Collection<int, ServiceVariant>
     */
    private function pricedVariants(Service $piece, Prestation $prestation): Collection
    {
        $pricedIds = $piece->prices()->where('prestation_id', $prestation->id)->whereNotNull('service_variant_id')->pluck('service_variant_id');

        return $piece->variants()->where('is_active', true)->whereIn('id', $pricedIds)->orderBy('name')->get();
    }

    /** Quantité déjà ajoutée d'une pièce (toutes variantes confondues) pour le service choisi. */
    public function quantityOfPiece(int $pieceId): int
    {
        return (int) collect($this->items)
            ->where('service_id', $pieceId)
            ->where('prestation_id', $this->prestationId)
            ->sum('quantity');
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
                items: array_map(fn (array $item) => collect($item)->except('base_name')->all(), $this->items),
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
        $prestations = $pressing->prestations()->where('is_active', true)->get();
        $selectedPrestation = $prestations->firstWhere('id', $this->prestationId);

        // Pièces proposées pour le service choisi (celles qui y ont un prix).
        $pieces = collect();
        if ($selectedPrestation !== null) {
            $pieces = $pressing->services()
                ->where('is_active', true)
                ->whereHas('prices', fn ($q) => $q->where('prestation_id', $selectedPrestation->id))
                ->with([
                    'variants' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
                    'prices' => fn ($q) => $q->where('prestation_id', $selectedPrestation->id),
                ])
                ->orderBy('name')
                ->get();
        }

        return view('livewire.orders.create', [
            'clients' => $clients,
            'selectedCustomer' => $selectedCustomer,
            'prestations' => $prestations,
            'selectedPrestation' => $selectedPrestation,
            'pieces' => $pieces,
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
