<?php

namespace App\Livewire\Sales;

use App\Livewire\Concerns\ActsAsSalesAgent;
use App\Models\SalesCommission;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Mes revenus » : la commission du commercial sur chaque paiement de
 * l'abonnement de ses pressings, mois par mois, payée ou à recevoir.
 */
class Earnings extends Component
{
    use ActsAsSalesAgent;

    /** Mois affiché en détail (AAAA-MM), le plus récent par défaut. */
    #[Url(as: 'mois', except: '')]
    public string $month = '';

    public function mount(): void
    {
        $this->agent();
    }

    #[Layout('layouts.sales', ['active' => 'earnings', 'title' => 'Mes revenus — PressLink'])]
    public function render(): View
    {
        $agent = $this->agent();
        $commissions = $agent->commissions()->get();
        $months = SalesCommission::byMonth($commissions);
        $selected = $months->has($this->month) ? $this->month : ($months->keys()->first() ?? '');

        return view('livewire.sales.earnings', [
            'agent' => $agent,
            'months' => $months,
            'selected' => $selected,
            'lines' => $commissions->filter(fn (SalesCommission $commission) => $commission->earned_at->format('Y-m') === $selected)->sortByDesc('earned_at'),
            'totals' => [
                'thisMonth' => $commissions->filter(fn (SalesCommission $commission) => $commission->earned_at->isCurrentMonth())->sum('amount_fcfa'),
                'unpaid' => $commissions->whereNull('paid_at')->sum('amount_fcfa'),
                'paid' => $commissions->whereNotNull('paid_at')->sum('amount_fcfa'),
            ],
        ]);
    }
}
