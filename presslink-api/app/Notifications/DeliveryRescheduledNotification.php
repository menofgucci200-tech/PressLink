<?php

namespace App\Notifications;

class DeliveryRescheduledNotification extends DeliveryNotification
{
    public function title(): string
    {
        return 'Livraison reprogrammée';
    }

    public function body(): string
    {
        return "{$this->order->pressing->name} vous livrera la commande #{$this->order->order_number} {$this->delivery->scheduleLabel()}. Ce créneau ne vous convient pas ? Vous pouvez annuler la livraison dans l'appli.";
    }
}
