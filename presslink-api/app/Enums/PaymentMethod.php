<?php

namespace App\Enums;

/**
 * Moyens de paiement courants dans les pressings en Côte d'Ivoire.
 */
enum PaymentMethod: string
{
    case Cash = 'especes';
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';
    case MtnMomo = 'mtn_momo';
    case MoovMoney = 'moov_money';
    case Card = 'carte';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Espèces',
            self::Wave => 'Wave',
            self::OrangeMoney => 'Orange Money',
            self::MtnMomo => 'MTN MoMo',
            self::MoovMoney => 'Moov Money',
            self::Card => 'Carte bancaire',
        };
    }

    /**
     * Logo de l'opérateur (public/images/paiement) ; les espèces et la carte sont dessinées.
     */
    public function logo(): ?string
    {
        return match ($this) {
            self::Wave => 'images/paiement/wave.webp',
            self::OrangeMoney => 'images/paiement/orange-money.webp',
            self::MtnMomo => 'images/paiement/mtn-momo.webp',
            self::MoovMoney => 'images/paiement/moov-money.webp',
            self::Cash, self::Card => null,
        };
    }
}
