<?php

namespace App\Livewire\Concerns;

use App\Actions\Subscriptions\RequestSubscriptionChange;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\SubscriptionPlan;
use App\Models\Pressing;
use App\Models\SubscriptionRequest;
use App\Support\GeniusPay;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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

    /** Ouvre la page de paiement GeniusPay de la demande (Wave, Orange Money, MTN, carte). */
    public function payOnline(int $requestId): void
    {
        $request = $this->subscriptionPressing()->subscriptionRequests()->pending()->findOrFail($requestId);
        abort_unless($request->canBePaidOnline(), 422);

        try {
            $checkoutUrl = $request->startOnlinePayment(route('subscription.payment.return', $request));
        } catch (RuntimeException $e) {
            Log::error($e->getMessage(), ['request' => $request->id]);
            $this->toast('Le paiement en ligne est indisponible pour le moment. Réessayez dans quelques minutes.', 'error');

            return;
        }

        $this->redirect($checkoutUrl);
    }

    private function sendRequest(Pressing $pressing, string $kind, ?Feature $feature = null, ?SubscriptionPlan $plan = null, ?int $orders = null, ?string $cycle = null): void
    {
        $request = (new RequestSubscriptionChange)->handle($pressing, auth()->user(), $kind, $feature, $plan, $orders, $cycle);

        $online = $request?->canBePaidOnline() ?? false;

        $this->toast(match (true) {
            $request === null => 'Cette demande est déjà envoyée : vous la retrouvez dans « Demandes en cours ».',
            $online => 'Demande envoyée : payez-la en ligne dans « Demandes en cours », elle s\'active dès le paiement confirmé.',
            default => 'Demande envoyée : l\'équipe PressLink vous envoie un lien de paiement Wave, puis active votre demande.',
        }, $request === null ? 'info' : 'success');
    }
}
