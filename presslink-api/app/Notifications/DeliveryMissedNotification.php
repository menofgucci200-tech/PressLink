<?php

namespace App\Notifications;

class DeliveryMissedNotification extends DeliveryNotification
{
    public function title(): string
    {
        return 'Livraison manquée';
    }

    public function body(): string
    {
        return "{$this->order->pressing->name} est passé vous livrer la commande #{$this->order->order_number} mais n'a trouvé personne. Choisissez un autre créneau dans l'appli, ou passez la chercher au pressing.";
    }
}
