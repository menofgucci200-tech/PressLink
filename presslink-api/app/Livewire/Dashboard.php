<?php

namespace App\Livewire;

use App\Enums\OrderIssueStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use App\Support\GroupMetrics;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    /** Tri du comparatif des pressings (tableau de bord du groupe). */
    #[Url(as: 'tri', except: 'revenue')]
    public string $groupSort = 'revenue';

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
            return $user->managesGroup() ? $this->renderGroup($user) : $this->renderOverview($user);
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
     * Tableau de bord du groupe — propriétaire ou responsable de zone qui
     * administre plusieurs pressings : totaux, comparatif, alertes et
     * évolution de l'encaissé sur la période choisie.
     */
    private function renderGroup(User $user): View
    {
        [$start, $end, , , $periodLabel] = $this->resolvePeriod();
        $metrics = new GroupMetrics($user->managedPressings(), $start, $end);
        $rows = $metrics->perPressing();

        return view('livewire.group.dashboard', [
            'rows' => $rows,
            'totals' => $metrics->totals($rows),
            'alerts' => $metrics->alerts($rows),
            'chart' => $metrics->dailyRevenue(),
            'periodLabel' => $periodLabel,
            'periodFrom' => $start->toDateString(),
            'periodTo' => $end->toDateString(),
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
            // Date de dépôt, comme le filtre de la liste des commandes : un
            // clic sur une carte retrouve exactement le même nombre.
            $counts = array_merge(
                $counts,
                $pressing->orders()
                    ->selectRaw('status, count(*) as aggregate')
                    ->whereBetween('dropped_off_at', [$rangeStart, $rangeEnd])
                    ->groupBy('status')
                    ->pluck('aggregate', 'status')
                    ->all(),
            );

            $recent = $pressing->filteredOrders(['search' => $this->search ?: null])->withReadySince()->latest()->take(8)->get();

            $countsPrevious = $pressing->orders()
                ->selectRaw('status, count(*) as aggregate')
                ->whereBetween('dropped_off_at', [$previousStart, $previousEnd])
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            // "À traiter aujourd'hui" reste indépendant de la période choisie
            // pour les cartes : c'est toujours l'état actuel de l'atelier,
            // pas un historique. On récupère l'id de la première commande
            // concernée pour pouvoir y renvoyer directement quand il n'y en
            // a qu'une (sinon, la liste filtrée par statut).
            // Même règle que l'alerte « Prête depuis N j » des listes : date
            // du passage au statut « prête » (historique), pas la dernière
            // modification de la commande.
            $readyOverdueOrders = $pressing->orders()
                ->where('status', OrderStatus::Prete->value)
                ->withReadySince()
                ->oldest('id')
                ->get(['id', 'status', 'updated_at'])
                ->filter(fn (Order $order) => $order->delayAlert() !== null)
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

            // "Encaissé" = paiements réellement reçus (au dépôt, acomptes,
            // soldes au retrait), datés du jour de l'encaissement. Les
            // commandes annulées en sont exclues (à rembourser). Récupéré
            // sur toute la fenêtre [période précédente → période courante]
            // pour calculer le delta avec une seule requête.
            $revenueByDay = OrderPayment::query()
                ->join('orders', 'orders.id', '=', 'order_payments.order_id')
                ->where('orders.pressing_id', $pressing->id)
                ->where('orders.status', '!=', OrderStatus::Annulee->value)
                ->whereNull('orders.deleted_at')
                ->whereBetween('order_payments.paid_at', [$previousStart, $rangeEnd])
                ->selectRaw('DATE(order_payments.paid_at) as day, SUM(order_payments.amount_fcfa) as total')
                ->groupBy('day')
                ->pluck('total', 'day');

            // Ce qui reste dû sur les commandes en cours ou remises à crédit.
            $outstanding = (int) $pressing->orders()
                ->where('status', '!=', OrderStatus::Annulee->value)
                ->whereColumn('amount_paid_fcfa', '<', 'total_fcfa')
                ->sum(DB::raw('total_fcfa - amount_paid_fcfa'));
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
            'outstanding' => $outstanding ?? 0,
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
