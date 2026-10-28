<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\SubscriptionRequest;
use App\Support\GeniusPay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paiement en ligne des demandes d'abonnement par GeniusPay : la notification
 * signée envoyée par GeniusPay (webhook) et le retour du pressing après la
 * page de paiement. Les deux activent la demande une seule fois.
 */
class GeniusPayController extends Controller
{
    /** Notification GeniusPay : seule une signature valide est prise en compte. */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! GeniusPay::hasValidSignature($payload, $request->header('X-Webhook-Timestamp'), $request->header('X-Webhook-Signature'))) {
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $event = json_decode($payload, true);
        $transaction = is_array($event) ? ($event['data']['transaction'] ?? null) : null;

        if (! is_array($transaction) || ! in_array($event['event'] ?? null, ['payment.success', 'payment.failed', 'payment.cancelled'], true)) {
            return response()->json(['received' => true]);
        }

        $subscriptionRequest = $this->requestFor($transaction);

        if ($event['event'] !== 'payment.success') {
            $subscriptionRequest?->forgetFailedOnlinePayment($transaction['reference'] ?? null);

            return response()->json(['received' => true]);
        }

        if ($subscriptionRequest === null) {
            Log::warning('GeniusPay : paiement reçu sans demande correspondante.', ['reference' => $transaction['reference'] ?? null]);

            return response()->json(['received' => true]);
        }

        $subscriptionRequest->confirmOnlinePayment($transaction, $event['data']['environment'] ?? $request->header('X-Webhook-Environment'));

        return response()->json(['received' => true]);
    }

    /**
     * Retour du pressing depuis la page de paiement : on demande à GeniusPay où
     * en est le paiement (sans attendre la notification). Payé : page Abonnement ;
     * sinon, retour au récapitulatif pour réessayer.
     */
    public function return(SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        $user = auth()->user();
        $pressing = $user->currentPressing();
        abort_unless($pressing && $subscriptionRequest->pressing_id === $pressing->id && $user->may(Permission::SubscriptionManage, $pressing), 403);

        if ($subscriptionRequest->isPending() && $subscriptionRequest->payment_reference) {
            $transaction = GeniusPay::findPayment($subscriptionRequest->payment_reference);
            if (in_array($transaction['status'] ?? null, ['failed', 'cancelled', 'expired'], true)) {
                $subscriptionRequest->forgetFailedOnlinePayment($transaction['reference'] ?? $subscriptionRequest->payment_reference);
            } elseif ($transaction !== null) {
                $subscriptionRequest->confirmOnlinePayment($transaction);
            }
        }

        $subscriptionRequest->refresh();

        // Pas encore confirmé (échec, abandon, ou confirmation en route) : retour au récapitulatif.
        if ($subscriptionRequest->paid_online_at === null && $subscriptionRequest->isPending()) {
            return redirect()->route('subscription.checkout', ['subscriptionRequest' => $subscriptionRequest, 'echec' => 1]);
        }

        [$type, $message] = $subscriptionRequest->isPayment()
            ? ['success', 'Paiement reçu : « '.$subscriptionRequest->label().' » est activé.']
            : ['info', 'Paiement reçu : l\'équipe PressLink finalise votre demande.'];

        return redirect()->route('subscription.show')->with('toast', ['type' => $type, 'message' => $message]);
    }

    /**
     * La demande payée : d'après la référence de la transaction, ou d'après
     * l'identifiant transmis dans les métadonnées (paiement sur une ancienne page).
     *
     * @param  array<string, mixed>  $transaction
     */
    private function requestFor(array $transaction): ?SubscriptionRequest
    {
        if (filled($transaction['reference'] ?? null)) {
            $found = SubscriptionRequest::where('payment_reference', $transaction['reference'])->first();
            if ($found !== null) {
                return $found;
            }
        }

        $id = $transaction['metadata']['subscription_request_id'] ?? null;

        return is_numeric($id) ? SubscriptionRequest::find((int) $id) : null;
    }
}
