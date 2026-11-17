<?php

namespace App\Livewire\Concerns;

use App\Models\Pressing;
use App\Models\SalesAgent;

/**
 * Pages de l'espace commercial : le commercial connecté (contrôlé par le
 * middleware EnsureSalesAgent) et ses seuls pressings.
 */
trait ActsAsSalesAgent
{
    protected function agent(): SalesAgent
    {
        $agent = auth()->user()?->salesAgent;
        abort_unless($agent !== null && $agent->isActive(), 403);

        return $agent;
    }

    /** Un pressing apporté par ce commercial ; sinon introuvable pour lui. */
    protected function ownPressing(int $pressingId): Pressing
    {
        return $this->agent()->pressings()->findOrFail($pressingId);
    }
}
