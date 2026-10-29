<?php

namespace App\Actions\Demo;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Prestation;
use App\Models\Service;

/**
 * Remplit un pressing fictif (démo DM-0001 ou pressing de test d'un
 * visiteur) : pièces et prix, clients, et commandes à chaque étape (en
 * cours, prêtes, remises). Sans factories : Faker n'est pas installé en
 * production.
 */
class FillDemoPressing
{
    /**
     * @param  list<array{first_name: string, last_name: string, phone: string, gender: string}>  $customers
     * @param  string|null  $customerPassword  null : clients sans compte dans l'application
     */
    public function handle(Pressing $pressing, array $customers, ?string $customerPassword = null): void
    {
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

        $customers = collect($customers)->map(function (array $data) use ($pressing, $customerPassword): Customer {
            $customer = Customer::create([
                ...$data,
                'sandbox_pressing_id' => $pressing->customerDirectoryId(),
                'password' => $customerPassword,
                'phone_verified_at' => $customerPassword !== null ? now() : null,
                'is_active' => true,
            ]);
            $pressing->customers()->attach($customer, ['joined_at' => now()]);

            return $customer;
        });

        $this->createOrders($pressing, $customers->all(), $services->all(), $prestations->all());
    }

    /**
     * @return array<string, array{open: ?string, close: ?string, closed: bool}>
     */
    public static function openingHours(): array
    {
        $hours = [];
        foreach (['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'] as $day) {
            $hours[$day] = ['open' => '07:30', 'close' => '19:00', 'closed' => false];
        }
        $hours['samedi'] = ['open' => '08:00', 'close' => '17:00', 'closed' => false];
        $hours['dimanche'] = ['open' => null, 'close' => null, 'closed' => true];

        return $hours;
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
}
