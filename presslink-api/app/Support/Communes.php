<?php

namespace App\Support;

/**
 * Communes et villes proposées pour le ramassage et la livraison : le
 * pressing coche celles qu'il dessert, le client choisit la sienne pour
 * chaque adresse. Une liste fermée, pour que « Cocody » et « cocody »
 * désignent toujours la même zone.
 */
class Communes
{
    public const ABIDJAN = [
        'Abobo', 'Adjamé', 'Anyama', 'Attécoubé', 'Bingerville', 'Cocody', 'Koumassi',
        'Marcory', 'Plateau', 'Port-Bouët', 'Songon', 'Treichville', 'Yopougon',
    ];

    public const OTHER_CITIES = [
        'Abengourou', 'Aboisso', 'Agboville', 'Assinie', 'Bonoua', 'Bouaké', 'Dabou', 'Daloa',
        'Divo', 'Gagnoa', 'Grand-Bassam', 'Jacqueville', 'Korhogo', 'Man', 'San-Pédro',
        'Soubré', 'Yamoussoukro',
    ];

    /** @return array<string, list<string>> */
    public static function grouped(): array
    {
        return ['Abidjan' => self::ABIDJAN, 'Autres villes' => self::OTHER_CITIES];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [...self::ABIDJAN, ...self::OTHER_CITIES];
    }
}
