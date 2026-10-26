<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Offre de lancement officiel : PressLink gratuit pour les pressings
 * jusqu'à une date (config presslink.launch.free_until, incluse).
 */
final class LaunchOffer
{
    /** Fin de l'offre : la date configurée, à 23 h 59 min 59 s. */
    public static function endsAt(): ?Carbon
    {
        $date = config('presslink.launch.free_until');

        return filled($date) ? Carbon::parse($date)->endOfDay() : null;
    }

    public static function isActive(): bool
    {
        $end = self::endsAt();

        return $end !== null && now()->lessThanOrEqualTo($end);
    }

    /** « 15 novembre » */
    public static function dateLabel(): string
    {
        return self::endsAt()?->translatedFormat('j F') ?? '';
    }

    /** Jours restants, aujourd'hui compris (le dernier jour vaut 1). */
    public static function daysLeft(): int
    {
        $end = self::endsAt();

        return $end === null ? 0 : max(0, (int) now()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
    }
}
