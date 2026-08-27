<?php

namespace Tests\Concurrency;

use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concurrency\Support\LivewireSession;
use Tests\TestCase;

/**
 * Base commune aux tests de concurrence métier (load-testing/, Section 5).
 *
 * Ces tests pilotent un vrai serveur HTTP (voir phpunit.concurrency.xml) —
 * PAS de RefreshDatabase : les fixtures sont créées et validées (commit
 * réel), puis explicitement nettoyées dans tearDown(). Toutes les entités
 * créées ici portent le préfixe "CT " pour rester repérables si un
 * nettoyage échoue.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    protected const PASSWORD = 'concurrency-test-pw';

    /** @var list<Pressing> */
    private array $createdPressings = [];

    protected function trackPressing(Pressing $pressing): void
    {
        $this->createdPressings[] = $pressing;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $baseUrl = $this->baseUrl();

        try {
            $reachable = Http::timeout(3)->get($baseUrl.'/login')->status() === 200;
        } catch (\Throwable) {
            $reachable = false;
        }

        if (! $reachable) {
            $this->markTestSkipped("Serveur de charge injoignable sur {$baseUrl}. Lancez : php artisan serve --env=loadtest --port=8100 (voir load-testing/README.md).");
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPressings as $pressing) {
            $ids = $pressing->customers()->pluck('customers.id');
            $pressing->orders()->withTrashed()->get()->each(function ($order) {
                $order->items()->delete();
                $order->statusHistory()->delete();
                $order->forceDelete();
            });
            $pressing->services()->delete();
            $pressing->subscription?->delete();
            $pressing->staff()->detach();
            $pressing->customers()->detach();
            Customer::whereIn('id', $ids)->delete();
            $pressing->delete();
        }

        $this->createdPressings = [];

        parent::tearDown();
    }

    protected function baseUrl(): string
    {
        return rtrim((string) env('CONCURRENCY_BASE_URL', 'http://127.0.0.1:8100'), '/');
    }

    protected function newSession(): LivewireSession
    {
        return new LivewireSession($this->baseUrl());
    }

    /**
     * @return array{pressing: Pressing, admin: User, subscription: Subscription}
     */
    protected function makePressingWithAdmin(?int $ordersLimit = null): array
    {
        $pressing = Pressing::factory()->create(['name' => 'CT Pressing '.uniqid()]);
        $this->trackPressing($pressing);

        $admin = User::factory()->create([
            'email' => 'ct-admin-'.uniqid().'@concurrency.test',
            'password' => Hash::make(self::PASSWORD),
        ]);
        $pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        $subscription = Subscription::factory()->active(SubscriptionPlan::Pro)->create([
            'pressing_id' => $pressing->id,
            'orders_limit' => $ordersLimit,
            'orders_used' => 0,
        ]);

        return ['pressing' => $pressing, 'admin' => $admin, 'subscription' => $subscription];
    }

    protected function makeEmployeeOf(Pressing $pressing): User
    {
        $employee = User::factory()->create([
            'email' => 'ct-employee-'.uniqid().'@concurrency.test',
            'password' => Hash::make(self::PASSWORD),
        ]);
        $pressing->staff()->attach($employee, ['role' => PressingRole::Employee->value, 'is_active' => true]);

        return $employee;
    }

    protected function makeCustomerOf(Pressing $pressing): Customer
    {
        $customer = Customer::factory()->create(['password' => Hash::make(self::PASSWORD)]);
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        return $customer;
    }
}
