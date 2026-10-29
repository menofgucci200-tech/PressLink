<?php

namespace App\Livewire\Admin\Payments;

use App\Models\PaymentAttempt;
use App\Models\Pressing;
use App\Models\SubscriptionRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Historique des paiements — Super Admin : chaque paiement reçu ou tenté
 * (en ligne avec GeniusPay, ou enregistré par l'équipe), réussi ou échoué,
 * avec sa référence PressLink, pour retrouver un paiement en quelques
 * secondes et suivre le taux de réussite.
 */
class Index extends Component
{
    use WithPagination;

    /** Filtres de statut proposés : clé d'URL => statuts. */
    public const STATUS_FILTERS = [
        'paye' => [PaymentAttempt::PAID],
        'echoue' => PaymentAttempt::FAILURES,
        'en-cours' => [PaymentAttempt::PENDING],
    ];

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'moyen', except: '')]
    public string $method = '';

    #[Url(as: 'pressing', except: '')]
    public string $pressingId = '';

    #[Url(as: 'du', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'au', except: '')]
    public string $dateTo = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'method', 'pressingId', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'method', 'pressingId', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    /**
     * Tous les filtres sauf le statut (appliqué à part, pour garder les compteurs).
     *
     * @return Builder<PaymentAttempt>
     */
    private function filteredQuery(): Builder
    {
        $query = PaymentAttempt::query();

        if ($term = trim($this->search)) {
            $query->where(fn (Builder $q) => $q->where('reference', 'like', "%{$term}%")
                ->orWhere('gateway_reference', 'like', "%{$term}%")
                ->orWhere('pressing_name', 'like', "%{$term}%"));
        }
        if ($this->method === 'manuel') {
            $query->where('channel', PaymentAttempt::MANUAL);
        } elseif ($this->method !== '') {
            $query->where('payment_method', $this->method);
        }
        if ($this->pressingId !== '') {
            $query->where('pressing_id', (int) $this->pressingId);
        }
        if ($this->dateFrom !== '' && ($from = rescue(fn () => Carbon::parse($this->dateFrom)->startOfDay(), null, false))) {
            $query->where('started_at', '>=', $from);
        }
        if ($this->dateTo !== '' && ($to = rescue(fn () => Carbon::parse($this->dateTo)->endOfDay(), null, false))) {
            $query->where('started_at', '<=', $to);
        }

        return $query;
    }

    #[Layout('layouts.admin', ['active' => 'payments', 'title' => 'Paiements'])]
    public function render()
    {
        $filtered = $this->filteredQuery();
        $counts = (clone $filtered)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $paid = (int) ($counts[PaymentAttempt::PAID] ?? 0);
        $failed = (int) collect(PaymentAttempt::FAILURES)->sum(fn (string $status) => $counts[$status] ?? 0);

        $list = clone $filtered;
        if (isset(self::STATUS_FILTERS[$this->status])) {
            $list->whereIn('status', self::STATUS_FILTERS[$this->status]);
        }

        return view('livewire.admin.payments.index', [
            'payments' => $list->with('subscriptionRequest')->latest('started_at')->orderByDesc('id')->paginate(25),
            'summary' => [
                'all' => (int) $counts->sum(),
                'paye' => $paid,
                'echoue' => $failed,
                'en-cours' => (int) ($counts[PaymentAttempt::PENDING] ?? 0),
                'rate' => $paid + $failed > 0 ? round($paid / ($paid + $failed) * 100, 1) : null,
                'collected' => (int) (clone $filtered)->paid()->sum(DB::raw('COALESCE(paid_amount_fcfa, total_fcfa)')),
            ],
            'methods' => SubscriptionRequest::ONLINE_METHODS + ['manuel' => 'Enregistré par l\'équipe'],
            'pressings' => Pressing::withoutSandboxes()->orderBy('name')->get(['id', 'name']),
            'hasFilters' => $this->search !== '' || $this->status !== '' || $this->method !== '' || $this->pressingId !== '' || $this->dateFrom !== '' || $this->dateTo !== '',
        ]);
    }
}
