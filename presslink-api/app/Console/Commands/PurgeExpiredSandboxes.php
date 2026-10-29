<?php

namespace App\Console\Commands;

use App\Actions\Sandbox\DeleteSandbox;
use App\Models\Pressing;
use Illuminate\Console\Command;

/**
 * Supprime les pressings de test arrivés au bout de leurs jours d'essai
 * (« Découvrir avec un pressing fictif »), avec leurs données fictives.
 */
class PurgeExpiredSandboxes extends Command
{
    protected $signature = 'presslink:purge-sandboxes';

    protected $description = 'Supprime les pressings de test expirés et leurs données fictives.';

    public function handle(DeleteSandbox $delete): int
    {
        $count = 0;
        Pressing::whereNotNull('sandbox_expires_at')
            ->where('sandbox_expires_at', '<=', now())
            ->each(function (Pressing $pressing) use ($delete, &$count) {
                $delete->handle($pressing);
                $count++;
            });

        $this->info("{$count} pressing(s) de test supprimé(s).");

        return self::SUCCESS;
    }
}
