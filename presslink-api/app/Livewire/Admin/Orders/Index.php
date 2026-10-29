<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\OrderIssueStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vue Commandes plateforme — Super Admin. Utile pour du support :
 * retrouver n'importe quelle commande sans connaître son pressing
 * d'origine et en consulter le détail (articles, historique,
 * signalements) sans quitter la console.
 */
class Index extends Component
{
    use WithPagination;

    /** Statuts pour lesquels une date prévue dépassée signale un retard. */
    public const OPEN_STATUSES = [OrderStatus::Recue, OrderStatus::Traitement, OrderStatus::Attente];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'pressing', except: '')]
    public string $pressingId = '';

    #[Url(as: 'du', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'au', except: '')]
    public string $dateTo = '';

    #[Url(as: 'signalements', except: false)]
    public bool $withOpenIssues = false;

    #[Url(as: 'retard', except: false)]
    public bool $lateOnly = false;

    /** Commande affichée dans le panneau de détail. */
    #[Url(as: 'commande', except: null)]
    public ?int $selectedOrderId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'pressingId', 'dateFrom', 'dateTo', 'withOpenIssues', 'lateOnly'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'pressingId', 'dateFrom', 'dateTo', 'withOpenIssues', 'lateOnly']);
        $this->resetPage();
    }

    public function showOrder(int $orderId): void
    {
        $this->selectedOrderId = $orderId;
    }

    public function closeOrder(): void
    {
        $this->selectedOrderId = null;
    }

    /** @return array<string, string|bool> */
    public function filters(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'pressing' => $this->pressingId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'issues' => $this->withOpenIssues,
            'late' => $this->lateOnly,
        ];
    }

    /**
     * Filtres communs à la liste, aux compteurs par statut et à l'export.
     * Le filtre de statut est appliqué à part pour que les compteurs
     * restent visibles quel que soit le statut sélectionné.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Order>
     */
    public static function filteredQuery(array $filters, bool $withStatus = true): Builder
    {
        // Ni la démo ni les pressings de test : leurs commandes sont fictives.
        $query = Order::query()->whereIn('pressing_id', Pressing::withoutDemo()->select('id'));

        if ($term = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"))
                ->orWhereHas('pressing', fn ($p) => $p->where('name', 'like', "%{$term}%")));
        }

        if ($pressingId = $filters['pressing'] ?? '') {
            $query->where('pressing_id', $pressingId);
        }

        if ($from = self::parseDate($filters['date_from'] ?? '')) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to = self::parseDate($filters['date_to'] ?? '')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        if (filter_var($filters['issues'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->whereHas('issues', fn ($q) => $q->where('status', OrderIssueStatus::Open->value));
        }

        if (filter_var($filters['late'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->whereIn('status', array_map(fn (OrderStatus $s) => $s->value, self::OPEN_STATUSES))
                ->where('expected_at', '<', now());
        }

        if ($withStatus && ($status = $filters['status'] ?? '')) {
            $query->where('status', $status);
        }

        return $query;
    }

    private static function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    #[Layout('layouts.admin', ['active' => 'orders', 'title' => 'Commandes'])]
    public function render()
    {
        $filters = $this->filters();

        $statusCounts = self::filteredQuery($filters, withStatus: false)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $filtered = self::filteredQuery($filters);

        return view('livewire.admin.orders.index', [
            'orders' => (clone $filtered)
                ->with(['pressing:id,name,code', 'customer:id,first_name,last_name,phone'])
                ->withCount(['items', 'issues as open_issues_count' => fn ($q) => $q->where('status', OrderIssueStatus::Open->value)])
                ->latest()
                ->orderByDesc('id')
                ->paginate(20),
            'volume' => (int) (clone $filtered)->where('status', '!=', OrderStatus::Annulee->value)->sum('total_fcfa'),
            'statusCounts' => $statusCounts,
            'statuses' => OrderStatus::cases(),
            'pressings' => Pressing::withoutDemo()->orderBy('name')->get(['id', 'name']),
            'selectedOrder' => $this->selectedOrderId
                ? Order::with([
                    'pressing:id,name,code,phone',
                    'customer',
                    'items',
                    'statusHistory' => fn ($q) => $q->orderBy('created_at'),
                    'statusHistory.changedBy:id,name',
                    'issues' => fn ($q) => $q->latest(),
                    'creator:id,name',
                    'payments',
                ])->find($this->selectedOrderId)
                : null,
            'hasFilters' => collect($filters)->filter()->isNotEmpty(),
        ]);
    }
}
