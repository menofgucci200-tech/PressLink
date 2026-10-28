<?php

namespace App\Livewire\Subscription;

use App\Enums\Permission;
use App\Enums\SubscriptionPlan;
use App\Livewire\Concerns\RequestsSubscriptionChanges;
use App\Models\SubscriptionRequest;
use App\Support\PlanCatalog;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Page Formules de l'espace pressing : les trois formules côte à côte, la
 * formule actuelle, ce que le pressing gagne à passer au-dessus, et la
 * demande de changement : payée en ligne depuis le récapitulatif (GeniusPay),
 * sinon par lien Wave envoyé puis activée par PressLink.
 */
class Plans extends Component
{
    use RequestsSubscriptionChanges;

    /** Durée payée en cas de changement de formule : au mois ou à l'année. */
    public string $cycle = SubscriptionRequest::MONTHLY;

    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->may(Permission::SubscriptionManage, $pressing), 403);
    }

    #[Layout('layouts.dashboard', ['active' => 'subscription', 'title' => 'Formules'])]
    public function render()
    {
        $subscription = auth()->user()->currentPressing()->subscription;
        $current = $subscription?->plan;
        $next = $current === null ? null : (SubscriptionPlan::cases()[array_search($current, SubscriptionPlan::cases(), true) + 1] ?? null);

        return view('livewire.subscription.plans', [
            'plans' => PlanCatalog::plans(),
            'current' => $current,
            'subscription' => $subscription,
            'next' => $next,
            'gains' => $current && $next ? PlanCatalog::gains($current, $next) : null,
            'pendingPlans' => $subscription
                ? $subscription->pressing->subscriptionRequests()->pending()->where('kind', SubscriptionRequest::KIND_PLAN)->pluck('plan')->map(fn ($p) => $p?->value)->filter()->all()
                : [],
        ]);
    }
}
