<?php

namespace App\Livewire\Orders;

use App\Actions\Orders\CreateOrderAction;
use App\Actions\Orders\OrderPhotos;
use App\Enums\Permission;
use App\Livewire\Concerns\ManagesOrderPhotos;
use App\Livewire\Concerns\PicksOrderCustomer;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\Pressing;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Dépôt rapide, pour les grosses commandes : le client, le nombre de pièces,
 * et le numéro à lui remettre. Vient ensuite la prise de photos ; le détail et
 * les prix seront saisis plus tard, au calme (voir Create, `completingOrderId`).
 */
class QuickDeposit extends Component
{
    use ManagesOrderPhotos, PicksOrderCustomer, ShowsToasts;

    /** Garde-fou contre une faute de frappe (colonne sur 2 octets, bien au-delà du réel). */
    public const MAX_PIECES = 9999;

    public string $declaredPieces = '';

    public string $expectedAt = '';

    public string $notes = '';

    public ?string $errorMessage = null;

    /** Dépôt qui vient d'être enregistré : l'écran passe aux photos. */
    #[Locked]
    public ?int $depositId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->may(Permission::OrdersCreate), 403);

        $this->expectedAt = $this->defaultExpectedAt();
    }

    public function save(CreateOrderAction $createOrder): void
    {
        // Double toucher sur un réseau lent : un seul dépôt.
        if ($this->depositId !== null) {
            return;
        }

        $this->errorMessage = null;
        $pressing = $this->pressing();

        if (! $pressing->acceptsCustomers()) {
            $this->errorMessage = $pressing->closedMessage();

            return;
        }

        if ($this->selectedCustomerId === null) {
            $this->addError('clientSearch', 'Choisissez d\'abord le client.');

            return;
        }

        $this->validate([
            'declaredPieces' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PIECES],
            'expectedAt' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'declaredPieces.required' => 'Indiquez le nombre de pièces.',
            'declaredPieces.integer' => 'Le nombre de pièces doit être un nombre entier.',
            'declaredPieces.min' => 'Comptez au moins une pièce.',
            'declaredPieces.max' => 'Ce nombre de pièces paraît trop grand : vérifiez la saisie.',
            'expectedAt.date_format' => 'La date « Prête le » n\'est pas valide.',
            'expectedAt.after_or_equal' => 'La date « Prête le » ne peut pas être passée.',
            'notes.max' => 'La remarque est trop longue (1 000 caractères au plus).',
        ]);

        $customer = $pressing->customers()->find($this->selectedCustomerId);
        abort_if($customer === null, 403);

        try {
            $deposit = $createOrder->quickDeposit($pressing, $customer, (int) $this->declaredPieces, $this->expectedAt ?: null, trim($this->notes) ?: null);
        } catch (QueryException $e) {
            report($e);
            $this->errorMessage = 'Le dépôt n\'a pas pu être enregistré. Réessayez ; si le problème continue, contactez le support PressLink.';

            return;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->depositId = $deposit->id;
    }

    /** Écran remis à zéro pour le client suivant. */
    public function nextDeposit(): void
    {
        $this->reset(['clientSearch', 'selectedCustomerId', 'showNewClientForm', 'newFirstName', 'newLastName', 'newPhone', 'knownCustomerId', 'knownCustomerName', 'declaredPieces', 'notes', 'errorMessage', 'depositId']);
        $this->expectedAt = $this->defaultExpectedAt();
    }

    protected function customerPicked(): void
    {
        $this->resetErrorBag('clientSearch');
        $this->dispatch('depot-pieces');
    }

    protected function photoOrder(): Order
    {
        return $this->deposit() ?? abort(404);
    }

    private function deposit(): ?Order
    {
        return $this->depositId !== null ? $this->pressing()->orders()->find($this->depositId) : null;
    }

    private function pressing(): Pressing
    {
        return auth()->user()->currentPressing();
    }

    private function defaultExpectedAt(): string
    {
        return now()->addDays(2)->format('Y-m-d');
    }

    #[Layout('layouts.dashboard', ['active' => 'orders', 'title' => 'Dépôt rapide'])]
    public function render()
    {
        $pressing = $this->pressing();
        $selectedCustomer = $this->selectedCustomerId ? $pressing->customers()->find($this->selectedCustomerId) : null;
        $deposit = $this->deposit()?->load(['customer', 'photos', 'pressing']);

        return view('livewire.orders.quick-deposit', [
            'clients' => $selectedCustomer === null && $deposit === null ? $this->searchCustomers($pressing) : collect(),
            'selectedCustomer' => $selectedCustomer,
            'acceptsCustomers' => $pressing->acceptsCustomers(),
            'deposit' => $deposit,
            'order' => $deposit,
            'canAddPhotos' => $deposit !== null && OrderPhotos::canAdd(auth()->user(), $deposit),
            'maxPhotos' => OrderPhoto::MAX_PER_ORDER,
        ]);
    }
}
