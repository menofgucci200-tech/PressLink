<?php

namespace App\Notifications;

class OrderReadyNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Votre commande est prête !';
    }

    public function body(): string
    {
        return "La commande #{$this->order->order_number} est prête à être récupérée au {$this->order->pressing->name}.";
    }
}
