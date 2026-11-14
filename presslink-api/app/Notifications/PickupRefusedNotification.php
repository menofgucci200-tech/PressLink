<?php

namespace App\Notifications;

class PickupRefusedNotification extends PickupRequestNotification
{
    public function title(): string
    {
        return 'Ramassage non disponible';
    }

    public function body(): string
    {
        $reason = $this->pickupRequest->refusal_reason;

        return "{$this->pickupRequest->pressing->name} ne peut pas passer chercher votre linge"
            .($reason ? " : {$reason}" : '').'. Vous pouvez choisir un autre créneau.';
    }
}
