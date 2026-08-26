<?php

namespace App\Livewire\Orders;

use App\Enums\OrderIssueStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

class Show extends Component
{
    public Order $order;

    public ?string $errorMessage = null;

    public ?int $resolvingIssueId = null;

    public string $resolutionNote = '';

    public function mount(Order $order): void
    {
        abort_unless(auth()->user()->can('view', $order), 403);
        $this->order = $order;
    }

    public function transitionTo(string $status): void
    {
        $this->errorMessage = null;

        try {
            $this->order->update(['status' => OrderStatus::from($status)]);
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->order->refresh();
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
