<?php

namespace App\Livewire\Concerns;

use App\Models\SubscriptionRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * Traitement des demandes d'abonnement par l'équipe PressLink (fiche d'un
 * pressing, page « Demandes et paiements ») : montant et lien de paiement
 * Wave, activation une fois payé, refus avec un motif montré au pressing.
 *
 * Le composant doit utiliser ShowsToasts.
 */
trait HandlesSubscriptionRequests
{
    /** @var array<int, string> montant à payer de chaque demande en attente */
    public array $requestAmounts = [];

    /** @var array<int, string> lien de paiement (Wave Business) de chaque demande en attente */
    public array $requestLinks = [];

    /** @var array<int, string> motif de refus saisi pour chaque demande */
    public array $declineReasons = [];

    /**
     * Demandes que cette page peut traiter.
     *
     * @return Builder<SubscriptionRequest>|HasMany<SubscriptionRequest, *>
     */
    abstract protected function handleableRequests(): Builder|HasMany;

    /** Après une activation (l'abonnement a changé). */
    protected function requestApplied(SubscriptionRequest $request): void {}

    private function findPendingRequest(int $requestId): SubscriptionRequest
    {
        abort_unless(auth()->user()->is_super_admin, 403);

        return $this->handleableRequests()->pending()->findOrFail($requestId);
    }

    /** Demande payée : on active ce qui a été demandé. */
    public function applyRequest(int $requestId): void
    {
        $request = $this->findPendingRequest($requestId);
        try {
            $request->apply();
        } catch (RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->requestApplied($request);
        $this->toast("Activé : {$request->label()}.");
    }

    /** Montant et lien de paiement d'une demande, montrés au pressing sur sa page Abonnement. */
    public function savePaymentLink(int $requestId): void
    {
        $request = $this->findPendingRequest($requestId);

        $this->validate([
            "requestAmounts.{$requestId}" => ['nullable', 'integer', 'min:0'],
            "requestLinks.{$requestId}" => ['nullable', 'url:https', 'max:500'],
        ], [
            "requestLinks.{$requestId}.url" => 'Collez le lien complet (il commence par https://).',
        ]);

        $amount = $this->requestAmounts[$requestId] ?? '';
        $request->update([
            'amount_fcfa' => $amount === '' ? null : (int) $amount,
            'payment_link' => ($this->requestLinks[$requestId] ?? '') ?: null,
        ]);
        $this->toast('Lien de paiement enregistré : le pressing le voit sur sa page Abonnement.');
    }

    /** Refus, avec un motif facultatif montré au pressing. */
    public function declineRequest(int $requestId): void
    {
        $request = $this->findPendingRequest($requestId);

        $this->validate(["declineReasons.{$requestId}" => ['nullable', 'string', 'max:500']], [], [
            "declineReasons.{$requestId}" => 'motif',
        ]);

        $request->decline($this->declineReasons[$requestId] ?? null);
        unset($this->declineReasons[$requestId]);
        $this->toast('Demande refusée.'.($request->decline_reason ? ' Le pressing voit le motif sur sa page Abonnement.' : ''));
    }

    /**
     * Pré-remplit montant et lien des demandes affichées.
     *
     * @param  iterable<SubscriptionRequest>  $requests
     */
    private function fillRequestFields(iterable $requests): void
    {
        foreach ($requests as $request) {
            $this->requestAmounts[$request->id] ??= $request->amount_fcfa !== null ? (string) $request->amount_fcfa : '';
            $this->requestLinks[$request->id] ??= (string) $request->payment_link;
        }
    }
}
