<?php

namespace App\Support;

/**
 * Couleurs des vêtements : celles qu'on rencontre tous les jours sont proposées
 * d'un geste ; pour les autres, la teinte choisie au sélecteur est ramenée au nom
 * de couleur le plus proche (« Bleu ciel », « Bordeaux »…), lisible sur le reçu.
 */
final class ColorPalette
{
    public const MULTICOLOR = 'Multicolore';

    /** Couleurs proposées en un geste. */
    public const PRESETS = [
        'Blanc' => '#FFFFFF',
        'Noir' => '#111111',
        'Gris' => '#9CA3AF',
        'Rouge' => '#DC2626',
        'Orange' => '#F97316',
        'Jaune' => '#FACC15',
        'Vert' => '#16A34A',
        'Bleu' => '#2563EB',
        'Rose' => '#EC4899',
        'Violet' => '#7C3AED',
        'Marron' => '#78350F',
        'Beige' => '#E7D3B0',
    ];

    /** Teintes supplémentaires, pour nommer une couleur choisie au sélecteur. */
    private const EXTRA = [
        'Gris clair' => '#E5E7EB',
        'Gris foncé' => '#4B5563',
        'Bleu ciel' => '#7DD3FC',
        'Bleu marine' => '#1E3A8A',
        'Turquoise' => '#14B8A6',
        'Vert clair' => '#86EFAC',
        'Vert foncé' => '#14532D',
        'Kaki' => '#8A8A4B',
        'Jaune moutarde' => '#CA8A04',
        'Rose clair' => '#FBCFE8',
        'Fuchsia' => '#C026D3',
        'Bordeaux' => '#7F1D1D',
        'Lilas' => '#C4B5FD',
        'Crème' => '#FEF3C7',
        'Marron clair' => '#B45309',
    ];

    /** @return array<string, string> nom => code hexadécimal */
    public static function presets(): array
    {
        return self::PRESETS;
    }

    /** @return array<string, string> toutes les teintes nommées (comptoir hors connexion) */
    public static function all(): array
    {
        return self::PRESETS + self::EXTRA;
    }

    /** Code hexadécimal d'une couleur connue (pour l'afficher), sinon null. */
    public static function hex(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return self::PRESETS[$name] ?? self::EXTRA[$name] ?? null;
    }

    /** Nom de la couleur la plus proche d'un code « #RRGGBB » ; null si le code est invalide. */
    public static function nameFor(string $hex): ?string
    {
        if (! preg_match('/^#?([0-9a-f]{6})$/i', trim($hex), $m)) {
            return null;
        }

        [$r, $g, $b] = self::rgb($m[1]);
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach (self::PRESETS + self::EXTRA as $name => $candidate) {
            [$cr, $cg, $cb] = self::rgb(ltrim($candidate, '#'));
            // Distance « de moindre coût » qui pondère le rouge selon sa moyenne (plus proche de l'œil qu'une distance brute).
            $meanRed = ($r + $cr) / 2;
            $distance = (2 + $meanRed / 256) * ($r - $cr) ** 2 + 4 * ($g - $cg) ** 2 + (2 + (255 - $meanRed) / 256) * ($b - $cb) ** 2;

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $name;
            }
        }

        return $best;
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
