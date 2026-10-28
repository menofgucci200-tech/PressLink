<?php

namespace App\Livewire\Concerns;

use App\Actions\Subscriptions\RequestSubscriptionChange;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\SubscriptionPlan;
use App\Models\Pressing;
use App\Models\SubscriptionRequest;

/**
 * Demandes faites depuis les pages Abonnement et Formules : option, commandes
 * en plus, autre formule, renouvellement. Le pressing paie en ligne (GeniusPay)
 * et la demande s'active d'elle-même ; sans paiement en ligne, l'équipe
 * PressLink répond avec un lien de paiement, puis active la demande une fois payée.
 */
trait RequestsSubscriptionChanges
{
    use ShowsToasts;

    protected function subscriptionPressing(): Pressing
    {
        $pressing = auth()->user()->currentPressing();
        abort_unless($pressing && auth()->user()->may(Permission::SubscriptionManage, $pressing) && $pressing->subscription, 403);

        return $pressing;
    }

    public function requestOption(string $feature): void
    {
        $pressing = $this->subscriptionPressing();
        $feature = Feature::tryFrom($feature);
        abort_if($feature === null, 422);

        if ($pressing->subscription->hasAddon($feature) || $pressing->subscription->plan->includes($feature)) {
            return;
        }

        $this->sendRequest($pressing, SubscriptionRequest::KIND_OPTION, feature: $feature);
    }

    public function requestOrders(int $count): void
    {
        $pressing = $this->subscriptionPressing();
        abort_unless(in_array($count, array_column(SubscriptionRequest::orderPacks(), 'count'), true), 422);

        $this->sendRequest($pressing, SubscriptionRequest::KIND_ORDERS, orders: $count);
    }

    public function requestPlan(string $plan, string $cycle = SubscriptionRequest::MONTHLY): void
    {
        $pressing = $this->subscriptionPressing();
        $plan = SubscriptionPlan::tryFrom($plan);
        abort_if($plan === null || $plan === $pressing->subscription->plan, 422);

        $this->sendRequest($pressing, SubscriptionRequest::KIND_PLAN, plan: $plan, cycle: $cycle);
    }

    public function requestRenewal(string $cycle = SubscriptionRequest::MONTHLY): void
    {
        $this->sendRequest($this->subscriptionPressing(), SubscriptionRequest::KIND_RENEWAL, cycle: $cycle);
    }

    public function cancelRequest(int $requestId): void
    {
        $request = $this->subscriptionPressing()->subscriptionRequests()->pending()->findOrFail($requestId);

        // Un paiement en ligne peut encore aboutir : la demande doit rester là pour être activée.
        if ($request->hasOnlinePaymentInProgress()) {
            $this->toast('Un paiement en ligne est en cours pour cette demande : elle ne peut pas être annulée pour l\'instant.', 'warning');

            return;
        }

        $request->delete();
        $this->toast('Demande annulée.');
    }

    private function sendRequest(Pressing $pressing, string $kind, ?Feature $feature = null, ?SubscriptionPlan $plan = null, ?int $orders = null, ?string $cycle = null): void
    {
        $request = (new RequestSubscriptionChange)->handle($pressing, auth()->user(), $kind, $feature, $plan, $orders, $cycle);

        // Paiement en ligne : directement au récapitulatif, puis à la page de paiement.
        if ($request?->canBePaidOnline()) {
            $this->redirectRoute('subscription.checkout', $request, navigate: true);

            return;
        }

        $this->toast($request === null || ! $request->wasRecentlyCreated
            ? 'Cette demande est déjà envoyée : l\'équipe PressLink vous envoie le lien de paiement.'
            : 'Demande envoyée : l\'équipe PressLink vous envoie un lien de paiement Wave, puis active votre demande.', $request === null || ! $request->wasRecentlyCreated ? 'info' : 'success');
    }
}
