<?php

namespace App\Livewire\Subscription;

use App\Enums\Permission;
use App\Livewire\Concerns\ShowsToasts;
use App\Models\SubscriptionRequest;
use App\Rules\IvorianPhone;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    /** Moyen de paiement choisi sur le récapitulatif (code de SubscriptionRequest::ONLINE_METHODS). */
    public string $method = 'wave';

    /** Numéro du payeur, demandé pour Orange Money, MTN et Moov (prérempli). */
    public string $phone = '';

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

        // Le moyen déjà choisi pour cette demande, et le numéro connu du pressing.
        if (array_key_exists((string) $subscriptionRequest->payment_method, SubscriptionRequest::ONLINE_METHODS)) {
            $this->method = $subscriptionRequest->payment_method;
        }
        $this->phone = PhoneNumber::national(auth()->user()->phone ?: $subscriptionRequest->pressing->phone);
    }

    public function pay(): void
    {
        $request = $this->subscriptionRequest->fresh();
        $this->authorizeRequest($request);

        if (! $request->canBePaidOnline()) {
            $this->toast('Cette demande ne peut pas être payée en ligne pour le moment.', 'warning');

            return;
        }

        $this->resetErrorBag();
        $needsPhone = in_array($this->method, SubscriptionRequest::PHONE_METHODS, true);
        // Le numéro est vérifié au format international ; le champ garde la saisie du pressing.
        Validator::make(
            ['method' => $this->method, 'phone' => $this->phone === '' ? '' : PhoneNumber::normalize($this->phone)],
            [
                'method' => ['required', Rule::in(array_keys(SubscriptionRequest::ONLINE_METHODS))],
                'phone' => $needsPhone ? ['required', new IvorianPhone] : ['nullable'],
            ],
            ['phone.required' => 'Indiquez le numéro '.(SubscriptionRequest::ONLINE_METHODS[$this->method] ?? 'Mobile Money').' qui va payer.'],
        )->validate();

        try {
            $checkoutUrl = $request->startOnlinePayment(
                route('subscription.payment.return', $request),
                $this->method,
                $needsPhone ? PhoneNumber::normalize($this->phone) : null,
            );
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
