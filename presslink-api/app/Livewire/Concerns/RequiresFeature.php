<?php

namespace App\Livewire\Concerns;

use App\Enums\Feature;
use App\Enums\Permission;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Page réservée à une formule : si le pressing ne l'a pas, le propriétaire
 * arrive sur la page Abonnement, sur l'option qui la débloque ; les autres
 * membres de l'équipe reçoivent un refus.
 */
trait RequiresFeature
{
    protected function requireFeature(Feature $feature): void
    {
        $user = auth()->user();
        $pressing = $user->currentPressing();

        if ($pressing === null || $user->pressingHasFeature($pressing, $feature)) {
            return;
        }

        abort_unless($user->may(Permission::SubscriptionManage, $pressing), 403);

        throw new HttpResponseException(redirect()->route('subscription.show', ['option' => $feature->value]));
    }
}
