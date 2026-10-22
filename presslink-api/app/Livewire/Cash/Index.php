<?php

namespace App\Livewire\Cash;

use App\Actions\Cash\CashDaySummary;
use App\Enums\ActivityAction;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Livewire\Concerns\RequiresFeature;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\ActivityLog;
use App\Models\CashClosing;
use App\Models\CashExpense;
use App\Models\Pressing;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Caisse du jour d'un pressing : dépenses de la journée, puis clôture —
 * espèces encaissées − dépenses = espèces attendues dans la caisse, comparées
 * aux espèces comptées (écart). Selon le rôle :
 *  - historique (propriétaire) : tous les jours, totaux par moyen, clôtures et écarts ;
 *  - clôture (gérant) : la journée en cours seulement ;
 *  - dépenses : enregistrer les sorties d'espèces de la journée.
 * Le propriétaire peut rouvrir une journée clôturée par erreur.
 */
class Index extends Component
{
    use RequiresFeature, ShowsToasts;

    #[Url(as: 'jour', except: '')]
    public string $day = '';

    public string $declaredCash = '';

    public string $note = '';

    // Nouvelle dépense de caisse
    public string $expenseLabel = '';

    public string $expenseAmount = '';

    public function mount(): void
    {
        $this->requireFeature(Feature::Cash);
        $user = auth()->user();
        abort_unless($user->may(Permission::CashHistory) || $user->may(Permission::CashClose) || $user->may(Permission::CashExpenses), 403);
    }

    /** Revérifié à chaque action : le pressing actif ou le rôle a pu changer depuis l'ouverture de la page. */
    public function hydrate(): void
    {
        $user = auth()->user();
        abort_unless($user->may(Permission::CashHistory) || $user->may(Permission::CashClose) || $user->may(Permission::CashExpenses), 403);
    }

    private function pressing(): Pressing
    {
        $pressing = auth()->user()->currentPressing();
        abort_if($pressing === null, 403);

        return $pressing;
    }

    private function selectedDay(): Carbon
    {
        // Sans l'historique, seule la journée en cours est visible.
        if (! auth()->user()->may(Permission::CashHistory)) {
            return today();
        }

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
        abort_unless(auth()->user()->may(Permission::CashClose), 403);
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
        $difference = $declared - $summary['expected_cash'];

        try {
            $this->storeClosing($pressing, $day, $summary, $declared, $difference);
        } catch (UniqueConstraintViolationException) {
            // Double clic ou deuxième onglet : la journée vient d'être clôturée.
            $this->toast('Cette journée vient déjà d\'être clôturée.', 'info');

            return;
        }

        $fcfa = fn (int $n) => number_format($n, 0, ',', ' ');
        ActivityLog::record($pressing->id, ActivityAction::CashClosed,
            'Caisse du '.$day->format('d/m/Y').' clôturée : '.$fcfa($declared).' F comptés pour '.$fcfa($summary['expected_cash']).' F attendus'
            .($summary['expenses_total'] > 0 ? ' (après '.$fcfa($summary['expenses_total']).' F de dépenses)' : '')
            .($difference !== 0 ? ' (écart '.($difference > 0 ? '+' : '').$fcfa($difference).' F)' : ''),
            null, ['difference_fcfa' => $difference, 'business_date' => $day->toDateString()]);

        $this->reset(['declaredCash', 'note']);
        $this->toast($difference === 0 ? 'Caisse clôturée : aucun écart.' : 'Caisse clôturée avec un écart de '.($difference > 0 ? '+' : '').$fcfa($difference).' F.', $difference === 0 ? 'success' : 'warning');
    }

    /** @param  array<string, mixed>  $summary */
    private function storeClosing(Pressing $pressing, CarbonInterface $day, array $summary, int $declared, int $difference): void
    {
        CashClosing::create([
            'pressing_id' => $pressing->id,
            'business_date' => $day->toDateString(),
            'totals_by_method' => collect($summary['by_method'])->map(fn ($m) => ['count' => $m['count'], 'total' => $m['total']])->all(),
            'total_fcfa' => $summary['total'],
            'cash_received_fcfa' => $summary['cash'],
            'expenses_fcfa' => $summary['expenses_total'],
            'expected_cash_fcfa' => $summary['expected_cash'],
            'declared_cash_fcfa' => $declared,
            'difference_fcfa' => $difference,
            'payments_count' => $summary['count'],
            'note' => trim($this->note) ?: null,
            'closed_by' => auth()->id(),
        ]);
    }

    /** Dépense payée avec les espèces de la caisse (achat de produits, transport…). */
    public function addExpense(): void
    {
        abort_unless(auth()->user()->may(Permission::CashExpenses), 403);
        $pressing = $this->pressing();
        $day = $this->selectedDay();

        if ($this->isClosed($pressing, $day)) {
            $this->toast('Cette journée est clôturée : rouvrez-la pour ajouter une dépense.', 'warning');

            return;
        }

        $this->expenseLabel = trim($this->expenseLabel);
        $this->validate([
            'expenseLabel' => ['required', 'string', 'max:150'],
            'expenseAmount' => ['required', 'integer', 'min:1', 'max:10000000'],
        ], [
            'expenseLabel.required' => 'Indiquez à quoi a servi cet argent.',
            'expenseAmount.required' => 'Indiquez le montant.',
            'expenseAmount.min' => 'Le montant doit être d\'au moins 1 F.',
        ]);

        $expense = CashExpense::create([
            'pressing_id' => $pressing->id,
            'business_date' => $day->toDateString(),
            'amount_fcfa' => (int) $this->expenseAmount,
            'label' => $this->expenseLabel,
            'recorded_by' => auth()->id(),
        ]);

        ActivityLog::record($pressing->id, ActivityAction::CashExpenseRecorded,
            'Dépense de '.number_format($expense->amount_fcfa, 0, ',', ' ').' F : '.$expense->label, null, ['expense_id' => $expense->id]);

        $this->reset(['expenseLabel', 'expenseAmount']);
        $this->toast('Dépense enregistrée.');
    }

    public function removeExpense(int $expenseId): void
    {
        abort_unless(auth()->user()->may(Permission::CashExpenses), 403);
        $pressing = $this->pressing();
        $expense = CashExpense::where('pressing_id', $pressing->id)->findOrFail($expenseId);

        if ($this->isClosed($pressing, $expense->business_date)) {
            $this->toast('Cette journée est clôturée : la dépense ne peut plus être supprimée.', 'warning');

            return;
        }

        $expense->delete();
        ActivityLog::record($pressing->id, ActivityAction::CashExpenseRemoved,
            'Dépense de '.number_format($expense->amount_fcfa, 0, ',', ' ').' F supprimée : '.$expense->label);
        $this->toast('Dépense supprimée.', 'warning');
    }

    private function isClosed(Pressing $pressing, Carbon $day): bool
    {
        return CashClosing::where('pressing_id', $pressing->id)->whereDate('business_date', $day)->exists();
    }

    public function reopen(): void
    {
        $pressing = $this->pressing();
        abort_unless(auth()->user()->isAdminOf($pressing), 403);
        $day = $this->selectedDay();

        $closing = CashClosing::where('pressing_id', $pressing->id)->whereDate('business_date', $day)->firstOrFail();
        $closing->delete();

        ActivityLog::record($pressing->id, ActivityAction::CashReopened, 'Clôture de caisse du '.$day->format('d/m/Y').' annulée (journée rouverte)', null, ['business_date' => $day->toDateString()]);
        $this->toast('Journée rouverte : vous pouvez la clôturer à nouveau.', 'warning');
    }

    #[Layout('layouts.dashboard', ['active' => 'cash', 'title' => 'Caisse'])]
    public function render()
    {
        $pressing = $this->pressing();
        $day = $this->selectedDay();
        $user = auth()->user();
        $canHistory = $user->may(Permission::CashHistory);
        $canClose = $user->may(Permission::CashClose);
        $summary = CashDaySummary::for($pressing, $day);

        return view('livewire.cash.index', [
            'pressing' => $pressing,
            'selectedDay' => $day,
            'summary' => $summary,
            'closing' => CashClosing::with('closer:id,name')->where('pressing_id', $pressing->id)->whereDate('business_date', $day)->first(),
            'history' => $canHistory ? CashClosing::with('closer:id,name')->where('pressing_id', $pressing->id)->orderByDesc('business_date')->take(14)->get() : collect(),
            'canReopen' => $user->isAdminOf($pressing),
            'canHistory' => $canHistory,
            'canClose' => $canClose,
            'canExpenses' => $user->may(Permission::CashExpenses),
        ]);
    }
}
