<?php

namespace App\Console\Commands\LoadTest;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Génère un jeu de données de charge pour les tests k6 / Pest de
 * load-testing/. Toutes les entités créées sont préfixées "LT " / suffixées
 * "@loadtest.presslink.local" pour rester identifiables sans dépendre d'une
 * colonne dédiée — l'isolation réelle vient du fait que cette commande ne
 * doit tourner que sur la base presslink_loadtest (garde ci-dessous).
 *
 * Identifiants générés (mot de passe unique pour tout le jeu de charge) :
 *   - admin1@loadtest.presslink.local .. admin5@loadtest.presslink.local
 *   - employee1@loadtest.presslink.local .. employee20@loadtest.presslink.local
 *   - superadmin@loadtest.presslink.local
 *   - clients : téléphone +225 01 00 00 0001 .. +225 01 00 05 0000 (mdp commun)
 *   Mot de passe pour tous : "loadtest-2026"
 */
class SeedCommand extends Command
{
    protected $signature = 'loadtest:seed
        {--pressings=5 : Nombre de pressings à créer}
        {--employees-per-pressing=4 : Employés par pressing (en plus de 1 admin)}
        {--customers=500 : Nombre total de clients, répartis sur les pressings}
        {--fresh : Purge les données de charge existantes avant de re-semer}';

    protected $description = 'Sème un jeu de données de charge réaliste et isolé pour les tests k6/Pest (load-testing/).';

    private const PASSWORD = 'loadtest-2026';

    public function handle(): int
    {
        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->call('loadtest:purge', ['--force' => true]);
        }

        $pressingCount = (int) $this->option('pressings');
        $employeesPerPressing = (int) $this->option('employees-per-pressing');
        $totalCustomers = (int) $this->option('customers');
        $customersPerPressing = intdiv($totalCustomers, max($pressingCount, 1));

        $this->info("Génération de {$pressingCount} pressings, {$customersPerPressing} clients/pressing, {$employeesPerPressing} employés/pressing...");

        User::firstOrCreate(
            ['email' => 'superadmin@loadtest.presslink.local'],
            [
                'name' => 'LT Super Admin',
                'phone' => '+225010000'.str_pad('0', 4, '0', STR_PAD_LEFT),
                'password' => Hash::make(self::PASSWORD),
                'is_super_admin' => true,
            ]
        );

        $bar = $this->output->createProgressBar($pressingCount);
        $bar->start();

        for ($i = 1; $i <= $pressingCount; $i++) {
            $this->seedPressing($i, $employeesPerPressing, $customersPerPressing);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->summarize();

        return self::SUCCESS;
    }

    private function guardEnvironment(): bool
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! str_contains($database, 'loadtest')) {
            $this->error("Garde de sécurité : DB_DATABASE=\"{$database}\" ne contient pas \"loadtest\".");
            $this->error('Cette commande ne doit jamais être exécutée contre une base de production ou de développement.');
            $this->error('Utilisez APP_ENV=loadtest (voir .env.loadtest) avant de lancer loadtest:seed.');

            return false;
        }

        return true;
    }

    private function seedPressing(int $index, int $employeesPerPressing, int $customerCount): void
    {
        DB::transaction(function () use ($index, $employeesPerPressing, $customerCount): void {
            $pressing = Pressing::factory()->create([
                'name' => "LT Pressing {$index}",
                'city' => ['Cocody', 'Yopougon', 'Marcory', 'Plateau', 'Angré'][($index - 1) % 5],
                'email' => "pressing{$index}@loadtest.presslink.local",
            ]);

            Subscription::factory()->active(SubscriptionPlan::Pro)->create([
                'pressing_id' => $pressing->id,
                'orders_limit' => 100_000,
            ]);

            $admin = User::factory()->create([
                'name' => "LT Admin {$index}",
                'email' => "admin{$index}@loadtest.presslink.local",
                'phone' => '+225020000'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'password' => Hash::make(self::PASSWORD),
            ]);
            $pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);

            for ($e = 1; $e <= $employeesPerPressing; $e++) {
                $employeeNumber = ($index - 1) * $employeesPerPressing + $e;
                $employee = User::factory()->create([
                    'name' => "LT Employee {$employeeNumber}",
                    'email' => "employee{$employeeNumber}@loadtest.presslink.local",
                    'phone' => '+225030'.str_pad((string) $employeeNumber, 6, '0', STR_PAD_LEFT),
                    'password' => Hash::make(self::PASSWORD),
                ]);
                $pressing->staff()->attach($employee, ['role' => PressingRole::Employee->value, 'is_active' => true]);
            }

            $services = collect([
                ['name' => 'Chemise', 'price_fcfa' => 1000, 'variants' => ['Manche courte', 'Manche longue']],
                ['name' => 'Pantalon', 'price_fcfa' => 1500, 'variants' => []],
                ['name' => 'Costume', 'price_fcfa' => 4000, 'variants' => ['2 pièces', '3 pièces']],
                ['name' => 'Robe', 'price_fcfa' => 3000, 'variants' => []],
                ['name' => 'Veste', 'price_fcfa' => 2000, 'variants' => []],
            ])->map(function (array $definition) use ($pressing): Service {
                $service = Service::create([
                    'pressing_id' => $pressing->id,
                    'name' => $definition['name'],
                    'price_fcfa' => $definition['price_fcfa'],
                    'is_active' => true,
                ]);

                foreach ($definition['variants'] as $variantName) {
                    ServiceVariant::create([
                        'service_id' => $service->id,
                        'name' => $variantName,
                        'price_fcfa' => $definition['price_fcfa'] + 500,
                        'is_active' => true,
                    ]);
                }

                return $service;
            });

            $customers = $this->seedCustomers($pressing, $index, $customerCount);

            $this->seedOrders($pressing, $customers, $services);
        });
    }

    /** @return Collection<int, Customer> */
    private function seedCustomers(Pressing $pressing, int $pressingIndex, int $count): Collection
    {
        $customers = Customer::factory()
            ->count($count)
            ->sequence(fn ($sequence) => [
                'phone' => sprintf('+22501%02d%04d', $pressingIndex, $sequence->index + 1),
            ])
            ->create(['password' => Hash::make(self::PASSWORD)]);

        $pivotRows = $customers->mapWithKeys(fn (Customer $customer) => [
            $customer->id => ['joined_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ])->all();

        $pressing->customers()->attach($pivotRows);

        return $customers;
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Service>  $services
     */
    private function seedOrders(Pressing $pressing, Collection $customers, Collection $services): void
    {
        $action = new CreateOrderAction;

        // Distribution volontairement variée : la majorité des commandes en
        // statuts "actifs" (utiles pour les scénarios de charge dashboard/
        // liste), une partie récupérées/annulées pour peupler l'historique.
        $statusWeights = [
            OrderStatus::Recue->value => 30,
            OrderStatus::Traitement->value => 25,
            OrderStatus::Prete->value => 20,
            OrderStatus::Recuperee->value => 20,
            OrderStatus::Annulee->value => 5,
        ];

        foreach ($customers as $customer) {
            $orderCount = random_int(1, 4);

            for ($o = 0; $o < $orderCount; $o++) {
                $item = $services->random();

                $order = $action->handle($pressing, $customer, [[
                    'service_id' => $item->id,
                    'name' => $item->name,
                    'unit_price_fcfa' => $item->price_fcfa,
                    'quantity' => random_int(1, 3),
                ]]);

                $target = $this->weightedRandomStatus($statusWeights);

                foreach ($this->transitionPathTo($target) as $step) {
                    $order->update(['status' => $step]);
                }
            }
        }
    }

    /** @param  array<string, int>  $weights */
    private function weightedRandomStatus(array $weights): OrderStatus
    {
        $total = array_sum($weights);
        $roll = random_int(1, $total);
        $cumulative = 0;

        foreach ($weights as $status => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return OrderStatus::from($status);
            }
        }

        return OrderStatus::Recue;
    }

    /** @return list<OrderStatus> */
    private function transitionPathTo(OrderStatus $target): array
    {
        return match ($target) {
            OrderStatus::Recue => [],
            OrderStatus::Traitement, OrderStatus::Attente, OrderStatus::Annulee => [$target],
            OrderStatus::Prete => [OrderStatus::Traitement, OrderStatus::Prete],
            OrderStatus::Recuperee => [OrderStatus::Traitement, OrderStatus::Prete, OrderStatus::Recuperee],
        };
    }

    private function summarize(): void
    {
        $this->table(
            ['Entité', 'Total'],
            [
                ['Pressings', Pressing::where('name', 'like', 'LT Pressing%')->count()],
                ['Staff (admin + employés)', User::where('email', 'like', '%@loadtest.presslink.local')->count()],
                ['Clients', Customer::where('phone', 'like', '+225010%')->count()],
                ['Commandes', DB::table('orders')
                    ->join('pressings', 'pressings.id', '=', 'orders.pressing_id')
                    ->where('pressings.name', 'like', 'LT Pressing%')
                    ->count()],
                ['Notifications', DB::table('notifications')
                    ->whereIn('notifiable_id', function ($q) {
                        $q->select('id')->from('customers')->where('phone', 'like', '+225010%');
                    })
                    ->where('notifiable_type', Customer::class)
                    ->count()],
            ]
        );

        $this->newLine();
        $this->info('Mot de passe commun à tous les comptes de charge : '.self::PASSWORD);
        $this->info('Staff  : admin{N}@loadtest.presslink.local / employee{N}@loadtest.presslink.local');
        $this->info('Super admin : superadmin@loadtest.presslink.local');
        $this->info('Clients : téléphone +225 01 <pressing 2 chiffres> <n° 4 chiffres>');
    }
}
