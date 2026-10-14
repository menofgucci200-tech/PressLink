<?php

namespace App\Enums;

/**
 * État du règlement d'une commande, déduit du total et des paiements
 * enregistrés : tout payé, acompte versé, ou tout à payer au retrait.
 */
enum PaymentStatus: string
{
    case Paid = 'payee';
    case Partial = 'acompte';
    case Unpaid = 'a_payer';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Payée',
            self::Partial => 'Acompte versé',
            self::Unpaid => 'À payer au retrait',
        };
    }

    public static function for(int $totalFcfa, int $amountPaidFcfa): self
    {
        return match (true) {
            $amountPaidFcfa >= $totalFcfa => self::Paid,
            $amountPaidFcfa > 0 => self::Partial,
            default => self::Unpaid,
        };
    }
}
