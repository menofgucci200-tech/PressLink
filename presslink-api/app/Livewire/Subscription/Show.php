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
 * mois, fonctions incluses et options, renouvellement. Le pressing DEMANDE ;
 * l'équipe PressLink lui envoie un lien de paiement (Wave), puis active la
 * demande depuis la fiche du pressing. Comparer les formules : page Formules.
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
        ]);
    }
}
