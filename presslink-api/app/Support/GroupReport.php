<?php

namespace App\Support;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Rapport mensuel d'un groupe de pressings : totaux, comparatif et
 * évolution, en PDF (téléchargement et envoi automatique chaque mois).
 */
class GroupReport
{
    /** Mois au format AAAA-MM ; mois en cours par défaut ou si invalide. */
    public static function month(?string $value): Carbon
    {
        try {
            $month = $value ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        return $month->gt(now()) ? now()->startOfMonth() : $month;
    }

    /** @return array<string, mixed> */
    public static function data(User $user, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth()->min(now()->endOfDay());
        $metrics = new GroupMetrics($user->managedPressings(), $start, $end);
        $rows = $metrics->perPressing();

        return [
            'user' => $user,
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'rows' => $rows,
            'totals' => $metrics->totals($rows),
            'chart' => $metrics->dailyRevenue(),
            'alerts' => $metrics->alerts($rows),
        ];
    }

    public static function pdf(User $user, Carbon $month): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('reports.group-monthly', self::data($user, $month))->setPaper('a4');
    }

    public static function filename(Carbon $month): string
    {
        return 'rapport-groupe-'.$month->format('Y-m').'.pdf';
    }
}
