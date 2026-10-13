<?php

namespace App\Livewire\Admin;

use App\Actions\Demo\ResetDemoPressing;
use App\Enums\OrderIssueStatus;
use App\Enums\OrderStatus;
use App\Enums\PressingStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vue globale du back-office Super Admin — Phase 7 du plan de
 * développement (pressings actifs, clients, commandes), enrichie de
 * filtres (période, ville, administrateur), d'indicateurs business
 * (revenu récurrent, volume traité, tendances) et d'une liste « À
 * surveiller » pour agir avant qu'un pressing ne décroche.
 */
class Dashboard extends Component
{
    /** Un pressing actif sans commande depuis ce nombre de jours est « inactif ». */
    public const DORMANT_AFTER_DAYS = 14;

    /** Fenêtre d'alerte avant la fin d'un essai, en jours. */
    public const TRIAL_ENDING_WITHIN_DAYS = 7;

    /** Part du quota mensuel de commandes à partir de laquelle on alerte. */
    public const QUOTA_ALERT_RATIO = 0.8;

    public string $period = '30';

    public string $city = '';

    public string $administratorId = '';

    /**
     * Accès de démo affichés une seule fois après (ré)initialisation.
     *
     * @var array{password: string, code: string}|null
     */
    public ?array $demoAccess = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);
    }

    public function resetDemo(ResetDemoPressing $reset): void
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        $result = $reset->handle();
        $this->demoAccess = ['password' => $result['password'], 'code' => $result['pressing']->code];
    }

    public function resetFilters(): void
    {
        $this->reset(['period', 'city', 'administratorId']);
    }

    private function periodStart(): ?Carbon
    {
        return match ($this->period) {
            'today' => now()->startOfDay(),
            '7' => now()->subDays(7),
            '30' => now()->subDays(30),
            'month' => now()->startOfMonth(),
            default => null,
        };
    }

    /** Début de la période précédente de même durée (pour les tendances). */
    private function previousPeriodStart(?Carbon $since): ?Carbon
    {
        if ($since === null) {
            return null;
        }

        return $since->copy()->subSeconds((int) $since->diffInSeconds(now()));
    }

    /** @return Builder<Pressing> */
    private function filteredPressings(): Builder
    {
        // Le pressing de démo (DM-0001) n'entre jamais dans les statistiques.
        $query = Pressing::query()->withoutDemo();

        if ($this->city !== '') {
            $query->where('city', $this->city);
        }

        if ($this->administratorId !== '') {
            $administratorId = $this->administratorId;
            $query->whereHas('staff', fn ($q) => $q->where('users.id', $administratorId)
                ->where('pressing_users.role', 'admin')
                ->where('pressing_users.is_active', true));
        }

        return $query;
    }

    #[Layout('layouts.admin', ['active' => 'dashboard', 'title' => 'Vue globale'])]
    public function render()
    {
        $pressingsQuery = $this->filteredPressings();
        // Sous-requête plutôt qu'une liste d'identifiants : la base fait le
        // filtrage, quel que soit le nombre de pressings.
        $pressingIds = (clone $pressingsQuery)->select('pressings.id');

        $since = $this->periodStart();
        $previousSince = $this->previousPeriodStart($since);

        $orders = fn () => Order::query()->whereIn('pressing_id', $pressingIds);
        $inPeriod = fn (Builder $q) => $since ? $q->where('created_at', '>=', $since) : $q;
        $inPreviousPeriod = fn (Builder $q) => $q->where('created_at', '>=', $previousSince)->where('created_at', '<', $since);
        // Le volume traité ignore les commandes annulées.
        $volume = fn (Builder $q) => (int) $q->where('status', '!=', OrderStatus::Annulee->value)->sum('total_fcfa');

        $totalOrders = $inPeriod($orders())->count();
        $orderVolume = $volume($inPeriod($orders()));
        $previousOrders = $previousSince ? $inPreviousPeriod($orders())->count() : null;
        $previousVolume = $previousSince ? $volume($inPreviousPeriod($orders())) : null;

        $customers = fn () => Customer::whereHas('pressings', fn ($q) => $q->whereIn('pressings.id', $pressingIds));

        $subscriptions = Subscription::whereIn('pressing_id', $pressingIds)->with('pressing:id,name,status')->get();

        return view('livewire.admin.dashboard', [
            'activePressings' => (clone $pressingsQuery)->where('status', PressingStatus::Active)->count(),
            'suspendedPressings' => (clone $pressingsQuery)->where('status', PressingStatus::Suspended)->count(),
            'newPressings' => $since ? (clone $pressingsQuery)->where('created_at', '>=', $since)->count() : null,
            'totalClients' => $customers()->count(),
            'newClients' => $since ? $customers()->where('customers.created_at', '>=', $since)->count() : null,
            'totalOrders' => $totalOrders,
            'ordersChangePct' => $this->changePct($totalOrders, $previousOrders),
            'orderVolume' => $orderVolume,
            'volumeChangePct' => $this->changePct($orderVolume, $previousVolume),
            'monthlyRecurringRevenue' => $this->monthlyRecurringRevenue($subscriptions),
            'payingPressings' => $subscriptions->where('status', SubscriptionStatus::Active)->count(),
            'planBreakdown' => $this->planBreakdown($subscriptions),
            'statusBreakdown' => $this->statusBreakdown($subscriptions),
            'chart' => $this->ordersChart($orders(), $since),
            'watchlist' => $this->watchlist($pressingsQuery, $pressingIds, $subscriptions),
            'topPressings' => (clone $pressingsQuery)
                ->withCount(['orders as period_orders_count' => $inPeriod])
                ->withSum(['orders as period_volume' => fn (Builder $q) => $inPeriod($q)->where('status', '!=', OrderStatus::Annulee->value)], 'total_fcfa')
                ->orderByDesc('period_orders_count')
                ->take(5)
                ->get()
                ->filter(fn (Pressing $p) => $p->period_orders_count > 0)
                ->values(),
            'recentPressings' => (clone $pressingsQuery)->with('subscription')->latest()->take(5)->get(),
            'cities' => Pressing::withoutDemo()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'administrators' => User::whereHas('pressings', fn ($q) => $q->withoutDemo()->where('pressing_users.role', 'admin')
                ->where('pressing_users.is_active', true))->orderBy('name')->get(),
            'demoExists' => ResetDemoPressing::exists(),
        ]);
    }

    private function changePct(int $current, ?int $previous): ?int
    {
        if ($previous === null || $previous === 0) {
            return null;
        }

        return (int) round(($current - $previous) / $previous * 100);
    }

    /**
     * Revenu récurrent mensuel (FCFA) : abonnements actifs uniquement, les
     * formules annuelles ramenées au mois. Les essais n'y entrent pas.
     *
     * @param  Collection<int, Subscription>  $subscriptions
     */
    private function monthlyRecurringRevenue(Collection $subscriptions): int
    {
        return (int) round($subscriptions
            ->where('status', SubscriptionStatus::Active)
            ->sum(fn (Subscription $s) => $s->billing_cycle === 'annual'
                ? ($s->plan->annualPriceFcfa() ?? 0) / 12
                : ($s->plan->monthlyPriceFcfa() ?? 0)));
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @return list<array{label: string, count: int}>
     */
    private function planBreakdown(Collection $subscriptions): array
    {
        return array_map(fn (SubscriptionPlan $plan) => [
            'label' => $plan->label(),
            'count' => $subscriptions->filter(fn (Subscription $s) => $s->plan === $plan)->count(),
        ], SubscriptionPlan::cases());
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @return list<array{key: string, label: string, count: int}>
     */
    private function statusBreakdown(Collection $subscriptions): array
    {
        return array_map(fn (SubscriptionStatus $status) => [
            'key' => $status->value,
            'label' => $status->label(),
            'count' => $subscriptions->where('status', $status)->count(),
        ], SubscriptionStatus::cases());
    }

    /**
     * Commandes créées par tranche (jour, semaine ou mois selon la durée
     * couverte) — une seule requête groupée par jour, regroupée ensuite.
     *
     * @param  Builder<Order>  $orders
     * @return array{bars: list<array{label: string, count: int}>, max: int}
     */
    private function ordersChart(Builder $orders, ?Carbon $since): array
    {
        $perDay = (clone $orders)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $start = ($since ?? ($perDay->keys()->min() ? Carbon::parse($perDay->keys()->min()) : now()))->copy()->startOfDay();
        $end = now()->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;

        [$step, $format] = match (true) {
            $days <= 31 => ['day', 'd/m'],
            $days <= 120 => ['week', 'd/m'],
            default => ['month', 'm/Y'],
        };

        $bars = [];
        $cursor = match ($step) {
            'week' => $start->copy()->startOfWeek(),
            'month' => $start->copy()->startOfMonth(),
            default => $start->copy(),
        };

        while ($cursor->lte($end)) {
            $next = $cursor->copy()->add(1, $step);
            $count = 0;
            for ($day = $cursor->copy(); $day->lt($next) && $day->lte($end); $day->addDay()) {
                $count += (int) ($perDay[$day->toDateString()] ?? 0);
            }
            $bars[] = ['label' => $cursor->format($format), 'count' => $count];
            $cursor = $next;
        }

        return ['bars' => $bars, 'max' => max(1, ...array_column($bars, 'count'))];
    }

    /**
     * Pressings qui demandent une action du Super Admin.
     *
     * @param  Builder<Pressing>  $pressingsQuery
     * @param  Builder<Pressing>  $pressingIds
     * @param  Collection<int, Subscription>  $subscriptions
     * @return list<array{key: string, label: string, hint: string, tone: string, pressings: Collection<int, Pressing>}>
     */
    private function watchlist(Builder $pressingsQuery, Builder $pressingIds, Collection $subscriptions): array
    {
        $live = $subscriptions->filter(fn (Subscription $s) => $s->pressing?->status === PressingStatus::Active);
        $pressingsOf = fn (Collection $subs) => $subs->pluck('pressing')->filter()->values();

        $trialEndsBy = now()->addDays(self::TRIAL_ENDING_WITHIN_DAYS);

        $items = [
            [
                'key' => 'expired',
                'label' => 'Abonnements expirés',
                'hint' => 'Essai terminé ou abonnement expiré : ces pressings ne peuvent plus créer de commande.',
                'tone' => 'error',
                'pressings' => $pressingsOf($live->filter(fn (Subscription $s) => $s->status === SubscriptionStatus::Expired
                    || ($s->status === SubscriptionStatus::Trialing && $s->trial_ends_at?->isPast()))),
            ],
            [
                'key' => 'trial-ending',
                'label' => 'Essais qui se terminent',
                'hint' => 'Fin d\'essai dans les '.self::TRIAL_ENDING_WITHIN_DAYS.' prochains jours : le moment de proposer une formule.',
                'tone' => 'warning',
                'pressings' => $pressingsOf($live->filter(fn (Subscription $s) => $s->status === SubscriptionStatus::Trialing
                    && $s->trial_ends_at !== null && $s->trial_ends_at->isFuture() && $s->trial_ends_at->lte($trialEndsBy))),
            ],
            [
                'key' => 'quota',
                'label' => 'Quota de commandes presque atteint',
                'hint' => 'Au moins '.(int) (self::QUOTA_ALERT_RATIO * 100).' % du quota utilisé : proposer la formule supérieure.',
                'tone' => 'warning',
                'pressings' => $pressingsOf($live->filter(fn (Subscription $s) => $s->orders_limit
                    && $s->orders_used >= $s->orders_limit * self::QUOTA_ALERT_RATIO)),
            ],
            [
                'key' => 'setup-fee',
                'label' => 'Tickets d\'entrée non réglés',
                'hint' => 'Abonnement actif mais ticket d\'entrée toujours en attente.',
                'tone' => 'info',
                'pressings' => $pressingsOf($live->filter(fn (Subscription $s) => $s->status === SubscriptionStatus::Active
                    && ! $s->isSetupFeePaid())),
            ],
            [
                'key' => 'dormant',
                'label' => 'Pressings inactifs',
                'hint' => 'Aucune commande depuis '.self::DORMANT_AFTER_DAYS.' jours : un appel peut éviter un départ.',
                'tone' => 'info',
                'pressings' => (clone $pressingsQuery)
                    ->where('status', PressingStatus::Active)
                    ->where('created_at', '<=', now()->subDays(self::DORMANT_AFTER_DAYS))
                    ->whereDoesntHave('orders', fn ($q) => $q->where('created_at', '>=', now()->subDays(self::DORMANT_AFTER_DAYS)))
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ];

        $openIssues = OrderIssue::where('status', OrderIssueStatus::Open->value)
            ->whereHas('order', fn ($q) => $q->whereIn('pressing_id', $pressingIds))
            ->with('order.pressing:id,name')
            ->get();

        $items[] = [
            'key' => 'issues',
            'label' => 'Signalements clients ouverts',
            'hint' => 'Signalements pas encore traités par les pressings.',
            'tone' => 'error',
            'pressings' => $openIssues->pluck('order.pressing')->filter()->unique('id')->values(),
            'count' => $openIssues->count(),
        ];

        return array_map(fn (array $item) => $item + ['count' => $item['pressings']->count()], $items);
    }
}
