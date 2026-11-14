<?php

namespace App\Notifications;

class PickupAcceptedNotification extends PickupRequestNotification
{
    public function title(): string
    {
        return $this->pickupRequest->rescheduled ? 'Ramassage reprogrammé' : 'Ramassage prévu';
    }

    public function body(): string
    {
        $pressing = $this->pickupRequest->pressing->name;
        $when = $this->pickupRequest->scheduleLabel();

        return $this->pickupRequest->rescheduled
            ? "{$pressing} passera chercher votre linge {$when}. Ce créneau ne vous convient pas ? Vous pouvez annuler la demande dans l'appli."
            : "{$pressing} passera chercher votre linge {$when}.";
    }
}
