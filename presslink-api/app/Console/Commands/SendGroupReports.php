<?php

namespace App\Console\Commands;

use App\Mail\GroupMonthlyReport;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie le rapport du mois écoulé aux propriétaires de groupe qui l'ont
 * demandé (Rapports du groupe > Envoi automatique). Planifié le 1er de
 * chaque mois (routes/console.php).
 */
class SendGroupReports extends Command
{
    protected $signature = 'presslink:send-group-reports
        {--month= : Mois au format AAAA-MM (par défaut : le mois précédent)}';

    protected $description = 'Envoie le rapport mensuel PDF aux propriétaires de groupes de pressings.';

    public function handle(): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', (string) $this->option('month'))->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $sent = 0;
        User::where('group_report_opt_in', true)->whereNotNull('email')->each(function (User $user) use ($month, &$sent) {
            if (! $user->managesGroup()) {
                return;
            }

            Mail::to($user->email)->queue(new GroupMonthlyReport($user, $month));
            $sent++;
        });

        $this->info("{$sent} rapport(s) mis en file d'envoi pour ".$month->format('m/Y').'.');

        return self::SUCCESS;
    }
}
