<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hébergement mutualisé sans worker persistant : on vide la queue via cron,
// par tranches de 50s max, toutes les minutes (voir routes/console.php + cron LWS).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

// Rapport mensuel des groupes de pressings, le 1er du mois au matin.
Schedule::command('presslink:send-group-reports')->monthlyOn(1, '07:00');

// Rappels de fin d'essai gratuit (3 jours puis 1 jour avant).
Schedule::command('presslink:trial-reminders')->dailyAt('09:00');
