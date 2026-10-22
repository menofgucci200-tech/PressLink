<?php

namespace App\Support;

/**
 * Exports Excel / CSV : un texte saisi librement (nom d'un client dans
 * l'appli…) qui commence par « = », « + », « @ » ou « - » ne doit jamais
 * devenir une formule à l'ouverture du fichier. Il est préfixé d'une
 * apostrophe, qui le fait lire comme du texte. Les nombres ne sont pas touchés.
 */
final class SpreadsheetSafe
{
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $first = $value[0];
        $dangerous = in_array($first, ['=', '+', '@', "\t", "\r"], true)
            || ($first === '-' && ! is_numeric($value));

        return $dangerous ? "'".$value : $value;
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), $row);
    }
}
