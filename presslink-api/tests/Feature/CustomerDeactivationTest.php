<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCustomerIsActive;
use App\Livewire\Admin\Clients\Index as AdminClientsIndex;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Désactivation d'un compte client depuis la console Super Admin : la
 * connexion à l'application est refusée et les sessions ouvertes coupées.
 */
class CustomerDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        return Customer::factory()->create(['phone' => '+2250701020304', 'password' => 'secret123']);
    }

    public function test_a_deactivated_customer_cannot_log_in(): void
    {
        $this->customer()->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/customer/login', ['phone' => '+2250701020304', 'password' => 'secret123'])
            ->assertForbidden()
            ->assertJson(['message' => EnsureCustomerIsActive::MESSAGE]);
    }

    public function test_a_wrong_password_does_not_reveal_that_the_account_is_deactivated(): void
    {
        $this->customer()->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/customer/login', ['phone' => '+2250701020304', 'password' => 'mauvais'])
            ->assertStatus(422)
            ->assertJsonMissing(['message' => EnsureCustomerIsActive::MESSAGE]);
    }

    public function test_an_open_session_is_cut_once_the_account_is_deactivated(): void
    {
        $customer = $this->customer();
        $token = $customer->createToken('customer-app')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/auth/customer/me', $headers)->assertOk();

        $customer->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/orders', $headers)->assertForbidden();
        $this->assertSame(0, $customer->tokens()->count());
    }

    public function test_super_admin_can_deactivate_and_reactivate_a_customer(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $customer = $this->customer();
        $customer->update(['onesignal_player_id' => 'player-123']);
        $customer->createToken('customer-app');
        $order = Order::factory()->for($customer)->create();

        $component = Livewire::test(AdminClientsIndex::class)
            ->call('showCustomer', $customer->id)
            ->call('toggleActive');

        $customer->refresh();
        $this->assertFalse($customer->is_active);
        $this->assertNull($customer->onesignal_player_id);
        $this->assertSame(0, $customer->tokens()->count());
        $this->assertModelExists($order);

        $component->set('activity', 'disabled')
            ->assertViewHas('customers', fn ($c) => $c->pluck('id')->all() === [$customer->id]);

        $component->call('toggleActive');
        $this->assertTrue($customer->refresh()->is_active);

        $this->postJson('/api/v1/auth/customer/login', ['phone' => '+2250701020304', 'password' => 'secret123'])->assertOk();
    }
}
