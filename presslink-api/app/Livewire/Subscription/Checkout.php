<?php

namespace App\Livewire\Subscription;

use App\Enums\Permission;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\SubscriptionRequest;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Récapitulatif avant paiement : ce que le pressing achète, le montant et les
 * dates exactes, puis « Payer » (page GeniusPay). Toutes les demandes payables
 * en ligne y passent : formule, renouvellement, option, commandes en plus.
 */
class Checkout extends Component
{
    use ShowsToasts;

    public SubscriptionRequest $subscriptionRequest;

    /** Retour d'un paiement non confirmé (échec, abandon ou confirmation en attente). */
    #[Url(except: false)]
    public bool $echec = false;

    public function mount(SubscriptionRequest $subscriptionRequest): void
    {
        $this->authorizeRequest($subscriptionRequest);

        if (! $subscriptionRequest->isPending()) {
            $this->flashToast($subscriptionRequest->status === SubscriptionRequest::DONE
                ? 'Cette demande est déjà payée et activée.'
                : 'Cette demande a été refusée : vous pouvez en faire une nouvelle.', 'info');
            $this->redirectRoute('subscription.show', navigate: true);

            return;
        }

        // La déduction des jours restants baisse chaque jour : montant à jour.
        $subscriptionRequest->refreshPlanChangeAmount();
    }

    public function pay(): void
    {
        $request = $this->subscriptionRequest->fresh();
        $this->authorizeRequest($request);

        if (! $request->canBePaidOnline()) {
            $this->toast('Cette demande ne peut pas être payée en ligne pour le moment.', 'warning');

            return;
        }

        try {
            $checkoutUrl = $request->startOnlinePayment(route('subscription.payment.return', $request));
        } catch (RuntimeException $e) {
            Log::error($e->getMessage(), ['request' => $request->id]);
            $this->toast('Le paiement en ligne est indisponible pour le moment. Réessayez dans quelques minutes.', 'error');

            return;
        }

        $this->redirect($checkoutUrl);
    }

    public function cancel(): void
    {
        $request = $this->subscriptionRequest->fresh();
        $this->authorizeRequest($request);

        if ($request->isPending() && $request->hasOnlinePaymentInProgress()) {
            $this->toast('Un paiement en ligne est en cours pour cette demande : elle ne peut pas être annulée pour l\'instant.', 'warning');

            return;
        }

        if ($request->isPending()) {
            $request->delete();
        }

        $this->flashToast('Demande annulée.', 'info');
        $this->redirectRoute('subscription.show', navigate: true);
    }

    private function authorizeRequest(?SubscriptionRequest $request): void
    {
        $user = auth()->user();
        $pressing = $user->currentPressing();

        abort_unless($request && $pressing && $request->pressing_id === $pressing->id && $user->may(Permission::SubscriptionManage, $pressing), 403);
    }

    #[Layout('layouts.dashboard', ['active' => 'subscription', 'title' => 'Paiement'])]
    public function render()
    {
        $request = $this->subscriptionRequest;

        $subscription = $request->pressing->subscription;
        $planPrice = $request->kind === SubscriptionRequest::KIND_PLAN && $request->plan !== null
            ? ($request->cycle === SubscriptionRequest::ANNUAL ? $request->plan->annualPriceFcfa() : $request->plan->monthlyPriceFcfa())
            : null;
        $credit = $planPrice !== null && $request->amount_fcfa !== null ? max(0, $planPrice - $request->amount_fcfa) : 0;

        return view('livewire.subscription.checkout', [
            'request' => $request,
            'subscription' => $subscription,
            'period' => $request->periodAfterPayment(),
            'planPrice' => $planPrice,
            'credit' => $credit,
            // Jours restants qui valent plus que le prix choisi (montée au mois depuis l'année, par exemple).
            'creditCapped' => $credit > 0 && SubscriptionRequest::unusedCredit($subscription) > $credit,
            'upgrade' => $request->isPlanUpgrade(),
        ]);
    }
}
