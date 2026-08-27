<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Dashboard extends Component
{
    public bool $showExportMenu = false;

    #[Layout('layouts.dashboard', ['active' => 'dashboard', 'title' => 'Dashboard'])]
    public function render()
    {
        $pressing = auth()->user()->currentPressing();

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
