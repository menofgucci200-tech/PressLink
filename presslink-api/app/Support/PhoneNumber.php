<?php

namespace App\Support;

/**
 * Numéros ivoiriens (10 chiffres depuis 2021) : mobiles 01, 05, 07 et fixes
 * 21, 25, 27. Les formulaires acceptent la saisie libre (« 07 00 00 00 00 »,
 * « 27.22.41.18.35 », « 00225… », « +225 … ») et la ramènent au format
 * stocké : +225 suivi des 10 chiffres.
 */
class PhoneNumber
{
    public const RULE = 'regex:/^\+225[02][0-9]{9}$/';

    public static function normalize(?string $phone): string
    {
        $phone = preg_replace('/[\s.\-()\/]/u', '', (string) $phone) ?? '';

        return match (true) {
            preg_match('/^[02][0-9]{9}$/', $phone) === 1 => '+225'.$phone,
            preg_match('/^00225[02][0-9]{9}$/', $phone) === 1 => '+'.substr($phone, 2),
            preg_match('/^225[02][0-9]{9}$/', $phone) === 1 => '+'.$phone,
            default => $phone,
        };
    }
}
