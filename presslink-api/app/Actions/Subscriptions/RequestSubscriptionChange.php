<?php

namespace App\Actions\Subscriptions;

use App\Enums\Feature;
use App\Enums\SubscriptionPlan;
use App\Http\Controllers\SitePageController;
use App\Mail\SubscriptionRequested;
use App\Models\Pressing;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Demande d'un pressing (option, commandes en plus, autre formule,
 * renouvellement), avec le montant à payer d'après les prix en vigueur, et
 * l'e-mail qui prévient l'équipe PressLink. Une demande identique déjà en
 * attente n'est pas dupliquée (sauf commandes en plus).
 *
 * @return SubscriptionRequest|null null si une demande identique attend déjà
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

        $alreadyPending = $pressing->subscriptionRequests()->pending()
            ->where('kind', $kind)
            ->when($feature !== null, fn ($q) => $q->where('feature', $feature->value))
            ->when($kind === SubscriptionRequest::KIND_PLAN, fn ($q) => $q->where('plan', $plan?->value))
            ->exists();

        if ($alreadyPending && $kind !== SubscriptionRequest::KIND_ORDERS) {
            return null;
        }

        $request = $pressing->subscriptionRequests()->create([
            'requested_by' => $user->id,
            'kind' => $kind,
            'feature' => $feature,
            'plan' => $plan,
            'extra_orders' => $orders,
            'cycle' => $cycle,
            'amount_fcfa' => SubscriptionRequest::priceFor($kind, $subscription, $feature, $plan, $orders, $cycle),
            'status' => SubscriptionRequest::PENDING,
        ]);

        if (SitePageController::canSendMail()) {
            try {
                Mail::to(config('presslink.support.email'))->send(new SubscriptionRequested($request));
            } catch (Throwable $e) {
                Log::warning('Demande d\'abonnement : e-mail non envoyé', ['request' => $request->id, 'error' => $e->getMessage()]);
            }
        }

        return $request;
    }
}
