<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use App\Livewire\Admin\Pressings\Index as AdminPressingsIndex;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 8 — le statut "suspendu" (bouton Super Admin) doit avoir un effet
 * réel, pas seulement cosmétique : un pressing suspendu ne doit plus
 * pouvoir accueillir de nouveaux clients ni créer de nouvelles commandes.
 */
class PressingSuspensionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_join_a_suspended_pressing(): void
    {
        $pressing = Pressing::factory()->create(['code' => 'PE-4821', 'status' => PressingStatus::Suspended]);
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/pressings/join', ['code' => 'PE-4821'])
            ->assertForbidden();

        $this->assertFalse($pressing->customers()->where('customers.id', $customer->id)->exists());
    }

    public function test_customer_can_still_join_an_active_pressing(): void
    {
        $pressing = Pressing::factory()->create(['code' => 'PE-4821', 'status' => PressingStatus::Active]);
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/pressings/join', ['code' => 'PE-4821'])
            ->assertOk();
    }

    public function test_suspended_pressing_cannot_create_a_new_order(): void
    {
        $pressing = Pressing::factory()->create(['status' => PressingStatus::Suspended]);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        $this->expectException(RuntimeException::class);

        (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);
    }

    public function test_reactivating_a_pressing_restores_order_creation(): void
    {
        $pressing = Pressing::factory()->create(['status' => PressingStatus::Suspended]);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        $pressing->update(['status' => PressingStatus::Active]);

        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);

        $this->assertNotNull($order->id);
    }

    public function test_super_admin_suspending_a_pressing_blocks_it_end_to_end(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $pressing = Pressing::factory()->create(['code' => 'PE-4821', 'status' => PressingStatus::Active]);
        $pressing->staff()->attach(User::factory()->create(), ['role' => PressingRole::Admin->value, 'is_active' => true]);

        Livewire::actingAs($superAdmin)
            ->test(AdminPressingsIndex::class)
            ->call('toggleStatus', $pressing->id);

        $this->assertSame(PressingStatus::Suspended, $pressing->fresh()->status);

        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/pressings/join', ['code' => 'PE-4821'])
            ->assertForbidden();
    }
}
