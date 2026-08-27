<?php

namespace App\Console\Commands\LoadTest;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Supprime tout le jeu de données créé par loadtest:seed. Ne cible que les
 * entités portant les marqueurs de nommage du jeu de charge (voir
 * SeedCommand) et refuse de s'exécuter hors de la base presslink_loadtest.
 */
class PurgeCommand extends Command
{
    protected $signature = 'loadtest:purge {--force : Ne pas demander de confirmation}';

    protected $description = 'Supprime le jeu de données de charge (pressings/staff/clients/commandes "LT ...").';

    public function handle(): int
    {
        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Supprimer toutes les données de charge (pressings "LT ...") ?')) {
            $this->info('Annulé.');

            return self::SUCCESS;
        }

        $pressingIds = Pressing::where('name', 'like', 'LT Pressing%')->pluck('id');

        $customerIds = DB::table('pressing_customers')->whereIn('pressing_id', $pressingIds)->pluck('customer_id')->unique();

        DB::table('notifications')
            ->where('notifiable_type', Customer::class)
            ->whereIn('notifiable_id', $customerIds)
            ->delete();

        Order::withTrashed()->whereIn('pressing_id', $pressingIds)->get()->each(function (Order $order): void {
            $order->items()->delete();
            $order->statusHistory()->delete();
            $order->issues()->delete();
            $order->forceDelete();
        });

        DB::table('services')->whereIn('pressing_id', $pressingIds)->delete();
        DB::table('subscriptions')->whereIn('pressing_id', $pressingIds)->delete();
        DB::table('pressing_customers')->whereIn('pressing_id', $pressingIds)->delete();
        DB::table('pressing_users')->whereIn('pressing_id', $pressingIds)->delete();

        Customer::whereIn('id', $customerIds)->delete();
        User::where('email', 'like', '%@loadtest.presslink.local')->delete();
        Pressing::whereIn('id', $pressingIds)->delete();

        $this->info('Données de charge supprimées.');

        return self::SUCCESS;
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
