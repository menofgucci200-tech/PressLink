<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * État de santé de la plateforme pour la page « Santé » de la console :
 * chaque contrôle donne une valeur lisible, un niveau (ok, warning, error)
 * et, s'il le faut, ce qu'il faut faire. Rien n'est modifié.
 */
class PlatformHealth
{
    /** Fichier touché chaque minute par la tâche planifiée (routes/console.php). */
    public const HEARTBEAT_FILE = 'framework/schedule-heartbeat';

    /**
     * @return list<array{title: string, checks: list<array{label: string, value: string, level: string, hint: ?string}>}>
     */
    public function sections(float $bootMs): array
    {
        return [
            ['title' => 'Serveur PHP', 'checks' => $this->phpChecks($bootMs)],
            ['title' => 'Application', 'checks' => $this->appChecks()],
            ['title' => 'Base de données', 'checks' => $this->databaseChecks()],
            ['title' => 'Tâches de fond', 'checks' => $this->backgroundChecks()],
            ['title' => 'Espace disque et journal', 'checks' => $this->storageChecks()],
        ];
    }

    /** @return list<array{label: string, value: string, level: string, hint: ?string}> */
    private function phpChecks(float $bootMs): array
    {
        $checks = [
            $this->check('Démarrage de l\'application', number_format($bootMs, 0, ',', ' ').' ms',
                $bootMs < 80 ? 'ok' : ($bootMs < 200 ? 'warning' : 'error'),
                $bootMs < 80 ? null : 'Temps avant de commencer à construire la page. Au-delà de 80 ms, vérifiez qu\'OPcache est actif (ci-dessous) ; sinon c\'est le processeur de l\'hébergement.'),
            $this->check('Version de PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.3', '>=') ? 'ok' : 'warning', null),
            $this->check('Mémoire par requête', (string) ini_get('memory_limit'), 'ok', null),
        ];

        $status = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        if (! is_array($status) || ! ($status['opcache_enabled'] ?? false)) {
            $checks[] = $this->check('OPcache (code PHP gardé en mémoire)', 'Inactif', 'error',
                'Sans OPcache, PHP relit et recompile des centaines de fichiers à chaque page (souvent 100 à 300 ms de perdus). Activez-le dans le panneau de l\'hébergement (Configuration PHP → opcache.enable = On).');

            return $checks;
        }

        $memory = $status['memory_usage'] ?? [];
        $used = (int) ($memory['used_memory'] ?? 0);
        $free = (int) ($memory['free_memory'] ?? 0);
        $stats = $status['opcache_statistics'] ?? [];
        $hits = (int) ($stats['hits'] ?? 0);
        $misses = (int) ($stats['misses'] ?? 0);
        $hitRate = $hits + $misses > 0 ? $hits / ($hits + $misses) * 100 : 100;
        $scripts = (int) ($stats['num_cached_scripts'] ?? 0);
        $maxFiles = (int) ini_get('opcache.max_accelerated_files');
        $full = $free < 4 * 1024 * 1024 || ($maxFiles > 0 && $scripts >= $maxFiles * 0.95);

        $checks[] = $this->check('OPcache (code PHP gardé en mémoire)', 'Actif', 'ok', null);
        $checks[] = $this->check('Remplissage d\'OPcache',
            sprintf('%s utilisés, %s libres · %d fichiers sur %d', $this->bytes($used), $this->bytes($free), $scripts, $maxFiles),
            $full ? 'warning' : 'ok',
            $full ? 'OPcache est presque plein : des fichiers sont recompilés à chaque page. Augmentez opcache.memory_consumption ou opcache.max_accelerated_files.' : null);
        $checks[] = $this->check('Taux de réussite d\'OPcache', number_format($hitRate, 1, ',', ' ').' %',
            $hitRate >= 95 ? 'ok' : 'warning',
            $hitRate >= 95 ? null : 'Normal juste après un déploiement ; il doit remonter au-dessus de 95 %.');

        return $checks;
    }

    /** @return list<array{label: string, value: string, level: string, hint: ?string}> */
    private function appChecks(): array
    {
        $production = app()->isProduction();
        $debug = (bool) config('app.debug');
        $configCached = app()->configurationIsCached();
        $routesCached = app()->routesAreCached();
        $views = count(glob(config('view.compiled').'/*.php') ?: []);

        return [
            $this->check('Environnement', (string) app()->environment(), $production ? 'ok' : 'warning', $production ? null : 'Le site en ligne doit être en APP_ENV=production.'),
            $this->check('Mode débogage', $debug ? 'Activé' : 'Désactivé', $debug && $production ? 'error' : 'ok',
                $debug && $production ? 'APP_DEBUG=true en ligne ralentit chaque page et affiche les erreurs au public : mettez APP_DEBUG=false dans le .env.' : null),
            $this->check('Caches de configuration et de routes', $configCached && $routesCached ? 'Construits' : 'Manquants',
                $configCached && $routesCached ? 'ok' : 'warning',
                $configCached && $routesCached ? null : 'La tâche planifiée les reconstruit dans la minute après un déploiement. S\'ils restent manquants, le cron ne tourne pas.'),
            $this->check('Vues préparées', (string) $views, $views > 0 ? 'ok' : 'warning', null),
            $this->check('Sessions · cache · file d\'attente', implode(' · ', [config('session.driver'), config('cache.default'), config('queue.default')]), 'ok', null),
        ];
    }

    /** @return list<array{label: string, value: string, level: string, hint: ?string}> */
    private function databaseChecks(): array
    {
        try {
            $timings = [];
            for ($i = 0; $i < 5; $i++) {
                $start = microtime(true);
                DB::select('select 1');
                $timings[] = (microtime(true) - $start) * 1000;
            }
            sort($timings);
            $latency = $timings[2];
        } catch (Throwable $e) {
            return [$this->check('Connexion', 'Impossible', 'error', $e->getMessage())];
        }

        $checks = [
            $this->check('Temps d\'aller-retour', number_format($latency, 1, ',', ' ').' ms', $latency < 2 ? 'ok' : ($latency < 10 ? 'warning' : 'error'),
                $latency < 2 ? null : 'Chaque requête SQL paie ce délai. Une page en fait 10 à 30 : au-delà de quelques millisecondes, la base est éloignée ou surchargée chez l\'hébergeur.'),
        ];

        if (DB::getDriverName() === 'mysql') {
            $version = (string) (DB::selectOne('select version() as v')->v ?? '');
            $checks[] = $this->check('Version du serveur', $version, 'ok', null);

            $tables = collect(DB::select('select table_name as name, table_rows as row_count, data_length + index_length as size from information_schema.tables where table_schema = database() order by data_length + index_length desc limit 6'));
            foreach ($tables as $table) {
                $checks[] = $this->check("Table {$table->name}", sprintf('≈ %s lignes · %s', number_format((int) $table->row_count, 0, ',', ' '), $this->bytes((int) $table->size)), 'ok', null);
            }
        }

        return $checks;
    }

    /** @return list<array{label: string, value: string, level: string, hint: ?string}> */
    private function backgroundChecks(): array
    {
        $checks = [];

        $heartbeat = storage_path(self::HEARTBEAT_FILE);
        if (is_file($heartbeat)) {
            $age = time() - (int) filemtime($heartbeat);
            $checks[] = $this->check('Tâche planifiée (cron)', 'Dernier passage '.Carbon::createFromTimestamp((int) filemtime($heartbeat))->locale('fr')->diffForHumans(),
                $age <= 180 ? 'ok' : 'error',
                $age <= 180 ? null : 'Le cron ne tourne plus : e-mails, notifications, expirations et caches restent en attente. Vérifiez la tâche cron de l\'hébergement (php artisan schedule:run chaque minute).');
        } else {
            $checks[] = $this->check('Tâche planifiée (cron)', 'Pas encore de passage', 'warning', 'Le premier passage arrive dans la minute après le déploiement.');
        }

        if (Schema::hasTable('jobs')) {
            $pending = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('created_at');
            $waitMinutes = $oldest ? (int) floor((time() - (int) $oldest) / 60) : 0;
            $since = $oldest ? Carbon::createFromTimestamp((int) $oldest)->locale('fr')->diffForHumans(syntax: Carbon::DIFF_ABSOLUTE) : '';
            $checks[] = $this->check('Envois en attente (e-mails, notifications)', $pending === 0 ? 'Aucun' : "{$pending}, le plus ancien depuis {$since}",
                $pending === 0 || $waitMinutes < 5 ? 'ok' : 'error',
                $pending > 0 && $waitMinutes >= 5 ? 'La file ne se vide pas : le cron ne tourne pas, ou un envoi échoue en boucle.' : null);
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDays(7))->count();
            $checks[] = $this->check('Envois en échec (7 derniers jours)', (string) $failed, $failed === 0 ? 'ok' : 'warning',
                $failed === 0 ? null : 'Des e-mails ou notifications n\'ont pas pu partir : voir la table failed_jobs ou le journal.');
        }

        return $checks;
    }

    /** @return list<array{label: string, value: string, level: string, hint: ?string}> */
    private function storageChecks(): array
    {
        $checks = [];

        $free = @disk_free_space(storage_path());
        if ($free !== false) {
            $checks[] = $this->check('Espace libre', $this->bytes((int) $free), $free > 1024 ** 3 ? 'ok' : ($free > 200 * 1024 ** 2 ? 'warning' : 'error'),
                $free > 1024 ** 3 ? null : 'Peu d\'espace : photos des commandes et journaux risquent de ne plus s\'écrire.');
        }

        $logs = glob(storage_path('logs').'/*.log') ?: [];
        $logSize = array_sum(array_map(fn (string $file) => (int) filesize($file), $logs));
        $checks[] = $this->check('Journal d\'erreurs', $this->bytes($logSize).(count($logs) > 1 ? ' ('.count($logs).' fichiers)' : ''),
            $logSize < 50 * 1024 ** 2 ? 'ok' : 'warning',
            $logSize < 50 * 1024 ** 2 ? null : 'Journal volumineux : vérifiez les erreurs répétées, puis videz-le.');

        return $checks;
    }

    /** @return array{label: string, value: string, level: string, hint: ?string} */
    private function check(string $label, string $value, string $level, ?string $hint): array
    {
        return ['label' => $label, 'value' => $value, 'level' => $level, 'hint' => $hint];
    }

    private function bytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => number_format($bytes / 1024 ** 3, 1, ',', ' ').' Go',
            $bytes >= 1024 ** 2 => number_format($bytes / 1024 ** 2, 1, ',', ' ').' Mo',
            $bytes >= 1024 => number_format($bytes / 1024, 0, ',', ' ').' Ko',
            default => $bytes.' o',
        };
    }
}
