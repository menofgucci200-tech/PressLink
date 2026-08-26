<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PressingRole;
use App\Livewire\Clients\Show as ClientsShow;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Livewire\Services\Variants as ServicesVariants;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 8 — tests dédiés d'isolation multi-tenant :
 * RB-01 (un pressing ne voit/ne modifie jamais les données d'un autre
 * pressing) et RB-02 (un client ne voit/ne modifie jamais les données
 * d'un autre client). Complète les vérifications déjà éparpillées dans
 * d'autres fichiers (Orders/Show, Issues) par une couverture centralisée
 * et exhaustive des surfaces où une fuite serait la plus grave.
 */
class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    private function bearer(Customer $customer): string
    {
        return $customer->createToken('test')->plainTextToken;
    }

    // --- RB-01 : isolation entre pressings ---------------------------------

    public function test_staff_cannot_view_a_client_who_never_joined_their_pressing(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $foreignCustomer = Customer::factory()->create();
        $otherPressing->customers()->attach($foreignCustomer, ['joined_at' => now()]);

        $this->actingAs($admin)
            ->get(route('clients.show', $foreignCustomer))
            ->assertForbidden();
    }

    public function test_staff_can_view_a_client_shared_with_another_pressing_but_only_sees_their_own_orders(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $otherPressing->customers()->attach($customer, ['joined_at' => now()]);

        $ownOrder = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);
        $foreignOrder = (new CreateOrderAction)->handle($otherPressing, $customer, [
            ['service_id' => null, 'name' => 'Pantalon', 'unit_price_fcfa' => 1500, 'quantity' => 1],
        ]);

        Livewire::actingAs($admin)
            ->test(ClientsShow::class, ['customer' => $customer])
            ->assertOk()
            ->assertSee($ownOrder->order_number)
            ->assertDontSee($foreignOrder->order_number);
    }

    public function test_staff_cannot_manage_service_variants_belonging_to_another_pressing(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $foreignService = Service::factory()->for($otherPressing)->create();

        Livewire::actingAs($admin)
            ->test(ServicesVariants::class, ['service' => $foreignService])
            ->assertStatus(403);
    }

    public function test_orders_index_only_lists_the_current_pressings_orders(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $otherCustomer = Customer::factory()->create();
        $otherPressing->customers()->attach($otherCustomer, ['joined_at' => now()]);

        $ownOrder = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);
        $foreignOrder = (new CreateOrderAction)->handle($otherPressing, $otherCustomer, [
            ['service_id' => null, 'name' => 'Pantalon', 'unit_price_fcfa' => 1500, 'quantity' => 1],
        ]);

        Livewire::actingAs($admin)
            ->test(OrdersIndex::class)
            ->assertSee($ownOrder->order_number)
            ->assertDontSee($foreignOrder->order_number);
    }

    // --- RB-02 : isolation entre clients (API app mobile) ------------------

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $pressing = Pressing::factory()->create();
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        $pressing->customers()->attach($owner, ['joined_at' => now()]);

        $order = (new CreateOrderAction)->handle($pressing, $owner, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->bearer($intruder))
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertForbidden();
    }

    public function test_customer_orders_list_never_includes_another_customers_orders(): void
    {
        $pressing = Pressing::factory()->create();
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();
        $pressing->customers()->attach([$customerA->id, $customerB->id], ['joined_at' => now()]);

        $orderA = (new CreateOrderAction)->handle($pressing, $customerA, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);
        $orderB = (new CreateOrderAction)->handle($pressing, $customerB, [
            ['service_id' => null, 'name' => 'Pantalon', 'unit_price_fcfa' => 1500, 'quantity' => 1],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->bearer($customerA))
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonFragment(['order_number' => $orderA->order_number])
            ->assertJsonMissing(['order_number' => $orderB->order_number]);
    }

    public function test_customer_cannot_report_an_issue_on_another_customers_order(): void
    {
        $pressing = Pressing::factory()->create();
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        $pressing->customers()->attach($owner, ['joined_at' => now()]);

        $order = (new CreateOrderAction)->handle($pressing, $owner, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->bearer($intruder))
            ->postJson("/api/v1/orders/{$order->id}/issues", ['category' => 'missing_item'])
            ->assertForbidden();

        $this->assertSame(0, $order->issues()->count());
    }

    public function test_customer_cannot_list_issues_of_another_customers_order(): void
    {
        $pressing = Pressing::factory()->create();
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        $pressing->customers()->attach($owner, ['joined_at' => now()]);

        $order = (new CreateOrderAction)->handle($pressing, $owner, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->bearer($intruder))
            ->getJson("/api/v1/orders/{$order->id}/issues")
            ->assertForbidden();
    }
}
