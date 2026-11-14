<?php

namespace App\Enums;

/**
 * Livraison d'une commande prête, demandée par le client dans l'appli :
 * prévue sur le créneau choisi (le pressing peut la reprogrammer), puis
 * livrée et payée à la porte, manquée (client absent) ou annulée.
 */
enum OrderDeliveryStatus: string
{
    case Scheduled = 'scheduled';
    case Delivered = 'delivered';
    case Missed = 'missed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Livraison prévue',
            self::Delivered => 'Livrée',
            self::Missed => 'Livraison manquée',
            self::Cancelled => 'Livraison annulée',
        };
    }
}
