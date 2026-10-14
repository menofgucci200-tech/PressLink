<?php

namespace App\Livewire\Orders;

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class Index extends Component
{
    use ShowsToasts, WithPagination;

    /** Colonnes triables, avec leur sens par défaut au premier clic. */
    public const SORTABLE = [
        'numero' => 'asc',
        'client' => 'asc',
        'statut' => 'asc',
        'total' => 'desc',
        'reste' => 'desc',
        'depot' => 'desc',
    ];

    /**
     * Synchronisé avec `?search=` dans l'URL : permet à la recherche
     * globale de l'en-tête d'atterrir ici avec le terme déjà pré-rempli.
     */
    #[Url]
    public string $search = '';

    /** Permet de deep-linker depuis le dashboard (ex. tâches "à traiter"). */
    #[Url]
    public string $status = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    /** Payée, acompte versé ou à payer (voir PaymentStatus). */
    #[Url(as: 'paiement')]
    public string $payment = '';

    /** Tri : 'recent' (dernières créées) ou une clé de SORTABLE. */
    #[Url(as: 'tri', except: 'recent')]
    public string $sort = 'recent';

    #[Url(as: 'sens', except: 'desc')]
    public string $direction = 'desc';

    /** Commande ouverte dans le panneau d'aperçu (lien partageable). */
    #[Url(as: 'apercu', except: null)]
    public ?int $previewId = null;

    public string $previewPaymentMethod = 'especes';

    public ?string $previewError = null;

    /**
     * Dernière commande affichée : les commandes créées ensuite (par un
     * collègue) sont annoncées par un bandeau au lieu de décaler la liste.
     */
    public int $baselineOrderId = 0;

    public int $newOrdersCount = 0;

    public bool $showExportMenu = false;

    /**
     * Commandes cochées pour une action groupée (identifiants).
     *
     * @var list<string>
     */
    public array $selected = [];

    /** Au-delà, l'action groupée est refusée (garde-fou). */
    public const MAX_SELECTION = 100;

    public function mount(): void
    {
        $this->baselineOrderId = (int) ($this->pressing()?->orders()->max('id') ?? 0);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'dateFrom', 'dateTo', 'payment'], true)) {
            $this->resetPage();
            // Pas d'action groupée sur des commandes qui ne sont plus affichées.
            $this->selected = [];
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'dateFrom', 'dateTo', 'payment', 'sort', 'direction']);
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! array_key_exists($column, self::SORTABLE)) {
            return;
        }

        $this->direction = $this->sort === $column
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : self::SORTABLE[$column];
        $this->sort = $column;
        $this->resetPage();
    }

    /** Appelé toutes les 30 s (wire:poll) : statuts à jour + nouvelles commandes. */
    public function checkForNewOrders(): void
    {
        $pressing = $this->pressing();
        if ($pressing === null) {
            $this->skipRender();

            return;
        }

        $this->newOrdersCount = $pressing->filteredOrders($this->filters())
            ->where('orders.id', '>', $this->baselineOrderId)
            ->count();
    }

    public function showNewOrders(): void
    {
        $this->baselineOrderId = (int) ($this->pressing()?->orders()->max('id') ?? 0);
        $this->newOrdersCount = 0;
        $this->resetPage();
    }

    /** @param  list<int>  $ids  commandes de la page affichée */
    public function togglePage(array $ids): void
    {
        $ids = array_map('strval', $ids);
        $this->selected = array_diff($ids, $this->selected) === []
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /**
     * Change le statut de toutes les commandes cochées qui le permettent. Une
     * remise au client n'est faite en groupe que pour les commandes soldées :
     * celles avec un reste à payer sont laissées de côté (à encaisser une par
     * une).
     */
    public function bulkTransition(string $status, ChangeOrderStatusAction $changeStatus): void
    {
        $target = OrderStatus::tryFrom($status);
        $pressing = $this->pressing();

        if ($target === null || $pressing === null || $this->selected === []) {
            return;
        }

        if (count($this->selected) > self::MAX_SELECTION) {
            $this->toast('Sélectionnez au plus '.self::MAX_SELECTION.' commandes à la fois.', 'warning');

            return;
        }

        $orders = $pressing->orders()->whereIn('id', array_map('intval', $this->selected))->get();
        $done = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $blocked = ! $order->status->canTransitionTo($target)
                || ($target === OrderStatus::Recuperee && $order->balance_due_fcfa > 0)
                || ! auth()->user()->can('update', $order);

            if ($blocked) {
                $skipped++;

                continue;
            }

            try {
                $changeStatus->handle($order, $target);
                $done++;
            } catch (RuntimeException) {
                $skipped++;
            }
        }

        $this->selected = [];

        $message = $done > 0
            ? $done.' commande'.($done > 1 ? 's passées' : ' passée').' au statut « '.$target->label().' ».'
            : 'Aucune commande n\'a changé de statut.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' laissée'.($skipped > 1 ? 's' : '').' de côté ('
                .($target === OrderStatus::Recuperee ? 'statut incompatible ou solde à encaisser' : 'statut incompatible').').';
        }

        $this->toast($message, $done > 0 ? 'success' : 'warning');
    }

    public function preview(int $orderId): void
    {
        $this->previewId = $orderId;
        $this->previewError = null;
    }

    public function closePreview(): void
    {
        $this->previewId = null;
        $this->previewError = null;
    }

    public function previewTransition(string $status, ChangeOrderStatusAction $changeStatus): void
    {
        $order = $this->authorizedPreviewOrder();
        $target = OrderStatus::tryFrom($status);

        if ($target === null) {
            return;
        }

        try {
            $changeStatus->handle($order, $target);
        } catch (RuntimeException $e) {
            $this->previewError = $e->getMessage();

            return;
        }

        $this->previewError = null;
        $this->toast(ChangeOrderStatusAction::successMessage($target), $target === OrderStatus::Annulee ? 'warning' : 'success');
    }

    public function previewSettleAndRecover(ChangeOrderStatusAction $changeStatus): void
    {
        $order = $this->authorizedPreviewOrder();

        try {
            $changeStatus->handle($order, OrderStatus::Recuperee, PaymentMethod::tryFrom($this->previewPaymentMethod) ?? PaymentMethod::Cash);
        } catch (RuntimeException $e) {
            $this->previewError = $e->getMessage();

            return;
        }

        $this->previewError = null;
        $this->toast('Solde encaissé et commande remise au client.');
    }

    private function authorizedPreviewOrder(): Order
    {
        $order = $this->pressing()?->orders()->findOrFail((int) $this->previewId);
        abort_unless(auth()->user()->can('update', $order), 403);

        return $order;
    }

    private function pressing(): ?Pressing
    {
        return auth()->user()->currentPressing();
    }

    /** @return array{search: ?string, date_from: ?string, date_to: ?string, payment: ?string} */
    private function filters(): array
    {
        return [
            'search' => $this->search ?: null,
            'date_from' => $this->dateFrom ?: null,
            'date_to' => $this->dateTo ?: null,
            'payment' => $this->payment ?: null,
        ];
    }

    /**
     * @param  HasMany<Order, Pressing>  $query
     * @return HasMany<Order, Pressing>
     */
    private function applySort(HasMany $query): HasMany
    {
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        match ($this->sort) {
            'numero' => $query->orderBy('order_number', $direction),
            'client' => $query
                ->orderBy(Customer::select('last_name')->whereColumn('customers.id', 'orders.customer_id'), $direction)
                ->orderBy(Customer::select('first_name')->whereColumn('customers.id', 'orders.customer_id'), $direction),
            // Ordre du parcours d'une commande, pas l'ordre alphabétique.
            'statut' => $query->orderByRaw("CASE status WHEN 'recue' THEN 1 WHEN 'traitement' THEN 2 WHEN 'attente' THEN 3 WHEN 'prete' THEN 4 WHEN 'recuperee' THEN 5 ELSE 6 END {$direction}"),
            'total' => $query->orderBy('total_fcfa', $direction),
            'reste' => $query->orderByRaw("CAST(total_fcfa AS SIGNED) - CAST(amount_paid_fcfa AS SIGNED) {$direction}"),
            'depot' => $query->orderBy('dropped_off_at', $direction),
            default => $query->latest(),
        };

        return $query->orderByDesc('orders.id');
    }

    #[Layout('layouts.dashboard', ['active' => 'orders', 'title' => 'Commandes'])]
    public function render()
    {
        $pressing = $this->pressing();

        $orders = collect();
        $counts = [];
        $previewOrder = null;

        if ($pressing !== null) {
            $filters = $this->filters();
            $shown = fn (HasMany $query) => $this->baselineOrderId > 0
                ? $query->where('orders.id', '<=', $this->baselineOrderId)
                : $query;

            $orders = $this->applySort($shown($pressing->filteredOrders([...$filters, 'status' => $this->status ?: null]))
                ->withReadySince())
                ->paginate(15);

            // Compteurs des filtres de statut : mêmes recherche et dates que la
            // liste, pour annoncer ce qu'on obtient en cliquant sur un statut.
            $counts = $shown($pressing->filteredOrders($filters))
                ->reorder()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->all();

            $previewOrder = $this->previewId
                ? $pressing->orders()->withReadySince()->with(['customer', 'items', 'payments'])->find($this->previewId)
                : null;
        }

        return view('livewire.orders.index', [
            'orders' => $orders,
            'counts' => $counts,
            'statuses' => OrderStatus::cases(),
            'previewOrder' => $previewOrder,
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }
}
