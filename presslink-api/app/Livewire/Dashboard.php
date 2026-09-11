<?php

namespace App\Livewire;

use App\Enums\OrderIssueStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    public bool $showExportMenu = false;

    /** Filtre l'aperçu "Commandes récentes" — même champs que Orders\Index. */
    public string $search = '';

    /** 'today' | '7d' | '30d' | 'custom' — période des 4 cartes stats. */
    #[Url]
    public string $period = 'today';

    #[Url]
    public string $customFrom = '';

    #[Url]
    public string $customTo = '';

    public function setPeriod(string $period): void
    {
        $this->period = $period;

        if ($period !== 'custom') {
            $this->reset(['customFrom', 'customTo']);
        }
    }

    #[Layout('layouts.dashboard', ['active' => 'dashboard', 'title' => 'Dashboard'])]
    public function render()
    {
        $user = auth()->user();

        if ($user->hasMultiplePressings() && ! session()->has('active_pressing_id')) {
            return $this->renderOverview($user);
        }

        return $this->renderSinglePressing($user);
    }

    /**
     * Vue d'ensemble consolidée — atterrissage par défaut d'un propriétaire
     * ayant plusieurs pressings, tant qu'il n'en a pas choisi une précise.
     */
    private function renderOverview(User $user): View
    {
        $pressings = $user->activePressings();

        $rows = $pressings->map(function ($pressing) {
            $todayCount = $pressing->orders()->whereDate('created_at', today())->count();
            $openCount = $pressing->orders()->whereNotIn('status', [
                OrderStatus::Recuperee->value,
                OrderStatus::Annulee->value,
            ])->count();

            return [
                'pressing' => $pressing,
                'today_count' => $todayCount,
                'open_count' => $openCount,
            ];
        });

        $pressingIds = $pressings->pluck('id');

        $totals = [
            'today_count' => $rows->sum('today_count'),
            'open_count' => $rows->sum('open_count'),
        ];

        $recent = Order::whereIn('pressing_id', $pressingIds)
            ->with(['customer', 'pressing'])
            ->latest()
            ->take(8)
            ->get();

        return view('livewire.dashboard-overview', [
            'rows' => $rows,
            'totals' => $totals,
            'recent' => $recent,
        ]);
    }

    /**
     * Bornes [début, fin] de la période sélectionnée pour les cartes stats,
     * et la période de même durée immédiatement avant (pour le delta).
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon, 4: string}
     */
    private function resolvePeriod(): array
    {
        [$start, $end, $label] = match ($this->period) {
            '7d' => [now()->subDays(6)->startOfDay(), now()->endOfDay(), 'sur 7 jours'],
            '30d' => [now()->subDays(29)->startOfDay(), now()->endOfDay(), 'sur 30 jours'],
            'custom' => $this->resolveCustomPeriod(),
            default => [now()->startOfDay(), now()->endOfDay(), "aujourd'hui"],
        };

        $lengthInDays = $start->diffInDays($end) + 1;
        $previousEnd = $start->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subDays($lengthInDays - 1)->startOfDay();

        return [$start, $end, $previousStart, $previousEnd, $label];
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function resolveCustomPeriod(): array
    {
        try {
            $start = $this->customFrom !== '' ? Carbon::parse($this->customFrom)->startOfDay() : now()->startOfDay();
            $end = $this->customTo !== '' ? Carbon::parse($this->customTo)->endOfDay() : now()->endOfDay();
        } catch (\Exception) {
            return [now()->startOfDay(), now()->endOfDay(), "aujourd'hui"];
        }

        if ($end->lt($start)) {
            $end = $start->copy()->endOfDay();
        }

        return [$start, $end, 'du '.$start->format('d/m').' au '.$end->format('d/m')];
    }

    private function renderSinglePressing(User $user): View
    {
        $pressing = $user->currentPressing();

        $counts = [
            OrderStatus::Recue->value => 0,
            OrderStatus::Traitement->value => 0,
            OrderStatus::Prete->value => 0,
            OrderStatus::Recuperee->value => 0,
        ];

        $recent = collect();
        $tasks = [];
        $revenueByDay = collect();

        [$rangeStart, $rangeEnd, $previousStart, $previousEnd, $periodLabel] = $this->resolvePeriod();

        if ($pressing !== null) {
            $counts = array_merge(
                $counts,
                $pressing->orders()
                    ->selectRaw('status, count(*) as aggregate')
                    ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->groupBy('status')
                    ->pluck('aggregate', 'status')
                    ->all(),
            );

            $recent = $pressing->filteredOrders(['search' => $this->search ?: null])->latest()->take(8)->get();

            $countsPrevious = $pressing->orders()
                ->selectRaw('status, count(*) as aggregate')
                ->whereBetween('created_at', [$previousStart, $previousEnd])
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            // "À traiter aujourd'hui" reste indépendant de la période choisie
            // pour les cartes : c'est toujours l'état actuel de l'atelier,
            // pas un historique. On récupère l'id de la première commande
            // concernée pour pouvoir y renvoyer directement quand il n'y en
            // a qu'une (sinon, la liste filtrée par statut).
            $readyOverdueOrders = $pressing->orders()
                ->where('status', OrderStatus::Prete->value)
                ->where('updated_at', '<=', now()->subDays(5))
                ->pluck('id');

            $receivedOrders = $pressing->orders()->where('status', OrderStatus::Recue->value)->pluck('id');

            $openIssueOrders = $pressing->orders()
                ->whereHas('issues', fn ($q) => $q->where('status', OrderIssueStatus::Open->value))
                ->pluck('id');

            $tasks = [
                'readyOverdue' => ['count' => $readyOverdueOrders->count(), 'orderId' => $readyOverdueOrders->first()],
                'received' => ['count' => $receivedOrders->count(), 'orderId' => $receivedOrders->first()],
                'openIssues' => ['count' => $openIssueOrders->count(), 'orderId' => $openIssueOrders->first()],
            ];

            // "Encaissé" = commandes marquées récupérées (le paiement se fait au
            // retrait) — le montant du jour est agrégé sur le passage à ce statut,
            // pas sur la date de création de la commande. Récupéré sur toute la
            // fenêtre [période précédente → période courante] pour pouvoir
            // calculer le delta avec une seule requête.
            $revenueByDay = OrderStatusHistory::query()
                ->join('orders', 'orders.id', '=', 'order_status_histories.order_id')
                ->where('orders.pressing_id', $pressing->id)
                ->where('order_status_histories.status', OrderStatus::Recuperee->value)
                ->whereBetween('order_status_histories.created_at', [$previousStart, $rangeEnd])
                ->selectRaw('DATE(order_status_histories.created_at) as day, SUM(orders.total_fcfa) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
        }

        $revenuePeriod = $this->sumRevenueBetween($revenueByDay, $rangeStart, $rangeEnd);
        $revenuePrevious = $this->sumRevenueBetween($revenueByDay, $previousStart, $previousEnd);

        $revenueChangePct = $revenuePrevious > 0
            ? (int) round((($revenuePeriod - $revenuePrevious) / $revenuePrevious) * 100)
            : null;

        $buckets = $this->buildRevenueBuckets($revenueByDay, $rangeStart, $rangeEnd);
        $spark = $buckets->pluck('total');
        $sparkMax = max($spark->max(), 1);
        $sparkLabels = $buckets->pluck('label');

        return view('livewire.dashboard', [
            'pressing' => $pressing,
            'counts' => $counts,
            'countsPrevious' => $countsPrevious ?? collect(),
            'periodLabel' => $periodLabel,
            // Pour que "Exporter" respecte la période affichée (Aujourd'hui,
            // 7/30 jours, personnalisée) au lieu de toujours tout exporter.
            'exportParams' => [
                'status' => null,
                'search' => $this->search ?: null,
                'date_from' => $rangeStart->toDateString(),
                'date_to' => $rangeEnd->toDateString(),
            ],
            'recent' => $recent,
            'tasks' => $tasks,
            'revenuePeriod' => $revenuePeriod,
            'revenueChangePct' => $revenueChangePct,
            'spark' => $spark,
            'sparkMax' => $sparkMax,
            'sparkLabels' => $sparkLabels,
        ]);
    }

    /** @param  Collection<string, int>  $revenueByDay */
    private function sumRevenueBetween($revenueByDay, Carbon $start, Carbon $end): int
    {
        $total = 0;
        for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            $total += (int) ($revenueByDay[$day->toDateString()] ?? 0);
        }

        return $total;
    }

    /**
     * Répartit la période en au plus 7 tranches (un jour chacune si la
     * période fait 7 jours ou moins, sinon des groupes de plusieurs jours)
     * pour que le mini-graphique reste lisible quelle que soit la période
     * choisie — 1 jour, 7 jours, 30 jours ou une plage personnalisée.
     *
     * @param  Collection<string, int>  $revenueByDay
     * @return Collection<int, array{total: int, label: string}>
     */
    private function buildRevenueBuckets($revenueByDay, Carbon $start, Carbon $end): Collection
    {
        $totalDays = $start->diffInDays($end) + 1;
        $bucketSize = (int) ceil($totalDays / 7);

        $buckets = collect();
        $cursor = $start->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $bucketEnd = min($cursor->copy()->addDays($bucketSize - 1), $end->copy()->startOfDay());

            $total = $this->sumRevenueBetween($revenueByDay, $cursor, $bucketEnd);

            $label = $bucketSize === 1
                ? mb_strtoupper(mb_substr($cursor->locale('fr')->dayName, 0, 1))
                : $cursor->format('d/m').($bucketEnd->ne($cursor) ? '-'.$bucketEnd->format('d/m') : '');

            $buckets->push(['total' => $total, 'label' => $label]);

            $cursor = $bucketEnd->copy()->addDay();
        }

        return $buckets;
    }
}
