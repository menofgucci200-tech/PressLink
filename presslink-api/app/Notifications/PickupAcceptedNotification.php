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

        $fee = $this->pickupRequest->pickup_fee_fcfa > 0
            ? ' Frais de ramassage : '.number_format($this->pickupRequest->pickup_fee_fcfa, 0, ',', ' ').' F.'
            : '';

        return $this->pickupRequest->rescheduled
            ? "{$pressing} passera chercher votre linge {$when}.{$fee} Ce créneau ne vous convient pas ? Vous pouvez annuler la demande dans l'appli."
            : "{$pressing} passera chercher votre linge {$when}.{$fee}";
    }
}
