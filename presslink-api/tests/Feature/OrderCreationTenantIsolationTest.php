<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PressingRole;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Régression pour l'audit d'isolation multi-tenant : un client ne doit
 * jamais pouvoir être rattaché à une commande d'un pressing auquel il
 * n'appartient pas, même via une requête Livewire forgée qui contourne
 * la liste de clients affichée dans l'UI (RB-01 / RB-03).
 */
class OrderCreationTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_pick_customer_rejects_a_customer_of_another_pressing(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $otherPressing = Pressing::factory()->create();
        $foreignCustomer = Customer::factory()->create();
        $otherPressing->customers()->attach($foreignCustomer, ['joined_at' => now()]);

        $this->actingAs($admin);

        Livewire::test(OrdersCreate::class)
            ->call('pickCustomer', $foreignCustomer->id)
            ->assertForbidden();
    }

    public function test_create_rejects_a_forged_selected_customer_id_from_another_pressing(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $otherPressing = Pressing::factory()->create();
        $foreignCustomer = Customer::factory()->create();
        $otherPressing->customers()->attach($foreignCustomer, ['joined_at' => now()]);

        $this->actingAs($admin);

        // Simule une requête Livewire forgée qui contourne pickCustomer() en
        // fixant directement la propriété publique selectedCustomerId.
        Livewire::test(OrdersCreate::class)
            ->set('selectedCustomerId', $foreignCustomer->id)
            ->set('pickerService', 'other')
            ->set('pickerCustomName', 'Nappe')
            ->set('pickerCustomPrice', 1000)
            ->call('addPickedItem')
            ->call('create')
            ->assertForbidden();

        $this->assertDatabaseMissing('orders', ['customer_id' => $foreignCustomer->id]);
    }

    public function test_confirmation_step_does_not_leak_a_foreign_customers_details(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $otherPressing = Pressing::factory()->create();
        $foreignCustomer = Customer::factory()->create(['first_name' => 'Secret', 'last_name' => 'Concurrent']);
        $otherPressing->customers()->attach($foreignCustomer, ['joined_at' => now()]);

        $this->actingAs($admin);

        Livewire::test(OrdersCreate::class)
            ->set('selectedCustomerId', $foreignCustomer->id)
            ->assertDontSee('Secret Concurrent');
    }

    public function test_create_order_action_rejects_a_customer_not_belonging_to_the_pressing(): void
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        // Le client n'est volontairement PAS attaché à $pressing.

        $this->expectException(InvalidArgumentException::class);

        (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);
    }
}
