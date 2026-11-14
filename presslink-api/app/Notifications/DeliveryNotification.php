<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderDelivery;

/**
 * Suivi d'une livraison dans l'appli client : comme les notifications de
 * commande, elle ouvre la commande livrée.
 */
abstract class DeliveryNotification extends OrderNotification
{
    public function __construct(Order $order, protected OrderDelivery $delivery)
    {
        parent::__construct($order);
    }
}
