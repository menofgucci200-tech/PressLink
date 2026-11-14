<?php

namespace App\Notifications;

class PickupMissedNotification extends PickupRequestNotification
{
    public function title(): string
    {
        return 'Ramassage manqué';
    }

    public function body(): string
    {
        return "{$this->pickupRequest->pressing->name} est passé chercher votre linge mais n'a trouvé personne. Vous pouvez envoyer une nouvelle demande dans l'appli.";
    }
}
