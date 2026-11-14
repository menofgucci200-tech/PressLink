<?php

namespace App\Notifications;

class OrderRecoveredNotification extends OrderNotification
{
    public function title(): string
    {
        return $this->order->wasDelivered() ? 'Commande livrée' : 'Commande récupérée';
    }

    public function body(): string
    {
        return $this->order->wasDelivered()
            ? "Votre commande #{$this->order->order_number} vous a été livrée. Merci de votre confiance !"
            : "Votre commande #{$this->order->order_number} a été récupérée. Merci de votre confiance !";
    }
}
