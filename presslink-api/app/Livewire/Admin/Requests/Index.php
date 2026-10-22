<?php

namespace App\Livewire\Admin\Requests;

use App\Livewire\Concerns\HandlesSubscriptionRequests;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\SubscriptionRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Toutes les demandes d'abonnement des pressings au même endroit : celles à
 * traiter (lien de paiement, activation, refus avec motif), et l'historique
 * des paiements reçus et des refus.
 */
class Index extends Component
{
    use HandlesSubscriptionRequests;
    use ShowsToasts;
    use WithPagination;

    public const TABS = ['a-traiter' => 'À traiter', 'paiements' => 'Paiements reçus', 'refusees' => 'Refusées'];

    #[Url(except: 'a-traiter')]
    public string $tab = 'a-traiter';

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'a-traiter';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'search'], true)) {
            $this->resetPage();
        }
    }

    protected function handleableRequests(): Builder
    {
        return SubscriptionRequest::query();
    }

    /** @return Builder<SubscriptionRequest> */
    private function filtered(): Builder
    {
        return SubscriptionRequest::query()
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->whereHas(
                'pressing',
                fn (Builder $pressing) => $pressing->where('name', 'like', '%'.trim($this->search).'%')->orWhere('code', 'like', '%'.trim($this->search).'%'),
            ));
    }

    #[Layout('layouts.admin', ['active' => 'requests', 'title' => 'Demandes et paiements'])]
    public function render(): View
    {
        $requests = match ($this->tab) {
            'paiements' => $this->filtered()->where('status', SubscriptionRequest::DONE)->with(['pressing', 'handler'])->latest('handled_at')->paginate(25),
            'refusees' => $this->filtered()->where('status', SubscriptionRequest::DECLINED)->with(['pressing', 'handler'])->latest('handled_at')->paginate(25),
            default => $this->filtered()->pending()->with(['pressing', 'requester'])->oldest()->paginate(25),
        };

        if ($this->tab === 'a-traiter') {
            $this->fillRequestFields($requests->items());
        }

        $paid = fn ($from, $to) => (int) SubscriptionRequest::where('status', SubscriptionRequest::DONE)
            ->whereBetween('handled_at', [$from, $to])
            ->sum('amount_fcfa');

        return view('livewire.admin.requests.index', [
            'requests' => $requests,
            'pendingCount' => SubscriptionRequest::pending()->count(),
            'totals' => [
                'Ce mois-ci' => $paid(now()->startOfMonth(), now()),
                'Mois dernier' => $paid(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()),
                'Cette année' => $paid(now()->startOfYear(), now()),
            ],
        ]);
    }
}
