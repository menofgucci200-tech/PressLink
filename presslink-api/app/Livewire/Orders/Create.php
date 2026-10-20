<?php

namespace App\Livewire\Orders;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\Prestation;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Rules\IvorianPhone;
use App\Support\ColorPalette;
use App\Support\PhoneNumber;
use App\Support\ReceiptLinks;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Comptoir : la commande se crée sur un seul écran. Le client, le service et
 * les pièces à gauche ; le ticket, son total et le bouton « Valider » toujours
 * visibles. Cas le plus court : client → pièce → Valider (3 touchers).
 *
 * La commande en cours est gardée en brouillon (par pressing) : un employé
 * interrompu la retrouve en revenant sur l'écran.
 */
class Create extends Component
{
    /** Quantité maximale d'une ligne (garde-fou contre une faute de frappe). */
    public const MAX_QUANTITY = 999;

    // Client
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

    // Services et pièces
    /** @var array<int, array{service_id: ?int, service_variant_id: ?int, prestation_id: ?int, prestation_name: ?string, base_name: string, name: string, color: ?string, unit_price_fcfa: int, quantity: int}> */
    public array $items = [];

    /** Service (Repassage, Nettoyage…) dont on choisit les pièces. */
    public ?int $prestationId = null;

    /** « Pinceau » : couleur donnée aux prochaines pièces ajoutées (facultative). */
    public ?string $activeColor = null;

    /** Pièce à variantes dont on attend le choix de la variante. */
    public ?int $variantPieceId = null;

    public bool $showCustomForm = false;

    public string $customName = '';

    public string $customPrice = '';

    // Détails et paiement (préremplis : prête à J+2, payée au retrait)
    public string $expectedAt = '';

    public string $notes = '';

    /** Déjà tout payé (full), acompte (partial) ou tout au retrait (later). */
    public string $paymentChoice = 'later';

    public string $depositAmount = '';

    public string $paymentMethod = 'especes';

    public ?string $errorMessage = null;

    /** Brouillon repris à l'ouverture de l'écran. */
    public bool $draftRestored = false;

    /** @var array{id: int, number: string, customer: string, total: int, pdf: string, share: array{url: string, text: string, file: string, phone: string}}|null Dernière commande validée (bandeau « Imprimer le reçu »). */
    public ?array $lastCreated = null;

    /** Champs gardés en brouillon. */
    private const DRAFT_FIELDS = ['selectedCustomerId', 'items', 'prestationId', 'activeColor', 'expectedAt', 'notes', 'paymentChoice', 'depositAmount', 'paymentMethod'];

    public function mount(): void
    {
        $this->expectedAt = $this->defaultExpectedAt();

        $draft = session($this->draftKey());
        if (is_array($draft) && ($draft['items'] ?? []) !== []) {
            foreach (self::DRAFT_FIELDS as $field) {
                if (array_key_exists($field, $draft)) {
                    $this->{$field} = $draft[$field];
                }
            }
            $this->draftRestored = true;

            if ($this->selectedCustomerId !== null && ! $this->pressing()->customers()->where('customers.id', $this->selectedCustomerId)->where('customers.is_active', true)->exists()) {
                $this->selectedCustomerId = null;
            }
        }

        if ($this->prestationId === null || $this->selectedPrestation() === null) {
            $this->prestationId = $this->defaultPrestationId();
        }
    }

    /** Sauvegarde du brouillon à chaque échange avec le serveur. */
    public function dehydrate(): void
    {
        if ($this->items === [] && $this->selectedCustomerId === null) {
            session()->forget($this->draftKey());

            return;
        }

        session()->put($this->draftKey(), collect(self::DRAFT_FIELDS)->mapWithKeys(fn ($field) => [$field => $this->{$field}])->all());
    }

    /** Vide le ticket pour repartir de zéro. */
    public function clearDraft(): void
    {
        $this->resetOrder();
        $this->dispatch('comptoir-focus', target: 'client');
    }

    // ── Client ────────────────────────────────────────────────────────────

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
        $this->lastCreated = null;
        $this->dispatch('comptoir-focus', target: 'pieces');
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
        $this->reset(['newFirstName', 'newLastName', 'newPhone', 'knownCustomerId', 'knownCustomerName', 'clientSearch']);

        if ($customer->is_active === false) {
            $this->dispatch('toast', type: 'error', message: CreateOrderAction::INACTIVE_CUSTOMER_MESSAGE);

            return;
        }

        $this->selectedCustomerId = $customer->id;
        $this->lastCreated = null;
        $this->dispatch('comptoir-focus', target: 'pieces');
    }

    /**
     * « Refaire sa dernière commande » : recopie les pièces de la dernière
     * commande du client, aux prix actuels du pressing.
     */
    public function repeatLastOrder(): void
    {
        $order = $this->lastOrderOf($this->selectedCustomerId);
        if ($order === null) {
            return;
        }

        $pressing = $this->pressing();
        $pieces = $pressing->services()->where('is_active', true)->with('prices')->get()->keyBy('id');
        $variants = ServiceVariant::query()->whereIn('service_id', $pieces->keys())->where('is_active', true)->get()->keyBy('id');
        $prestations = $pressing->prestations()->where('is_active', true)->get()->keyBy('id');

        foreach ($order->items as $item) {
            $piece = $item->service_id !== null ? $pieces->get($item->service_id) : null;
            $variant = $item->service_variant_id !== null ? $variants->get($item->service_variant_id) : null;
            $prestation = $item->prestation_id !== null ? $prestations->get($item->prestation_id) : null;

            // Pièce, variante ou service retirés depuis : la ligne n'est pas reprise.
            if (($item->service_id !== null && $piece === null)
                || ($item->service_variant_id !== null && $variant === null)
                || ($item->prestation_id !== null && $prestation === null)) {
                continue;
            }

            $price = ($piece !== null && $prestation !== null)
                ? ($piece->priceFor($prestation, $variant) ?? $item->unit_price_fcfa)
                : $item->unit_price_fcfa;
            $baseName = $piece !== null
                ? $piece->name.($variant !== null ? ' · '.$variant->name : '')
                : $this->baseNameOf($item->name, $item->prestation_name, $item->color);

            $this->addLine($piece?->id, $variant?->id, $prestation?->id ?? $item->prestation_id, $prestation?->name ?? $item->prestation_name, $baseName, $price, $item->color, $item->quantity);
        }

        $this->dispatch('toast', type: 'success', message: 'Dernière commande recopiée : ajustez si besoin.');
    }

    // ── Services et pièces ────────────────────────────────────────────────

    public function selectPrestation(int $prestationId): void
    {
        $prestation = $this->pressing()->prestations()->where('is_active', true)->find($prestationId);
        abort_if($prestation === null, 404);

        $this->prestationId = $prestation->id;
        $this->variantPieceId = null;
        $this->showCustomForm = false;
    }

    /** Pinceau : la couleur choisie s'applique aux prochaines pièces ajoutées. Toucher la même couleur l'enlève. */
    public function setActiveColor(string $color): void
    {
        $color = trim(mb_substr($color, 0, 50));
        $this->activeColor = ($color === '' || $color === $this->activeColor) ? null : $color;
    }

    public function setActiveColorFromPicker(string $hex): void
    {
        $name = ColorPalette::nameFor($hex);

        if ($name !== null) {
            $this->activeColor = $name;
        }
    }

    /**
     * Touche sur une pièce : ajoute 1 au service choisi, dans la couleur du
     * pinceau. Une pièce à variantes (manche courte / longue…) demande d'abord laquelle.
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

        $this->addLine($piece->id, null, $prestation->id, $prestation->name, $piece->name, $price, $this->activeColor);
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

        $this->addLine($piece->id, $variant->id, $prestation->id, $prestation->name, $piece->name.' · '.$variant->name, $price, $this->activeColor);
        $this->variantPieceId = null;
    }

    /**
     * Nombre tapé sur le badge d'une pièce (« 12 chemises ») : fixe la
     * quantité de cette pièce, pour le service et la couleur en cours.
     */
    public function setPieceQuantity(int $pieceId, int $quantity): void
    {
        $prestation = $this->selectedPrestation();
        if ($prestation === null) {
            return;
        }

        $piece = $this->pressing()->services()->where('is_active', true)->find($pieceId);
        abort_if($piece === null, 404);
        $price = $piece->priceFor($prestation);
        if ($price === null) {
            return;
        }

        $quantity = max(0, min(self::MAX_QUANTITY, $quantity));
        $index = $this->findLine($piece->id, null, $prestation->id, $piece->name, $price, $this->activeColor);

        if ($index === null) {
            if ($quantity > 0) {
                $this->addLine($piece->id, null, $prestation->id, $prestation->name, $piece->name, $price, $this->activeColor, $quantity);
            }

            return;
        }

        $this->setItemQuantity($index, $quantity);
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

        $this->addLine(null, null, $prestation->id, $prestation->name, trim($this->customName), (int) $this->customPrice, $this->activeColor);
        $this->reset(['customName', 'customPrice', 'showCustomForm']);
    }

    /** Ajoute des unités à la ligne identique (même pièce, service, prix et couleur), ou crée la ligne. */
    private function addLine(?int $pieceId, ?int $variantId, ?int $prestationId, ?string $prestationName, string $baseName, int $price, ?string $color, int $quantity = 1): void
    {
        $index = $this->findLine($pieceId, $variantId, $prestationId, $baseName, $price, $color);

        if ($index !== null) {
            $this->items[$index]['quantity'] = min(self::MAX_QUANTITY, $this->items[$index]['quantity'] + $quantity);

            return;
        }

        $this->items[] = [
            'service_id' => $pieceId,
            'service_variant_id' => $variantId,
            'prestation_id' => $prestationId,
            'prestation_name' => $prestationName,
            'base_name' => $baseName,
            'name' => $this->displayName($baseName, $prestationName, $color),
            'color' => $color,
            'unit_price_fcfa' => $price,
            'quantity' => min(self::MAX_QUANTITY, $quantity),
        ];
    }

    private function findLine(?int $pieceId, ?int $variantId, ?int $prestationId, string $baseName, int $price, ?string $color): ?int
    {
        foreach ($this->items as $i => $item) {
            if ($item['service_id'] === $pieceId
                && $item['service_variant_id'] === $variantId
                && $item['prestation_id'] === $prestationId
                && $item['base_name'] === $baseName
                && $item['unit_price_fcfa'] === $price
                && $item['color'] === $color) {
                return $i;
            }
        }

        return null;
    }

    private function displayName(string $baseName, ?string $prestationName, ?string $color): string
    {
        return collect([$baseName, $prestationName, $color])->filter(fn ($part) => filled($part))->implode(' · ');
    }

    /** Nom de la pièce seule, à partir du nom affiché « Pièce · Service · Couleur ». */
    private function baseNameOf(string $name, ?string $prestationName, ?string $color): string
    {
        foreach ([$color, $prestationName] as $suffix) {
            if (filled($suffix) && str_ends_with($name, ' · '.$suffix)) {
                $name = mb_substr($name, 0, mb_strlen($name) - mb_strlen(' · '.$suffix));
            }
        }

        return $name;
    }

    /** Couleur (facultative) d'une ligne du ticket : « Chemise · Repassage · Bleu ». */
    public function setItemColor(int $index, string $color): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $color = trim(mb_substr($color, 0, 50));
        $this->items[$index]['color'] = $color !== '' ? $color : null;
        $this->items[$index]['name'] = $this->displayName($this->items[$index]['base_name'], $this->items[$index]['prestation_name'], $this->items[$index]['color']);
        $this->mergeIdenticalLines();
    }

    /** Couleur choisie au sélecteur : ramenée au nom de couleur le plus proche. */
    public function setItemColorFromPicker(int $index, string $hex): void
    {
        $name = ColorPalette::nameFor($hex);

        if ($name !== null) {
            $this->setItemColor($index, $name);
        }
    }

    /** Deux lignes devenues identiques (même couleur…) n'en font plus qu'une. */
    private function mergeIdenticalLines(): void
    {
        $merged = [];

        foreach ($this->items as $item) {
            $key = implode('|', [$item['service_id'], $item['service_variant_id'], $item['prestation_id'], $item['base_name'], $item['unit_price_fcfa'], $item['color']]);

            if (isset($merged[$key])) {
                $merged[$key]['quantity'] = min(self::MAX_QUANTITY, $merged[$key]['quantity'] + $item['quantity']);
            } else {
                $merged[$key] = $item;
            }
        }

        $this->items = array_values($merged);
    }

    /** Retire une unité de la pièce (la dernière ligne ajoutée, hors variantes). */
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

    /** Quantité déjà ajoutée d'une pièce (toutes variantes et couleurs confondues) pour le service choisi. */
    public function quantityOfPiece(int $pieceId): int
    {
        return (int) collect($this->items)
            ->where('service_id', $pieceId)
            ->where('prestation_id', $this->prestationId)
            ->sum('quantity');
    }

    public function incrementItem(int $index): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['quantity'] = min(self::MAX_QUANTITY, $this->items[$index]['quantity'] + 1);
        }
    }

    public function decrementItem(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->setItemQuantity($index, $this->items[$index]['quantity'] - 1);
    }

    /** Quantité tapée directement dans le ticket ; 0 retire la ligne. */
    public function setItemQuantity(int $index, int $quantity): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        if ($quantity <= 0) {
            $this->removeItem($index);

            return;
        }

        $this->items[$index]['quantity'] = min(self::MAX_QUANTITY, $quantity);
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function getTotalProperty(): int
    {
        return collect($this->items)->sum(fn ($item) => $item['unit_price_fcfa'] * $item['quantity']);
    }

    public function getPieceCountProperty(): int
    {
        return (int) collect($this->items)->sum('quantity');
    }

    // ── Paiement et validation ────────────────────────────────────────────

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

    private function validatePayment(): void
    {
        if ($this->total === 0) {
            return;
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
                'depositAmount.max' => 'Pour un acompte, le montant doit rester inférieur au total ('.number_format($this->total, 0, ',', ' ').' F). Sinon, choisissez « Payée ».',
            ]);
        }
    }

    /**
     * « Valider la commande ». S'il manque le client ou les pièces, le bouton
     * guide vers ce qui manque au lieu de rester grisé.
     */
    public function create(): void
    {
        $this->errorMessage = null;

        if ($this->selectedCustomerId === null) {
            $this->dispatch('toast', type: 'error', message: 'Choisissez d\'abord le client.');
            $this->dispatch('comptoir-focus', target: 'client');

            return;
        }

        if ($this->items === []) {
            $this->dispatch('toast', type: 'error', message: 'Ajoutez au moins une pièce.');
            $this->dispatch('comptoir-focus', target: 'pieces');

            return;
        }

        $pressing = $this->pressing();
        $customer = $pressing->customers()->find($this->selectedCustomerId);
        abort_if($customer === null, 403);

        $this->validatePayment();

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

        // L'écran se remet à zéro, prêt pour le client suivant ; le bandeau
        // propose d'imprimer le reçu de la commande qui vient d'être validée.
        $this->resetOrder();
        $this->lastCreated = [
            'id' => $order->id,
            'number' => $order->order_number,
            'customer' => $customer->fullName(),
            'total' => (int) $order->total_fcfa,
            'pdf' => route('orders.receipt', ['order' => $order, 'format' => 'pdf']),
            'share' => ['url' => ReceiptLinks::order($order), 'text' => ReceiptLinks::orderShareText($order), 'file' => ReceiptLinks::orderFileName($order), 'phone' => $customer->phone],
        ];
        $this->dispatch('comptoir-focus', target: 'client');
    }

    private function resetOrder(): void
    {
        $this->reset(['clientSearch', 'selectedCustomerId', 'showNewClientForm', 'newFirstName', 'newLastName', 'newPhone', 'knownCustomerId', 'knownCustomerName',
            'items', 'activeColor', 'variantPieceId', 'showCustomForm', 'customName', 'customPrice',
            'notes', 'paymentChoice', 'depositAmount', 'paymentMethod', 'errorMessage', 'draftRestored', 'lastCreated']);
        $this->expectedAt = $this->defaultExpectedAt();
        session()->forget($this->draftKey());
    }

    // ── Données de l'écran ────────────────────────────────────────────────

    private function pressing(): Pressing
    {
        return auth()->user()->currentPressing();
    }

    private function draftKey(): string
    {
        return 'order_draft.'.$this->pressing()->id;
    }

    private function defaultExpectedAt(): string
    {
        return now()->addDays(2)->format('Y-m-d');
    }

    /** Le service le plus demandé ces 90 derniers jours (sinon le premier de la liste). */
    private function defaultPrestationId(): ?int
    {
        $prestations = $this->pressing()->prestations()->where('is_active', true)->get();
        $usage = $this->usage()->groupBy('prestation_id')->map(fn ($rows) => $rows->sum('total'));

        return $prestations->sortByDesc(fn ($prestation) => $usage->get($prestation->id, 0))->first()?->id;
    }

    /**
     * Pièces commandées ces 90 derniers jours, par service : sert à proposer
     * en premier ce que le pressing fait le plus.
     *
     * @return Collection<int, object{prestation_id: ?int, service_id: int, total: int}>
     */
    private function usage(): Collection
    {
        return once(fn () => DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.pressing_id', $this->pressing()->id)
            ->where('orders.created_at', '>=', now()->subDays(90))
            ->whereNotNull('order_items.service_id')
            ->groupBy('order_items.prestation_id', 'order_items.service_id')
            ->selectRaw('order_items.prestation_id, order_items.service_id, SUM(order_items.quantity) as total')
            ->get());
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

    private function lastOrderOf(?int $customerId): ?Order
    {
        if ($customerId === null) {
            return null;
        }

        return Order::query()
            ->where('pressing_id', $this->pressing()->id)
            ->where('customer_id', $customerId)
            ->with('items')
            ->latest('id')
            ->first();
    }

    #[Layout('layouts.dashboard', ['active' => 'orders', 'title' => 'Nouvelle commande'])]
    public function render()
    {
        $pressing = $this->pressing();

        $selectedCustomer = $this->selectedCustomerId ? $pressing->customers()->find($this->selectedCustomerId) : null;
        $clients = $selectedCustomer === null ? $this->searchCustomers($pressing) : collect();
        $prestations = $pressing->prestations()->where('is_active', true)->get();
        $selectedPrestation = $prestations->firstWhere('id', $this->prestationId);

        // Pièces proposées pour le service choisi (celles qui y ont un prix),
        // les plus demandées d'abord.
        $pieces = collect();
        if ($selectedPrestation !== null) {
            $usage = $this->usage()->where('prestation_id', $selectedPrestation->id)->pluck('total', 'service_id');
            $pieces = $pressing->services()
                ->where('is_active', true)
                ->whereHas('prices', fn ($q) => $q->where('prestation_id', $selectedPrestation->id))
                ->with([
                    'variants' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
                    'prices' => fn ($q) => $q->where('prestation_id', $selectedPrestation->id),
                ])
                ->orderBy('name')
                ->get()
                ->sortByDesc(fn (Service $piece) => (int) $usage->get($piece->id, 0))
                ->values();
        }

        $lastOrder = $selectedCustomer !== null ? $this->lastOrderOf($selectedCustomer->id) : null;

        return view('livewire.orders.create', [
            'clients' => $clients,
            'selectedCustomer' => $selectedCustomer,
            'prestations' => $prestations,
            'selectedPrestation' => $selectedPrestation,
            'pieces' => $pieces,
            'lastOrder' => $lastOrder,
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
