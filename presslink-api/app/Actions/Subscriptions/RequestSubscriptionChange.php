<?php

namespace App\Actions\Subscriptions;

use App\Enums\Feature;
use App\Enums\SubscriptionPlan;
use App\Http\Controllers\SitePageController;
use App\Mail\SubscriptionRequested;
use App\Models\Pressing;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Support\GeniusPay;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Demande d'un pressing (option, commandes en plus, autre formule,
 * renouvellement), avec le montant à payer d'après les prix en vigueur, et
 * l'e-mail qui prévient l'équipe PressLink. Une demande identique déjà en
 * attente n'est pas dupliquée (sauf commandes en plus).
 *
 * Avec le paiement en ligne (GeniusPay), la demande identique en attente est
 * reprise (mise à jour si le pressing passe du mois à l'année) pour qu'il la
 * paie ; l'équipe n'est prévenue que si elle doit intervenir (prix non fixé).
 *
 * @return SubscriptionRequest|null null si une demande identique attend déjà (sans paiement en ligne)
 */
class RequestSubscriptionChange
{
    public function handle(Pressing $pressing, User $user, string $kind, ?Feature $feature = null, ?SubscriptionPlan $plan = null, ?int $orders = null, ?string $cycle = null): ?SubscriptionRequest
    {
        $subscription = $pressing->subscription;
        if ($kind === SubscriptionRequest::KIND_RENEWAL) {
            $plan = $subscription?->plan;
        }
        $cycle = in_array($kind, [SubscriptionRequest::KIND_PLAN, SubscriptionRequest::KIND_RENEWAL], true)
            ? ($cycle === SubscriptionRequest::ANNUAL ? SubscriptionRequest::ANNUAL : SubscriptionRequest::MONTHLY)
            : null;

        $amount = SubscriptionRequest::priceFor($kind, $subscription, $feature, $plan, $orders, $cycle);

        $alreadyPending = $kind === SubscriptionRequest::KIND_ORDERS ? null : $pressing->subscriptionRequests()->pending()
            ->where('kind', $kind)
            ->when($feature !== null, fn ($q) => $q->where('feature', $feature->value))
            ->when($kind === SubscriptionRequest::KIND_PLAN, fn ($q) => $q->where('plan', $plan?->value))
            ->latest('id')
            ->first();

        if ($alreadyPending !== null) {
            if (! GeniusPay::isConfigured()) {
                return null;
            }

            // Pas de paiement en cours : la demande suit le dernier choix (mois ou année).
            if (! $alreadyPending->hasOnlinePaymentInProgress() && $alreadyPending->cycle !== $cycle) {
                $alreadyPending->update(['cycle' => $cycle, 'amount_fcfa' => $amount]);
            }

            return $alreadyPending;
        }

        $request = $pressing->subscriptionRequests()->create([
            'requested_by' => $user->id,
            'kind' => $kind,
            'feature' => $feature,
            'plan' => $plan,
            'extra_orders' => $orders,
            'cycle' => $cycle,
            'amount_fcfa' => $amount,
            'status' => SubscriptionRequest::PENDING,
        ]);

        if (! $request->canBePaidOnline() && SitePageController::canSendMail()) {
            try {
                Mail::to(config('presslink.support.email'))->send(new SubscriptionRequested($request));
            } catch (Throwable $e) {
                Log::warning('Demande d\'abonnement : e-mail non envoyé', ['request' => $request->id, 'error' => $e->getMessage()]);
            }
        }

        return $request;
    }
}
