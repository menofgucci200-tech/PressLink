<?php

namespace App\Actions\Demo;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Pressing;
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

            $services = collect([
                ['name' => 'Chemise', 'price_fcfa' => 1000],
                ['name' => 'Pantalon', 'price_fcfa' => 1500],
                ['name' => 'Robe', 'price_fcfa' => 3000],
                ['name' => 'Costume', 'price_fcfa' => 4000],
                ['name' => 'Drap', 'price_fcfa' => 2000],
            ])->mapWithKeys(fn (array $service) => [
                $service['name'] => Service::create([...$service, 'pressing_id' => $pressing->id]),
            ]);

            $customers = collect(self::CUSTOMERS)->map(function (array $data) use ($pressing, $password): Customer {
                $customer = Customer::create([...$data, 'password' => $password, 'phone_verified_at' => now(), 'is_active' => true]);
                $pressing->customers()->attach($customer, ['joined_at' => now()]);

                return $customer;
            });

            $this->createOrders($pressing, $customers->all(), $services->all());

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
     */
    private function createOrders(Pressing $pressing, array $customers, array $services): void
    {
        $item = fn (string $name, int $quantity) => [
            'service_id' => $services[$name]->id,
            'name' => $name,
            'unit_price_fcfa' => $services[$name]->price_fcfa,
            'quantity' => $quantity,
        ];
        $action = new CreateOrderAction;

        // [client, articles, statuts successifs à appliquer]
        $orders = [
            [0, [$item('Chemise', 3), $item('Costume', 1)], [OrderStatus::Traitement, OrderStatus::Prete]],
            [0, [$item('Robe', 2)], [OrderStatus::Traitement, OrderStatus::Prete, OrderStatus::Recuperee]],
            [1, [$item('Pantalon', 2), $item('Chemise', 2)], [OrderStatus::Traitement]],
            [2, [$item('Drap', 2)], []],
            [3, [$item('Costume', 2)], [OrderStatus::Traitement, OrderStatus::Prete, OrderStatus::Recuperee]],
        ];

        foreach ($orders as [$customerIndex, $items, $statuses]) {
            $order = $action->handle($pressing, $customers[$customerIndex], $items, now()->addDays(2)->toDateString());
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
