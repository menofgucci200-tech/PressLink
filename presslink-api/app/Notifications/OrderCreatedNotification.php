<?php

namespace App\Notifications;

class OrderCreatedNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Commande enregistrée';
    }

    public function body(): string
    {
        return "Votre commande #{$this->order->order_number} a été enregistrée chez {$this->order->pressing->name}.";
    }
}
