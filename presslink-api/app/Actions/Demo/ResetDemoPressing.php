<?php

namespace App\Actions\Demo;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Prestation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * (Ré)initialise le pressing de démonstration en production : supprime
 * l'ancienne démo puis recrée un pressing complet (équipe, services, clients,
 * commandes à chaque statut). Sans factories : Faker n'est pas installé en
 * production. Un nouveau mot de passe, commun à tous les comptes de démo,
 * est généré à chaque réinitialisation.
 */
class ResetDemoPressing
{
    public const PRESSING_CODE = 'DM-0001';

    public const MANAGER_LOGIN = 'demo.gerant';

    public const EMPLOYEE_LOGIN = 'demo.employe';

    /**
     * Comptes clients de démo (numéros +225 00… : jamais attribués à un abonné).
     *
     * @var list<array{first_name: string, last_name: string, phone: string, gender: string}>
     */
    public const CUSTOMERS = [
        ['first_name' => 'Aïcha', 'last_name' => 'Koné', 'phone' => '+2250000000001', 'gender' => 'femme'],
        ['first_name' => 'Yao', 'last_name' => 'Kouamé', 'phone' => '+2250000000002', 'gender' => 'homme'],
        ['first_name' => 'Mariam', 'last_name' => 'Traoré', 'phone' => '+2250000000003', 'gender' => 'femme'],
        ['first_name' => 'Serge', 'last_name' => 'N\'Guessan', 'phone' => '+2250000000004', 'gender' => 'homme'],
    ];

    /**
     * @return array{password: string, pressing: Pressing}
     */
    public function handle(): array
    {
        // La remise à zéro de la démo n'a pas à remplir son journal d'activité.
        return ActivityLog::withoutLogging(fn () => $this->reset());
    }

    /** @return array{password: string, pressing: Pressing} */
    private function reset(): array
    {
        $password = Str::password(10, symbols: false);

        $pressing = DB::transaction(function () use ($password): Pressing {
            $this->deleteExistingDemo();

            $pressing = Pressing::create([
                'name' => 'Pressing Démo PressLink',
                'code' => self::PRESSING_CODE,
                'phone' => config('presslink.support.phone_display'),
                'email' => config('presslink.support.email'),
                'address' => 'Rue des Jardins',
                'city' => 'Cocody, Abidjan',
                'description' => 'Pressing de démonstration : explorez PressLink librement, les données sont fictives.',
                'opening_hours' => $this->openingHours(),
                'show_pricing_to_customers' => true,
                'status' => PressingStatus::Active,
            ]);

            $manager = User::create(['name' => 'Gérant Démo', 'login' => self::MANAGER_LOGIN, 'password' => $password]);
            $employee = User::create(['name' => 'Employé Démo', 'login' => self::EMPLOYEE_LOGIN, 'password' => $password]);
            $pressing->staff()->attach($manager, ['role' => PressingRole::Admin->value, 'is_active' => true]);
            $pressing->staff()->attach($employee, ['role' => PressingRole::Employee->value, 'is_active' => true]);

            $pressing->subscription()->create([
                'plan' => SubscriptionPlan::Premium,
                'status' => SubscriptionStatus::Active,
                'billing_cycle' => 'monthly',
                'orders_limit' => null,
                'orders_used' => 0,
                'current_period_starts_at' => now()->startOfMonth(),
                'current_period_ends_at' => now()->addYear(),
            ]);

            // Services par défaut du pressing (Repassage, Nettoyage) et prix de chaque pièce pour chacun.
            $prestations = $pressing->prestations()->get()->keyBy('icon');
            $services = collect([
                ['name' => 'Chemise', 'icon' => 'chemise', 'repassage' => 500, 'nettoyage' => 1000],
                ['name' => 'Pantalon', 'icon' => 'pantalon', 'repassage' => 700, 'nettoyage' => 1500],
                ['name' => 'Robe', 'icon' => 'robe', 'repassage' => 1000, 'nettoyage' => 3000],
                ['name' => 'Costume', 'icon' => 'costume', 'repassage' => 1500, 'nettoyage' => 4000],
                ['name' => 'Drap', 'icon' => 'drap', 'repassage' => 800, 'nettoyage' => 2000],
            ])->mapWithKeys(function (array $piece) use ($pressing, $prestations) {
                $service = Service::create(['pressing_id' => $pressing->id, 'name' => $piece['name'], 'icon' => $piece['icon'], 'price_fcfa' => $piece['nettoyage']]);
                $service->setPrice($prestations['repassage'], $piece['repassage']);
                $service->setPrice($prestations['nettoyage'], $piece['nettoyage']);

                return [$service->name => $service];
            });

            $customers = collect(self::CUSTOMERS)->map(function (array $data) use ($pressing, $password): Customer {
                $customer = Customer::create([...$data, 'password' => $password, 'phone_verified_at' => now(), 'is_active' => true]);
                $pressing->customers()->attach($customer, ['joined_at' => now()]);

                return $customer;
            });

            $this->createOrders($pressing, $customers->all(), $services->all(), $prestations->all());

            return $pressing;
        });

        return ['password' => $password, 'pressing' => $pressing];
    }

    public static function exists(): bool
    {
        return Pressing::where('code', self::PRESSING_CODE)->exists();
    }

    private function deleteExistingDemo(): void
    {
        $customers = Customer::whereIn('phone', array_column(self::CUSTOMERS, 'phone'))->get();
        DB::table('notifications')
            ->where('notifiable_type', (new Customer)->getMorphClass())
            ->whereIn('notifiable_id', $customers->pluck('id'))
            ->delete();
        $customers->each->forceDelete();

        User::whereIn('login', [self::MANAGER_LOGIN, self::EMPLOYEE_LOGIN])->get()->each->delete();

        // Les commandes, services, abonnement et rattachements suivent en cascade.
        Pressing::where('code', self::PRESSING_CODE)->get()->each->delete();
    }

    /**
     * @param  list<Customer>  $customers
     * @param  array<string, Service>  $services
     * @param  array<string, Prestation>  $prestations  clé = icône (repassage, nettoyage)
     */
    private function createOrders(Pressing $pressing, array $customers, array $services, array $prestations): void
    {
        $item = function (string $name, int $quantity, string $prestation = 'nettoyage') use ($services, $prestations) {
            $service = $prestations[$prestation];

            return [
                'service_id' => $services[$name]->id,
                'prestation_id' => $service->id,
                'prestation_name' => $service->name,
                'name' => "{$name} · {$service->name}",
                'unit_price_fcfa' => $services[$name]->priceFor($service),
                'quantity' => $quantity,
            ];
        };
        $action = new CreateOrderAction;

        // [client, articles, statuts successifs à appliquer]
        $orders = [
            [0, [$item('Chemise', 3, 'repassage'), $item('Costume', 1)], [OrderStatus::Traitement, OrderStatus::Prete]],
            [0, [$item('Robe', 2)], [OrderStatus::Traitement, OrderStatus::Prete, OrderStatus::Recuperee]],
            [1, [$item('Pantalon', 2), $item('Chemise', 2, 'repassage')], [OrderStatus::Traitement]],
            [2, [$item('Drap', 2)], []],
            [3, [$item('Costume', 2)], [OrderStatus::Traitement, OrderStatus::Prete, OrderStatus::Recuperee]],
        ];

        foreach ($orders as [$customerIndex, $items, $statuses]) {
            // Une commande remise au client est soldée (payée au dépôt dans la démo).
            $paid = in_array(OrderStatus::Recuperee, $statuses, true)
                ? (int) array_sum(array_map(fn (array $line) => $line['unit_price_fcfa'] * $line['quantity'], $items))
                : 0;
            $order = $action->handle($pressing, $customers[$customerIndex], $items, now()->addDays(2)->toDateString(), amountPaidFcfa: $paid);
            foreach ($statuses as $status) {
                $order->update(['status' => $status]);
            }
        }
    }

    /**
     * @return array<string, array{open: ?string, close: ?string, closed: bool}>
     */
    private function openingHours(): array
    {
        $hours = [];
        foreach (['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'] as $day) {
            $hours[$day] = ['open' => '07:30', 'close' => '19:00', 'closed' => false];
        }
        $hours['samedi'] = ['open' => '08:00', 'close' => '17:00', 'closed' => false];
        $hours['dimanche'] = ['open' => null, 'close' => null, 'closed' => true];

        return $hours;
    }
}
