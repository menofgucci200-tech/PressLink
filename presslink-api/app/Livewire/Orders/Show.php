<?php

namespace App\Livewire\Orders;

use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Enums\CancellationReason;
use App\Enums\OrderIssueStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

class Show extends Component
{
    use ShowsToasts;

    public Order $order;

    public ?string $errorMessage = null;

    public ?int $resolvingIssueId = null;

    public string $resolutionNote = '';

    /** Formulaire « Encaisser » (acompte complémentaire ou solde). */
    public bool $showPaymentForm = false;

    public string $paymentAmount = '';

    public string $paymentMethod = 'especes';

    /** Formulaire « Annuler la commande » : motif obligatoire, et sort de l'argent déjà versé. */
    public bool $showCancelForm = false;

    public string $cancelReason = '';

    public string $cancelNote = '';

    /** « refund » (rendu au client) ou « keep » (conservé), si le client a déjà payé. */
    public string $cancelMoney = '';

    public string $refundMethod = 'especes';

    public function mount(Order $order): void
    {
        abort_unless(auth()->user()->can('view', $order), 403);
        $this->order = $order;

        // Lien « Annuler… » de l'aperçu de la liste : le formulaire s'ouvre directement.
        if (request()->boolean('annuler') && CancelOrderAction::canCancel(auth()->user(), $order)) {
            $this->showCancelForm = true;
        }
    }

    public function openCancelForm(): void
    {
        abort_unless(CancelOrderAction::canCancel(auth()->user(), $this->order), 403);
        $this->resetErrorBag();
        $this->reset(['cancelReason', 'cancelNote', 'cancelMoney']);
        $this->showCancelForm = true;
    }

    public function closeCancelForm(): void
    {
        $this->showCancelForm = false;
        $this->resetErrorBag();
    }

    public function cancelOrder(CancelOrderAction $cancel): void
    {
        $this->errorMessage = null;
        $reasons = implode(',', array_column(CancellationReason::cases(), 'value'));
        $paid = $this->order->amount_paid_fcfa > 0;

        $this->validate([
            'cancelReason' => ['required', 'in:'.$reasons],
            'cancelNote' => [$this->cancelReason === CancellationReason::Other->value ? 'required' : 'nullable', 'string', 'max:500'],
            'cancelMoney' => [$paid ? 'required' : 'nullable', 'in:'.CancelOrderAction::REFUND.','.CancelOrderAction::KEEP],
            'refundMethod' => ['required', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
        ], [
            'cancelReason.required' => 'Choisissez le motif de l\'annulation.',
            'cancelNote.required' => 'Précisez le motif.',
            'cancelMoney.required' => 'Indiquez si l\'argent déjà versé est rendu ou conservé.',
        ]);

        try {
            $cancel->handle(
                $this->order,
                auth()->user(),
                CancellationReason::from($this->cancelReason),
                $this->cancelNote,
                $paid ? $this->cancelMoney : null,
                PaymentMethod::from($this->refundMethod),
            );
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->showCancelForm = false;
        $this->order->refresh();
        $this->toast('Commande annulée.', 'warning');
    }

    public function transitionTo(string $status, ChangeOrderStatusAction $changeStatus): void
    {
        $this->errorMessage = null;
        $target = OrderStatus::tryFrom($status);

        if ($target === null) {
            $this->errorMessage = 'Statut inconnu.';

            return;
        }

        try {
            $changeStatus->handle($this->order, $target);
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->order->refresh();
        $this->toast(ChangeOrderStatusAction::successMessage($target), $target === OrderStatus::Annulee ? 'warning' : 'success');
    }

    public function openPaymentForm(): void
    {
        $this->showPaymentForm = true;
        $this->paymentAmount = (string) $this->order->balance_due_fcfa;
        $this->resetErrorBag();
    }

    public function cancelPayment(): void
    {
        $this->showPaymentForm = false;
        $this->paymentAmount = '';
        $this->resetErrorBag();
    }

    /**
     * Enregistre un paiement (tout ou partie du reste à payer). Verrou sur
     * la commande : deux encaissements simultanés ne peuvent pas dépasser
     * le total.
     */
    public function recordPayment(): void
    {
        abort_unless(auth()->user()->can('update', $this->order), 403);

        $this->validate([
            'paymentAmount' => ['required', 'integer', 'min:1', 'max:'.max(1, $this->order->balance_due_fcfa)],
            'paymentMethod' => ['required', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
        ], [
            'paymentAmount.required' => 'Indiquez le montant encaissé.',
            'paymentAmount.integer' => 'Le montant doit être un nombre entier de francs.',
            'paymentAmount.min' => 'Le montant doit être supérieur à 0 F.',
            'paymentAmount.max' => 'Le montant ne peut pas dépasser le reste à payer ('.number_format($this->order->balance_due_fcfa, 0, ',', ' ').' F).',
        ]);

        $recorded = DB::transaction(function () {
            $locked = Order::whereKey($this->order->id)->lockForUpdate()->firstOrFail();
            $amount = (int) $this->paymentAmount;

            if ($locked->status === OrderStatus::Annulee || $amount > $locked->balance_due_fcfa) {
                return false;
            }

            $locked->recordPayment($amount, PaymentMethod::from($this->paymentMethod), auth()->id());

            return true;
        });

        $this->order->refresh();

        if (! $recorded) {
            $this->addError('paymentAmount', 'Ce paiement ne peut plus être enregistré : le reste à payer a changé. Vérifiez le montant.');

            return;
        }

        $this->cancelPayment();
        $this->toast($this->order->balance_due_fcfa === 0
            ? 'Paiement enregistré : la commande est soldée.'
            : 'Paiement enregistré. Reste à payer : '.number_format($this->order->balance_due_fcfa, 0, ',', ' ').' F.');
    }

    /** Remise au client avec encaissement du solde en une fois. */
    public function settleAndRecover(ChangeOrderStatusAction $changeStatus): void
    {
        abort_unless(auth()->user()->can('update', $this->order), 403);
        $this->errorMessage = null;

        try {
            $changeStatus->handle($this->order, OrderStatus::Recuperee, PaymentMethod::tryFrom($this->paymentMethod) ?? PaymentMethod::Cash);
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->order->refresh();
        $this->toast('Solde encaissé et commande remise au client.');
    }

    /** Correction d'une erreur de saisie : réservé aux gérants du pressing. */
    public function removePayment(int $paymentId): void
    {
        abort_unless(auth()->user()->can('delete', $this->order), 403);

        $this->order->payments()->findOrFail($paymentId)->delete();
        $this->order->refresh();
        $this->toast('Paiement supprimé. Reste à payer : '.number_format($this->order->balance_due_fcfa, 0, ',', ' ').' F.', 'warning');
    }

    public function startResolving(int $issueId): void
    {
        $this->resolvingIssueId = $issueId;
        $this->resolutionNote = '';
    }

    public function cancelResolving(): void
    {
        $this->resolvingIssueId = null;
        $this->resolutionNote = '';
    }

    public function confirmResolve(): void
    {
        $issue = $this->order->issues()->findOrFail($this->resolvingIssueId);
        $issue->update([
            'status' => OrderIssueStatus::Resolved,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'resolution_note' => trim($this->resolutionNote) ?: null,
        ]);

        $this->cancelResolving();
        $this->toast('Signalement marqué comme résolu.');
    }

    #[Layout('layouts.dashboard', ['active' => 'orders', 'title' => 'Commande'])]
    public function render()
    {
        $this->order->load(['customer', 'items', 'statusHistory.changedBy', 'issues', 'payments.recorder']);

        return view('livewire.orders.show', [
            'nextActions' => $this->order->status->allowedTransitions(),
            'paymentMethods' => PaymentMethod::cases(),
            'canRemovePayments' => auth()->user()->can('delete', $this->order),
            'canCancel' => CancelOrderAction::canCancel(auth()->user(), $this->order),
            'cancellationReasons' => CancellationReason::cases(),
        ]);
    }
}
