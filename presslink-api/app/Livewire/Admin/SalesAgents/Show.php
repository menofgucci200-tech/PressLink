<?php

namespace App\Livewire\Admin\SalesAgents;

use App\Livewire\Concerns\ShowsToasts;
use App\Models\SalesAgent;
use App\Models\SalesCommission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Relevé d'un commercial — Super Admin : ses commissions mois par mois.
 * L'équipe le paie (Wave ou autre) puis marque le mois comme payé ; le
 * commercial le voit aussitôt dans « Mes revenus ».
 */
class Show extends Component
{
    use ShowsToasts;

    #[Locked]
    public int $agentId;

    /** @var array<string, string> référence du paiement saisie pour chaque mois (AAAA-MM) */
    public array $references = [];

    public function mount(SalesAgent $salesAgent): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        $this->agentId = $salesAgent->id;
    }

    /** Toutes les commissions encore dues de ce mois passent « payées ». */
    public function markMonthPaid(string $month): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month) === 1, 422);
        $this->validate(["references.{$month}" => ['nullable', 'string', 'max:100']], [], ["references.{$month}" => 'référence']);

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $agent = SalesAgent::with('user')->findOrFail($this->agentId);

        $count = $agent->commissions()->unpaid()
            ->whereBetween('earned_at', [$start, $start->copy()->endOfMonth()])
            ->update([
                'paid_at' => now(),
                'paid_by' => auth()->id(),
                'payout_reference' => trim($this->references[$month] ?? '') ?: null,
            ]);

        unset($this->references[$month]);
        $this->toast($count > 0
            ? 'Commissions de '.$start->translatedFormat('F Y')." marquées payées pour {$agent->user->name}."
            : 'Rien à payer pour ce mois.', $count > 0 ? 'success' : 'info');
    }

    #[Layout('layouts.admin', ['active' => 'sales', 'title' => 'Commercial'])]
    public function render(): View
    {
        $agent = SalesAgent::with('user')->withCount('pressings')->findOrFail($this->agentId);
        $commissions = $agent->commissions()->latest('earned_at')->get();

        return view('livewire.admin.sales-agents.show', [
            'agent' => $agent,
            'months' => SalesCommission::byMonth($commissions),
            'linesByMonth' => $commissions->groupBy(fn (SalesCommission $commission) => $commission->earned_at->format('Y-m')),
            'unpaid' => $commissions->whereNull('paid_at')->sum('amount_fcfa'),
            'paid' => $commissions->whereNotNull('paid_at')->sum('amount_fcfa'),
        ]);
    }
}
