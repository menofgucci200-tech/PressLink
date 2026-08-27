<?php

namespace App\Livewire\Orders;

use App\Enums\OrderIssueStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

class Show extends Component
{
    public Order $order;

    public ?string $errorMessage = null;

    public function mount(Order $order): void
    {
        abort_unless(auth()->user()->can('view', $order), 403);
        $this->order = $order;
    }

    /**
     * Verrou pessimiste (SELECT ... FOR UPDATE) : deux changements de
     * statut concurrents sur la même commande sérialisent sur ce verrou au
     * lieu de valider chacun contre son propre statut de départ en
     * mémoire, ce qui évitait un "lost update" (les deux transitions
     * acceptées, l'historique incohérent avec le statut final — voir
     * load-testing/RAPPORT.md, finding D).
     */
    public function transitionTo(string $status): void
    {
        $this->errorMessage = null;
        $target = OrderStatus::from($status);

        try {
            DB::transaction(function () use ($target) {
                $locked = Order::whereKey($this->order->id)->lockForUpdate()->firstOrFail();
                $locked->update(['status' => $target]);
            });
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->order->refresh();
    }

    public function resolveIssue(int $issueId): void
    {
        $issue = $this->order->issues()->findOrFail($issueId);
        $issue->update([
            'status' => OrderIssueStatus::Resolved,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);
    }

    #[Layout('layouts.dashboard', ['active' => 'orders', 'title' => 'Commande'])]
    public function render()
    {
        $this->order->load(['customer', 'items', 'statusHistory.changedBy', 'issues']);

        return view('livewire.orders.show', [
            'nextActions' => $this->order->status->allowedTransitions(),
        ]);
    }
}
