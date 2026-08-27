<?php

namespace App\Console\Commands\LoadTest;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Section 6 du plan de charge : simule N commandes passant à READY (Prête)
 * d'un coup, et mesure le coût réel du pipeline de notification actuel.
 *
 * N'envoie jamais de vraie notification (voir app/Notifications/Channels/
 * FcmChannel.php — se dégrade en no-op loggué si aucun token FCM
 * n'est enregistré, ce qui est le cas de tout le jeu de données de charge).
 */
class NotificationsFloodCommand extends Command
{
    protected $signature = 'loadtest:notifications-flood
        {--count=100 : Nombre de commandes à faire passer à Prête}';

    protected $description = 'Mesure le coût du pipeline de notifications sur N transitions de statut simultanées (Section 6 du plan de charge).';

    public function handle(): int
    {
        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        $count = (int) $this->option('count');

        $pressingIds = Pressing::where('name', 'like', 'LT Pressing%')->pluck('id');

        if ($pressingIds->isEmpty()) {
            $this->error('Aucun pressing de charge trouvé. Lancez d\'abord : php artisan loadtest:seed --env=loadtest');

            return self::FAILURE;
        }

        $orders = $this->ensureOrdersInTraitement($pressingIds, $count);

        $notificationsBefore = DB::table('notifications')->count();
        $jobsBefore = DB::table('jobs')->count();
        $failedJobsBefore = DB::table('failed_jobs')->count();

        $this->info("Passage de {$orders->count()} commandes à \"Prête\" (déclenche OrderReadyNotification pour chacune)...");

        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        $start = microtime(true);

        foreach ($orders as $order) {
            $order->update(['status' => OrderStatus::Prete]);
            $bar->advance();
        }

        $elapsedSeconds = microtime(true) - $start;
        $bar->finish();
        $this->newLine(2);

        $notificationsAfter = DB::table('notifications')->count();
        $jobsAfter = DB::table('jobs')->count();
        $failedJobsAfter = DB::table('failed_jobs')->count();

        $notificationsCreated = $notificationsAfter - $notificationsBefore;
        $jobsCreated = $jobsAfter - $jobsBefore;
        $failedJobsCreated = $failedJobsAfter - $failedJobsBefore;

        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Commandes traitées', $orders->count()],
                ['Temps total', number_format($elapsedSeconds, 3).' s'],
                ['Temps moyen / commande', number_format(($elapsedSeconds / max($orders->count(), 1)) * 1000, 2).' ms'],
                ['Notifications DB créées (canal "database")', $notificationsCreated],
                ['Jobs créés dans la table "jobs" (canal FCM)', $jobsCreated],
                ['Jobs en échec ("failed_jobs")', $failedJobsCreated],
            ]
        );

        $this->newLine();

        if ($jobsCreated === 0) {
            $this->warn('CONSTAT : 0 job créé dans la table "jobs" malgré QUEUE_CONNECTION=database.');
            $this->warn('OrderNotification n\'implémente pas ShouldQueue (voir app/Notifications/OrderNotification.php) :');
            $this->warn('le canal FCM et l\'écriture en base sont exécutés de manière SYNCHRONE, dans la requête HTTP');
            $this->warn('qui déclenche le changement de statut. Le temps ci-dessus ("Temps moyen / commande") est donc');
            $this->warn('directement ajouté à la latence perçue par le staff qui clique sur "Marquer prête".');
        }

        $this->warn('LIMITE DE CET ENVIRONNEMENT : Firebase n\'est pas configuré et aucun client de charge n\'a de');
        $this->warn('fcm_token — 100% des envois FCM se dégradent en no-op loggué (voir storage/logs/laravel.log,');
        $this->warn('"FCM (mock) ... aucun token enregistré"). Le comportement réel de FCM sous charge (latence API');
        $this->warn('Google, taux d\'échec, retry sur token invalide) ne peut être mesuré que sur un vrai environnement');
        $this->warn('de staging avec des identifiants Firebase sandbox — non disponible dans ce conteneur.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, int>  $pressingIds
     * @return Collection<int, Order>
     */
    private function ensureOrdersInTraitement($pressingIds, int $count)
    {
        $existing = Order::whereIn('pressing_id', $pressingIds)
            ->where('status', OrderStatus::Traitement)
            ->limit($count)
            ->get();

        $missing = $count - $existing->count();

        if ($missing <= 0) {
            return $existing;
        }

        $this->info("Seulement {$existing->count()} commande(s) en \"Traitement\" disponible(s) : création de {$missing} commande(s) supplémentaire(s) (hors mesure)...");

        $pressings = Pressing::whereIn('id', $pressingIds)->with('services')->get();
        $action = new CreateOrderAction;
        $created = collect();

        $bar = $this->output->createProgressBar($missing);
        $bar->start();

        for ($i = 0; $i < $missing; $i++) {
            $pressing = $pressings->random();
            $customer = $pressing->customers()->inRandomOrder()->first();

            if ($customer === null) {
                $bar->advance();

                continue;
            }

            $order = $action->handle($pressing, $customer, [[
                'service_id' => null, 'service_variant_id' => null,
                'name' => 'Article flood notifications', 'color' => null,
                'unit_price_fcfa' => 1000, 'quantity' => 1,
            ]]);
            $order->update(['status' => OrderStatus::Traitement]);
            $created->push($order);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        return $existing->concat($created);
    }

    private function guardEnvironment(): bool
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! str_contains($database, 'loadtest')) {
            $this->error("Garde de sécurité : DB_DATABASE=\"{$database}\" ne contient pas \"loadtest\".");

            return false;
        }

        return true;
    }
}
