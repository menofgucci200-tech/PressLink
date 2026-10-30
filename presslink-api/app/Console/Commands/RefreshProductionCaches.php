<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Caches de production (configuration, routes, événements) : chaque requête
 * n'a plus à relire une trentaine de fichiers de configuration, le .env et
 * toutes les routes. L'hébergement n'a pas d'accès shell : la tâche planifiée
 * (chaque minute) les reconstruit quand ils manquent (le déploiement les
 * efface) ou quand le .env a été modifié depuis (nouvelles clés…).
 */
class RefreshProductionCaches extends Command
{
    protected $signature = 'presslink:refresh-caches {--force : Reconstruire même si les caches sont à jour}';

    protected $description = 'Reconstruit les caches de configuration et de routes après un déploiement ou une modification du .env.';

    public function handle(): int
    {
        if (! app()->isProduction() && ! $this->option('force')) {
            return self::SUCCESS;
        }

        $configCache = app()->getCachedConfigPath();
        $envFile = app()->environmentFilePath();
        $stale = ! is_file($configCache)
            || ! is_file(app()->getCachedRoutesPath())
            || (is_file($envFile) && filemtime($envFile) > filemtime($configCache));

        if (! $stale && ! $this->option('force')) {
            return self::SUCCESS;
        }

        try {
            foreach (['config:cache', 'route:cache', 'event:cache'] as $command) {
                Artisan::call($command);
            }
            Log::info('Caches de production reconstruits (configuration, routes, événements).');
            $this->info('Caches reconstruits.');
        } catch (Throwable $e) {
            // Sans cache, l'application fonctionne : on efface ce qui a pu être écrit à moitié.
            foreach (['config:clear', 'route:clear', 'event:clear'] as $command) {
                Artisan::call($command);
            }
            Log::error('Caches de production non reconstruits.', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
