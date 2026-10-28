<?php

namespace App\Livewire\Subscription;

use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\SubscriptionPlan;
use App\Livewire\Concerns\RequestsSubscriptionChanges;
use App\Models\SubscriptionRequest;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Page Abonnement du dashboard pressing (RB-09) : formule, commandes du
 * mois, fonctions incluses et options, renouvellement. Chaque choix mène au
 * récapitulatif puis au paiement en ligne (GeniusPay) ; sans paiement en ligne,
 * l'équipe PressLink envoie un lien Wave puis active la demande. Comparer les
 * formules : page Formules.
 */
class Show extends Component
{
    use RequestsSubscriptionChanges;

    /** Option mise en avant (arrivée depuis une page réservée). */
    #[Url(except: '')]
    public string $option = '';

    public function mount(): void
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->may(Permission::SubscriptionManage, $pressing), 403);
    }

    #[Layout('layouts.dashboard', ['active' => 'subscription', 'title' => 'Abonnement'])]
    public function render()
    {
        $pressing = auth()->user()->currentPressing();
        $subscription = $pressing->subscription;

        return view('livewire.subscription.show', [
            'subscription' => $subscription,
            'features' => Feature::cases(),
            'plans' => SubscriptionPlan::cases(),
            'orderPacks' => SubscriptionRequest::orderPacks(),
            'pendingRequests' => $pressing->subscriptionRequests()->pending()->latest()->get(),
            // Refus récents (avec le motif de l'équipe PressLink) et paiements reçus.
            'declinedRequests' => $pressing->subscriptionRequests()->where('status', SubscriptionRequest::DECLINED)
                ->where('handled_at', '>=', now()->subDays(30))->latest('handled_at')->get(),
            'payments' => $pressing->subscriptionRequests()->where('status', SubscriptionRequest::DONE)
                ->where('amount_fcfa', '>', 0)->latest('handled_at')->take(12)->get(),
        ]);
    }
}
