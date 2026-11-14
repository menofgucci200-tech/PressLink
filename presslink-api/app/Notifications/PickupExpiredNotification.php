<?php

namespace App\Notifications;

class PickupExpiredNotification extends PickupRequestNotification
{
    public function title(): string
    {
        return 'Demande de ramassage expirée';
    }

    public function body(): string
    {
        return "{$this->pickupRequest->pressing->name} n'a pas répondu avant la fin du créneau. Vous pouvez envoyer une nouvelle demande.";
    }
}
