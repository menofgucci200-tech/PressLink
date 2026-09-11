<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Models\Customer;
use App\Models\Pressing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerNotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Chaque commande créée notifie déjà automatiquement le client
     * (OrderObserver::created) — inutile (et incorrect) de notifier une
     * seconde fois manuellement ici.
     */
    private function makeCustomerWithNotifications(int $count): Customer
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        for ($i = 0; $i < $count; $i++) {
            (new CreateOrderAction)->handle($pressing, $customer, [
                ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
            ]);
        }

        return $customer;
    }

    public function test_mark_all_as_read_clears_the_unread_count(): void
    {
        $customer = $this->makeCustomerWithNotifications(3);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJson(['count' => 3]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJson(['count' => 0]);

        $this->assertSame(0, $customer->fresh()->unreadNotifications()->count());
    }

    public function test_mark_all_as_read_does_not_affect_another_customers_notifications(): void
    {
        $customer = $this->makeCustomerWithNotifications(2);
        $otherCustomer = $this->makeCustomerWithNotifications(1);

        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertSame(1, $otherCustomer->fresh()->unreadNotifications()->count());
    }
}
