<?php

namespace App\Livewire\Cash;

use App\Actions\Cash\CashDaySummary;
use App\Enums\ActivityAction;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\ActivityLog;
use App\Models\CashClosing;
use App\Models\Pressing;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Caisse du jour d'un pressing : encaissements enregistrés par moyen de
 * paiement, puis clôture avec les espèces comptées et l'écart. Les gérants
 * peuvent rouvrir une journée clôturée par erreur.
 */
class Index extends Component
{
    use ShowsToasts;

    #[Url(as: 'jour', except: '')]
    public string $day = '';

    public string $declaredCash = '';

    public string $note = '';

    private function pressing(): Pressing
    {
        $pressing = auth()->user()->currentPressing();
        abort_if($pressing === null, 403);

        return $pressing;
    }

    private function selectedDay(): Carbon
    {
        try {
            $day = $this->day !== '' ? Carbon::parse($this->day)->startOfDay() : today();
        } catch (\Exception) {
            $day = today();
        }

        return $day->gt(today()) ? today() : $day;
    }

    public function updatedDay(): void
    {
        $this->reset(['declaredCash', 'note']);
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $pressing = $this->pressing();
        $day = $this->selectedDay();

        $this->validate([
            'declaredCash' => ['required', 'integer', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'declaredCash.required' => 'Indiquez le montant en espèces compté dans la caisse.',
            'declaredCash.integer' => 'Le montant doit être un nombre entier de francs.',
        ]);

        if (CashClosing::where('pressing_id', $pressing->id)->whereDate('business_date', $day)->exists()) {
            $this->toast('Cette journée est déjà clôturée.', 'warning');

            return;
        }

        $summary = CashDaySummary::for($pressing, $day);
        $declared = (int) $this->declaredCash;
        $difference = $declared - $summary['cash'];

        CashClosing::create([
            'pressing_id' => $pressing->id,
            'business_date' => $day->toDateString(),
            'totals_by_method' => collect($summary['by_method'])->map(fn ($m) => ['count' => $m['count'], 'total' => $m['total']])->all(),
            'total_fcfa' => $summary['total'],
            'expected_cash_fcfa' => $summary['cash'],
            'declared_cash_fcfa' => $declared,
            'difference_fcfa' => $difference,
            'payments_count' => $summary['count'],
            'note' => trim($this->note) ?: null,
            'closed_by' => auth()->id(),
        ]);

        $fcfa = fn (int $n) => number_format($n, 0, ',', ' ');
        ActivityLog::record($pressing->id, ActivityAction::CashClosed,
            'Caisse du '.$day->format('d/m/Y').' clôturée : '.$fcfa($declared).' F comptés pour '.$fcfa($summary['cash']).' F attendus'
            .($difference !== 0 ? ' (écart '.($difference > 0 ? '+' : '').$fcfa($difference).' F)' : ''),
            null, ['difference_fcfa' => $difference, 'business_date' => $day->toDateString()]);

        $this->reset(['declaredCash', 'note']);
        $this->toast($difference === 0 ? 'Caisse clôturée : aucun écart.' : 'Caisse clôturée avec un écart de '.($difference > 0 ? '+' : '').$fcfa($difference).' F.', $difference === 0 ? 'success' : 'warning');
    }

    public function reopen(): void
    {
        $pressing = $this->pressing();
        abort_unless(auth()->user()->isAdminOf($pressing), 403);
        $day = $this->selectedDay();

        $closing = CashClosing::where('pressing_id', $pressing->id)->whereDate('business_date', $day)->firstOrFail();
        $closing->delete();

        ActivityLog::record($pressing->id, ActivityAction::CashClosed, 'Clôture de caisse du '.$day->format('d/m/Y').' annulée (journée rouverte)', null, ['reopened' => true]);
        $this->toast('Journée rouverte : vous pouvez la clôturer à nouveau.', 'warning');
    }

    #[Layout('layouts.dashboard', ['active' => 'cash', 'title' => 'Caisse'])]
    public function render()
    {
        $pressing = $this->pressing();
        $day = $this->selectedDay();

        return view('livewire.cash.index', [
            'pressing' => $pressing,
            'selectedDay' => $day,
            'summary' => CashDaySummary::for($pressing, $day),
            'closing' => CashClosing::with('closer:id,name')->where('pressing_id', $pressing->id)->whereDate('business_date', $day)->first(),
            'history' => CashClosing::with('closer:id,name')->where('pressing_id', $pressing->id)->orderByDesc('business_date')->take(14)->get(),
            'canReopen' => auth()->user()->isAdminOf($pressing),
        ]);
    }
}
