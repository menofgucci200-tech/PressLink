<?php

namespace App\Livewire\Subscription;

use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Page Abonnement du dashboard pressing — Phase 3 (RB-09).
 *
 * Lecture seule au MVP : changement de plan et paiement restent manuels,
 * gérés hors application (cf. plan de développement Phase 6/7).
 */
class Show extends Component
{
    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->isAdminOf($pressing), 403);
    }

    #[Layout('layouts.dashboard', ['active' => 'subscription', 'title' => 'Abonnement'])]
    public function render()
    {
        $pressing = auth()->user()->currentPressing();
        $subscription = $pressing->subscription;

        return view('livewire.subscription.show', [
            'subscription' => $subscription,
        ]);
    }
}
