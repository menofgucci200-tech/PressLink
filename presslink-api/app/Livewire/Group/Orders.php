<?php

namespace App\Livewire\Group;

use App\Enums\OrderStatus;
use App\Livewire\Group\Concerns\InteractsWithGroup;
use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Toutes les commandes du groupe, avec la colonne Pressing et les mêmes
 * filtres qu'un pressing (statut, paiement, retard, recherche, dates).
 */
class Orders extends Component
{
    use InteractsWithGroup, WithPagination;

    /** Statuts « en cours » regroupés (filtre `statut=en_cours`). */
    public const IN_PROGRESS = 'en_cours';

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'pressing', except: '')]
    public string $pressingId = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'paiement', except: '')]
    public string $payment = '';

    #[Url(as: 'du', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'au', except: '')]
    public string $dateTo = '';

    #[Url(as: 'retard', except: false)]
    public bool $lateOnly = false;

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'pressingId', 'status', 'payment', 'dateFrom', 'dateTo', 'lateOnly'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'pressingId', 'status', 'payment', 'dateFrom', 'dateTo', 'lateOnly']);
        $this->resetPage();
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return [
            'pressing' => $this->pressingId,
            'statut' => $this->status,
            'search' => $this->search,
            'paiement' => $this->payment,
            'du' => $this->dateFrom,
            'au' => $this->dateTo,
            'retard' => $this->lateOnly ? 1 : null,
        ];
    }

    /**
     * Requête partagée avec l'export du groupe.
     *
     * @param  list<int>  $pressingIds
     * @param  array<string, mixed>  $filters
     * @return Builder<Order>
     */
    public static function query(array $pressingIds, array $filters): Builder
    {
        $status = (string) ($filters['statut'] ?? '');

        return Order::query()
            ->whereIn('pressing_id', $pressingIds)
            ->with(['customer', 'pressing:id,name,code'])
            ->applyFilters([
                'search' => $filters['search'] ?? null,
                'payment' => $filters['paiement'] ?? null,
                'date_from' => $filters['du'] ?? null,
                'date_to' => $filters['au'] ?? null,
                'status' => OrderStatus::tryFrom($status)?->value,
            ])
            ->when($status === self::IN_PROGRESS, fn ($q) => $q->whereIn('status', [OrderStatus::Recue->value, OrderStatus::Traitement->value, OrderStatus::Attente->value]))
            ->when(! empty($filters['retard']), fn ($q) => $q
                ->whereIn('status', [OrderStatus::Recue->value, OrderStatus::Traitement->value, OrderStatus::Attente->value])
                ->whereDate('expected_at', '<', today()));
    }

    #[Layout('layouts.dashboard', ['active' => 'group-orders', 'title' => 'Commandes du groupe'])]
    public function render()
    {
        $ids = $this->scopedPressingIds($this->pressingId);
        $base = self::query($ids, $this->filters());

        return view('livewire.group.orders', [
            'orders' => (clone $base)->withReadySince()->latest()->latest('id')->paginate(20),
            'summary' => [
                'count' => (clone $base)->count(),
                'volume' => (int) (clone $base)->where('status', '!=', OrderStatus::Annulee->value)->sum('total_fcfa'),
                'due' => (int) (clone $base)->where('status', '!=', OrderStatus::Annulee->value)->whereColumn('amount_paid_fcfa', '<', 'total_fcfa')->sum(DB::raw('total_fcfa - amount_paid_fcfa')),
            ],
            'pressings' => $this->groupPressings(),
            'statuses' => OrderStatus::cases(),
            'hasFilters' => collect($this->filters())->filter()->isNotEmpty(),
            'unsettled' => Pressing::UNSETTLED_FILTER,
        ]);
    }
}
