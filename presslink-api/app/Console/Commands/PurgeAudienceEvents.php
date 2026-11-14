<?php

namespace App\Console\Commands;

use App\Models\AudienceEvent;
use Illuminate\Console\Command;

/**
 * Supprime l'audience du site plus ancienne que
 * {@see AudienceEvent::RETENTION_MONTHS} mois (durée annoncée dans la
 * politique de confidentialité).
 */
class PurgeAudienceEvents extends Command
{
    protected $signature = 'presslink:purge-audience';

    protected $description = 'Supprime l\'audience du site vieille de plus de '.AudienceEvent::RETENTION_MONTHS.' mois.';

    public function handle(): int
    {
        $count = AudienceEvent::where('occurred_at', '<', now()->subMonths(AudienceEvent::RETENTION_MONTHS))->delete();

        $this->info("{$count} événement(s) d'audience supprimé(s).");

        return self::SUCCESS;
    }
}
