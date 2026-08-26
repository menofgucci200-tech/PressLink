<?php

namespace App\Livewire\Issues;

use App\Enums\OrderIssueStatus;
use App\Models\OrderIssue;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Signalements clients — traiter les problèmes remontés depuis l'app
 * (article manquant, article qui n'appartient pas au client, etc.).
 */
class Index extends Component
{
    use WithPagination;

    public string $status = 'open';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function resolveIssue(int $issueId): void
    {
        $pressing = auth()->user()->currentPressing();

        $issue = OrderIssue::whereHas('order', fn ($q) => $q->where('pressing_id', $pressing?->id))
            ->findOrFail($issueId);

        $issue->update([
            'status' => OrderIssueStatus::Resolved,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);
    }

    #[Layout('layouts.dashboard', ['active' => 'issues', 'title' => 'Signalements'])]
    public function render()
    {
        $pressing = auth()->user()->currentPressing();

        $issues = collect();
        $openCount = 0;

        if ($pressing !== null) {
            $query = OrderIssue::whereHas('order', fn ($q) => $q->where('pressing_id', $pressing->id))
                ->with(['order', 'customer', 'resolvedBy'])
                ->latest();

            if ($this->status !== '') {
                $query->where('status', $this->status);
            }

            $issues = $query->paginate(15);

            $openCount = OrderIssue::whereHas('order', fn ($q) => $q->where('pressing_id', $pressing->id))
                ->where('status', OrderIssueStatus::Open->value)
                ->count();
        }

        return view('livewire.issues.index', [
            'issues' => $issues,
            'openCount' => $openCount,
        ]);
    }
}
