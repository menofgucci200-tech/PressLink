<?php

namespace Database\Seeders;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'PressLink Admin',
            'email' => 'superadmin@presslink.test',
            'phone' => '+2250700000000',
            'password' => Hash::make('password'),
            'is_super_admin' => true,
        ]);

        $admin = User::factory()->create([
            'name' => 'Awa Kouassi',
            'email' => 'admin@pressing-elegance.test',
            'phone' => '+2250700000001',
            'password' => Hash::make('password'),
        ]);

        $employee = User::factory()->create([
            'name' => 'Marc Koffi',
            'email' => 'employe@pressing-elegance.test',
            'phone' => '+2250700000002',
            'password' => Hash::make('password'),
        ]);

        $pressing = Pressing::factory()->create([
            'name' => 'Pressing Élégance',
            'city' => 'Cocody',
        ]);

        $pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $pressing->staff()->attach($employee, ['role' => PressingRole::Employee->value, 'is_active' => true]);

        Subscription::factory()->for($pressing)->create();

        $services = collect([
            ['name' => 'Chemise', 'price_fcfa' => 1000],
            ['name' => 'Pantalon', 'price_fcfa' => 1500],
            ['name' => 'Costume', 'price_fcfa' => 4000],
            ['name' => 'Robe', 'price_fcfa' => 3000],
        ])->map(fn ($service) => Service::create([...$service, 'pressing_id' => $pressing->id]));

        $customers = Customer::factory()->count(5)->create();

        foreach ($customers as $customer) {
            $pressing->customers()->attach($customer, ['joined_at' => now()]);
        }

        $chemise = $services->firstWhere('name', 'Chemise');
        $pantalon = $services->firstWhere('name', 'Pantalon');
        $costume = $services->firstWhere('name', 'Costume');

        $action = new CreateOrderAction;

        // Une commande par statut du workflow, pour peupler le dashboard de démo.
        $order1 = $action->handle($pressing, $customers[0], [
            ['service_id' => $chemise->id, 'name' => $chemise->name, 'unit_price_fcfa' => $chemise->price_fcfa, 'quantity' => 2],
            ['service_id' => $pantalon->id, 'name' => $pantalon->name, 'unit_price_fcfa' => $pantalon->price_fcfa, 'quantity' => 1],
        ]);
        $order1->update(['status' => OrderStatus::Traitement]);
        $order1->update(['status' => OrderStatus::Prete]);

        $action->handle($pressing, $customers[1], [
            ['service_id' => $costume->id, 'name' => $costume->name, 'unit_price_fcfa' => $costume->price_fcfa, 'quantity' => 1],
        ])->update(['status' => OrderStatus::Traitement]);

        $action->handle($pressing, $customers[2], [
            ['service_id' => $chemise->id, 'name' => $chemise->name, 'unit_price_fcfa' => $chemise->price_fcfa, 'quantity' => 4],
        ]);

        $order4 = $action->handle($pressing, $customers[3], [
            ['service_id' => $pantalon->id, 'name' => $pantalon->name, 'unit_price_fcfa' => $pantalon->price_fcfa, 'quantity' => 2],
        ]);
        $order4->update(['status' => OrderStatus::Traitement]);
        $order4->update(['status' => OrderStatus::Prete]);
        $order4->update(['status' => OrderStatus::Recuperee]);
    }
}
