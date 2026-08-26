<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case Expired = 'expired';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Essai',
            self::Active => 'Actif',
            self::Expired => 'Expiré',
            self::Canceled => 'Annulé',
        };
    }

    /** RB-09 : un abonnement dans cet état permet-il de créer une commande ? */
    public function allowsOrderCreation(): bool
    {
        return match ($this) {
            self::Trialing, self::Active => true,
            self::Expired, self::Canceled => false,
        };
    }
}
