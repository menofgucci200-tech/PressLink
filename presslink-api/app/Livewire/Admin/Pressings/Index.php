<?php

namespace App\Livewire\Admin\Pressings;

use App\Enums\PressingStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\Pressing;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste des pressings — Super Admin. Filtres et tri partagés avec
 * l'export CSV (voir {@see self::filteredQuery()}).
 */
class Index extends Component
{
    use ShowsToasts, WithPagination;

    /** Fenêtre (jours) de la colonne « Commandes récentes ». */
    public const RECENT_DAYS = 30;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $plan = '';

    #[Url(as: 'abonnement', except: '')]
    public string $subscriptionStatus = '';

    #[Url(as: 'ville', except: '')]
    public string $city = '';

    #[Url(as: 'tri', except: 'recent')]
    public string $sort = 'recent';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'plan', 'subscriptionStatus', 'city', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'plan', 'subscriptionStatus', 'city', 'sort']);
        $this->resetPage();
    }

    public function toggleStatus(int $pressingId): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $pressing = Pressing::findOrFail($pressingId);

        $pressing->update([
            'status' => $pressing->status === PressingStatus::Active
                ? PressingStatus::Suspended
                : PressingStatus::Active,
        ]);

        $this->toast($pressing->status === PressingStatus::Active
            ? "{$pressing->name} est réactivé."
            : "{$pressing->name} est suspendu.", $pressing->status === PressingStatus::Active ? 'success' : 'warning');
    }

    /** @return array<string, string> */
    public function filters(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'plan' => $this->plan,
            'subscription_status' => $this->subscriptionStatus,
            'city' => $this->city,
            'sort' => $this->sort,
        ];
    }

    /**
     * @param  array<string, ?string>  $filters
     * @return Builder<Pressing>
     */
    public static function filteredQuery(array $filters): Builder
    {
        // Les pressings de test des visiteurs ont leur propre encart (vue globale).
        $query = Pressing::withoutSandboxes()
            ->with('subscription')
            ->withCount(['staff', 'customers', 'orders'])
            ->withCount(['orders as recent_orders_count' => fn ($q) => $q->where('created_at', '>=', now()->subDays(self::RECENT_DAYS))])
            ->withMax('orders as last_order_at', 'created_at');

        if ($term = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"));
        }

        if ($status = $filters['status'] ?? '') {
            $query->where('status', $status);
        }

        if ($plan = $filters['plan'] ?? '') {
            $query->whereHas('subscription', fn ($q) => $q->where('plan', $plan));
        }

        if ($subscriptionStatus = $filters['subscription_status'] ?? '') {
            $subscriptionStatus === 'none'
                ? $query->whereDoesntHave('subscription')
                : $query->whereHas('subscription', fn ($q) => $q->where('status', $subscriptionStatus));
        }

        if ($city = $filters['city'] ?? '') {
            $query->where('city', $city);
        }

        match ($filters['sort'] ?? 'recent') {
            'name' => $query->orderBy('name'),
            'activity' => $query->orderByDesc('recent_orders_count')->orderBy('name'),
            'last_order' => $query->orderByRaw('last_order_at IS NULL')->orderByDesc('last_order_at'),
            default => $query->latest()->orderByDesc('id'),
        };

        return $query;
    }

    #[Layout('layouts.admin', ['active' => 'pressings', 'title' => 'Pressings'])]
    public function render()
    {
        return view('livewire.admin.pressings.index', [
            'pressings' => self::filteredQuery($this->filters())->paginate(15),
            'statusCounts' => Pressing::withoutDemo()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'subscriptionCounts' => Pressing::withoutDemo()->join('subscriptions', 'subscriptions.pressing_id', '=', 'pressings.id')
                ->selectRaw('subscriptions.status, COUNT(*) as aggregate')->groupBy('subscriptions.status')->pluck('aggregate', 'status'),
            'cities' => Pressing::withoutSandboxes()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'subscriptionStatuses' => SubscriptionStatus::cases(),
            'hasFilters' => collect($this->filters())->except('sort')->filter()->isNotEmpty() || $this->sort !== 'recent',
        ]);
    }
}
