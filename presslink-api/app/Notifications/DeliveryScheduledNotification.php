<?php

namespace App\Notifications;

class DeliveryScheduledNotification extends DeliveryNotification
{
    public function title(): string
    {
        return 'Livraison programmée';
    }

    public function body(): string
    {
        return "Votre commande #{$this->order->order_number} est prête : {$this->order->pressing->name} vous la livrera {$this->delivery->scheduleLabel()} ({$this->delivery->place()}). Ce créneau ne vous convient pas ? Vous pouvez annuler la livraison dans l'appli.";
    }
}
