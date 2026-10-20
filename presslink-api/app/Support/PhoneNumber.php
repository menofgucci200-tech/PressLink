<?php

namespace App\Support;

/**
 * Numéros de téléphone ivoiriens : un seul format partout, « +225 » suivi
 * des 10 chiffres du numéro national (plan de numérotation de 2021).
 *
 * C'est ce format qui est stocké (clients, équipe, pressings) et qui servira
 * aux envois SMS et WhatsApp. Les mobiles commencent par 01 (Moov),
 * 05 (MTN) ou 07 (Orange) ; les fixes par 21, 25 ou 27.
 */
final class PhoneNumber
{
    public const PREFIX = '+225';

    public const NATIONAL_LENGTH = 10;

    /** @var list<string> */
    public const MOBILE_PREFIXES = ['01', '05', '07'];

    /** @var list<string> */
    public const LANDLINE_PREFIXES = ['21', '25', '27'];

    /**
     * Plage réservée aux comptes de démonstration (+225 00 00 00 00 xx) :
     * jamais attribuée à un abonné, donc jamais contactée par SMS.
     */
    public const DEMO_RANGE = '+22500000000';

    /**
     * Met une saisie au format stocké : « 07 08 12 44 00 », « 0708124400 »,
     * « +225 07 08 12 44 00 » ou « 00225… » donnent « +2250708124400 ».
     * Une saisie qui ne ressemble pas à un numéro ivoirien est renvoyée
     * nettoyée des espaces, pour que la validation la refuse.
     */
    public static function normalize(?string $input): string
    {
        $compact = preg_replace('/[^\d+]/', '', trim((string) $input)) ?? '';

        if (str_starts_with($compact, '+')) {
            // Un autre indicatif que +225 n'est pas un numéro ivoirien.
            if (! str_starts_with($compact, self::PREFIX)) {
                return $compact;
            }
            $digits = substr($compact, strlen(self::PREFIX));
        } elseif (str_starts_with($compact, '00225')) {
            $digits = substr($compact, 5);
        } elseif (str_starts_with($compact, '225') && strlen($compact) === 3 + self::NATIONAL_LENGTH) {
            $digits = substr($compact, 3);
        } else {
            $digits = $compact;
        }

        return preg_match('/^\d{'.self::NATIONAL_LENGTH.'}$/', $digits) === 1 ? self::PREFIX.$digits : $compact;
    }

    /** Le numéro (déjà normalisé) est-il un numéro ivoirien valide ? */
    public static function isValid(?string $phone, bool $mobileOnly = true): bool
    {
        if (self::isDemo($phone)) {
            return true;
        }

        $prefixes = $mobileOnly ? self::MOBILE_PREFIXES : [...self::MOBILE_PREFIXES, ...self::LANDLINE_PREFIXES];

        return preg_match('/^\+225('.implode('|', $prefixes).')\d{8}$/', (string) $phone) === 1;
    }

    /** Numéro d'un compte de démonstration (voir DEMO_RANGE). */
    public static function isDemo(?string $phone): bool
    {
        return preg_match('/^'.preg_quote(self::DEMO_RANGE, '/').'\d{2}$/', (string) $phone) === 1;
    }

    /** « +2250708124400 » → « 0708124400 » (ce que l'on tape au clavier). */
    public static function national(?string $phone): string
    {
        $phone = (string) $phone;

        return str_starts_with($phone, self::PREFIX) ? substr($phone, strlen(self::PREFIX)) : $phone;
    }

    /** « +2250708124400 » → « +225 07 08 12 44 00 » (affichage). */
    public static function format(?string $phone): string
    {
        $national = self::national($phone);
        if (preg_match('/^\d{10}$/', $national) !== 1) {
            return (string) $phone;
        }

        return self::PREFIX.' '.implode(' ', str_split($national, 2));
    }
}
