<?php

namespace App\Livewire;

use App\Enums\OrderIssueStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Dashboard extends Component
{
    public bool $showExportMenu = false;

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
        $tasks = ['readyOverdue' => 0, 'received' => 0, 'openIssues' => 0];
        $revenueByDay = collect();

        if ($pressing !== null) {
            $counts = array_merge(
                $counts,
                $pressing->orders()
                    ->selectRaw('status, count(*) as aggregate')
                    ->whereDate('created_at', today())
                    ->groupBy('status')
                    ->pluck('aggregate', 'status')
                    ->all(),
            );

            $recent = $pressing->orders()->with('customer')->latest()->take(8)->get();

            $countsYesterday = $pressing->orders()
                ->selectRaw('status, count(*) as aggregate')
                ->whereDate('created_at', today()->subDay())
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $tasks = [
                // Encore "prête" 5 jours après le dernier passage à ce statut : le
                // client n'est pas revenu la récupérer, à relancer.
                'readyOverdue' => $pressing->orders()
                    ->where('status', OrderStatus::Prete->value)
                    ->where('updated_at', '<=', now()->subDays(5))
                    ->count(),
                'received' => $pressing->orders()->where('status', OrderStatus::Recue->value)->count(),
                'openIssues' => $pressing->orders()
                    ->whereHas('issues', fn ($q) => $q->where('status', OrderIssueStatus::Open->value))
                    ->count(),
            ];

            // "Encaissé" = commandes marquées récupérées (le paiement se fait au
            // retrait) — le montant du jour est agrégé sur le passage à ce statut,
            // pas sur la date de création de la commande.
            $revenueByDay = OrderStatusHistory::query()
                ->join('orders', 'orders.id', '=', 'order_status_histories.order_id')
                ->where('orders.pressing_id', $pressing->id)
                ->where('order_status_histories.status', OrderStatus::Recuperee->value)
                ->where('order_status_histories.created_at', '>=', now()->subDays(13)->startOfDay())
                ->selectRaw('DATE(order_status_histories.created_at) as day, SUM(orders.total_fcfa) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
        }

        $last7 = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $previous7 = collect(range(13, 7))->map(fn ($i) => now()->subDays($i)->toDateString());

        $revenueLast7 = $last7->sum(fn ($day) => (int) ($revenueByDay[$day] ?? 0));
        $revenuePrevious7 = $previous7->sum(fn ($day) => (int) ($revenueByDay[$day] ?? 0));

        $revenueChangePct = $revenuePrevious7 > 0
            ? (int) round((($revenueLast7 - $revenuePrevious7) / $revenuePrevious7) * 100)
            : null;

        $spark = $last7->map(fn ($day) => (int) ($revenueByDay[$day] ?? 0));
        $sparkMax = max($spark->max(), 1);

        return view('livewire.dashboard', [
            'pressing' => $pressing,
            'counts' => $counts,
            'countsYesterday' => $countsYesterday ?? collect(),
            'recent' => $recent,
            'tasks' => $tasks,
            'revenueLast7' => $revenueLast7,
            'revenueChangePct' => $revenueChangePct,
            'spark' => $spark,
            'sparkMax' => $sparkMax,
        ]);
    }
}
