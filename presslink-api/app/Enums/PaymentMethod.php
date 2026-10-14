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
}
