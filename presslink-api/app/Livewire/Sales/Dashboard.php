<?php

namespace App\Livewire\Sales;

use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\ActsAsSalesAgent;
use App\Models\SalesAgentClaim;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tableau de bord du commercial : ses pressings (inscrits, en mois offert,
 * abonnés), son code et son lien d'inscription à partager.
 */
class Dashboard extends Component
{
    use ActsAsSalesAgent;

    public function mount(): void
    {
        $this->agent();
    }

    #[Layout('layouts.sales', ['active' => 'dashboard', 'title' => 'Mon activité — PressLink'])]
    public function render(): View
    {
        $agent = $this->agent();
        $pressings = $agent->pressings()->with('subscription')->latest('sales_agent_attached_at')->get();

        $inTrial = $pressings->filter(fn ($pressing) => $pressing->subscription?->isInTrial())->count();
        $subscribed = $pressings->filter(fn ($pressing) => $pressing->subscription?->status === SubscriptionStatus::Active && ! $pressing->subscription->periodEnded())->count();

        return view('livewire.sales.dashboard', [
            'agent' => $agent,
            'stats' => [
                ['label' => 'Pressings inscrits', 'value' => $pressings->count()],
                ['label' => 'Ce mois-ci', 'value' => $pressings->filter(fn ($pressing) => $pressing->sales_agent_attached_at?->isCurrentMonth())->count()],
                ['label' => 'En mois offert', 'value' => $inTrial],
                ['label' => 'Abonnés', 'value' => $subscribed],
            ],
            'recent' => $pressings->take(5),
            'earnings' => [
                'thisMonth' => (int) $agent->commissions()->where('earned_at', '>=', now()->startOfMonth())->sum('amount_fcfa'),
                'unpaid' => (int) $agent->commissions()->unpaid()->sum('amount_fcfa'),
            ],
            'pendingClaims' => $agent->claims()->where('status', SalesAgentClaim::PENDING)->count(),
            'shareText' => 'Bonjour, voici le lien pour inscrire votre pressing sur PressLink (1 mois offert) : '.$agent->signupUrl(),
        ]);
    }
}
