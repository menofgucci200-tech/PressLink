<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Dashboard extends Component
{
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
        }

        return view('livewire.dashboard', [
            'pressing' => $pressing,
            'counts' => $counts,
            'recent' => $recent,
        ]);
    }
}
