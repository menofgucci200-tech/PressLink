<?php

namespace App\Notifications;

/** Dépôt rapide complété : le client découvre le détail et le prix de sa commande. */
class OrderDetailsCompletedNotification extends OrderNotification
{
    public function title(): string
    {
        return 'Détail de votre commande';
    }

    public function body(): string
    {
        return "Le détail de votre commande #{$this->order->order_number} est disponible : ".number_format((int) $this->order->total_fcfa, 0, ',', ' ').' F.';
    }

    protected function sendsEmail(): bool
    {
        return true;
    }
}
