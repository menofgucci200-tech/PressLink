<?php

namespace App\Notifications;

class OrderRecoveredNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Commande récupérée';
    }

    public function body(): string
    {
        return "Votre commande #{$this->order->order_number} a été récupérée. Merci de votre confiance !";
    }
}
