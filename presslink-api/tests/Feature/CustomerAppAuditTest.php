<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Corrections de l'audit de l'appli client (22/10/2026) côté API.
 */
class CustomerAppAuditTest extends TestCase
{
    use RefreshDatabase;

    private function headers(Customer $customer): array
    {
        return ['Authorization' => 'Bearer '.$customer->createToken('test')->plainTextToken];
    }

    public function test_the_app_can_read_every_order_page_by_page(): void
    {
        $customer = Customer::factory()->create();
        $pressing = Pressing::factory()->create();
        $ready = Order::factory()->for($customer)->for($pressing)->create(['status' => OrderStatus::Prete, 'created_at' => now()->subMonth()]);
        Order::factory()->count(26)->for($customer)->for($pressing)->create(['status' => OrderStatus::Recuperee]);
        $headers = $this->headers($customer);

        // Anciennes versions de l'appli : 20 par page, comme avant.
        $this->getJson('/api/v1/orders', $headers)
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('last_page', 2);

        // L'appli demande 100 par page : la commande prête la plus ancienne est là.
        $all = $this->getJson('/api/v1/orders?per_page=100', $headers)
            ->assertOk()
            ->assertJsonCount(27, 'data')
            ->assertJsonPath('last_page', 1);
        $this->assertContains($ready->id, array_column($all->json('data'), 'id'));

        // Jamais plus de 100 par page.
        $this->getJson('/api/v1/orders?per_page=5000', $headers)->assertJsonPath('per_page', 100);
    }

    public function test_the_profile_tells_the_app_whether_emails_are_wanted(): void
    {
        $customer = Customer::factory()->create(['email' => 'awa@exemple.ci', 'email_notifications' => false]);

        $this->getJson('/api/v1/auth/customer/me', $this->headers($customer))
            ->assertOk()
            ->assertJsonPath('email_notifications', false);

        $this->postJson('/api/v1/auth/customer/login', ['phone' => $customer->phone, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('customer.email_notifications', false);
    }

    public function test_the_email_switch_changes_nothing_else_in_the_profile(): void
    {
        $customer = Customer::factory()->create(['gender' => null, 'email' => 'awa@exemple.ci', 'email_notifications' => true]);

        $this->putJson('/api/v1/customer/profile', ['email_notifications' => false], $this->headers($customer))
            ->assertOk()
            ->assertJsonPath('email_notifications', false);

        $customer->refresh();
        $this->assertFalse($customer->email_notifications);
        $this->assertNull($customer->gender);
        $this->assertSame('awa@exemple.ci', $customer->email);
    }

    public function test_a_full_profile_update_still_requires_the_identity(): void
    {
        $customer = Customer::factory()->create();

        $this->putJson('/api/v1/customer/profile', ['first_name' => 'Awa', 'email_notifications' => true], $this->headers($customer))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name', 'gender']);
    }

    public function test_logging_out_unlinks_the_notifications_of_this_phone(): void
    {
        $customer = Customer::factory()->create(['onesignal_player_id' => 'telephone-a']);

        $this->postJson('/api/v1/auth/customer/logout', ['player_id' => 'telephone-a'], $this->headers($customer))->assertOk();

        $this->assertNull($customer->fresh()->onesignal_player_id);
    }

    public function test_logging_out_another_phone_keeps_the_current_one(): void
    {
        $customer = Customer::factory()->create(['onesignal_player_id' => 'telephone-b']);

        $this->postJson('/api/v1/auth/customer/logout', ['player_id' => 'telephone-a'], $this->headers($customer))->assertOk();
        $this->postJson('/api/v1/auth/customer/logout', [], $this->headers($customer->fresh()))->assertOk();

        $this->assertSame('telephone-b', $customer->fresh()->onesignal_player_id);
    }

    public function test_a_shared_phone_only_notifies_the_last_account_signed_in(): void
    {
        $previous = Customer::factory()->create(['onesignal_player_id' => 'telephone-partage']);
        $next = Customer::factory()->create();

        $this->putJson('/api/v1/customer/onesignal-player-id', ['player_id' => 'telephone-partage'], $this->headers($next))->assertOk();

        $this->assertNull($previous->fresh()->onesignal_player_id);
        $this->assertSame('telephone-partage', $next->fresh()->onesignal_player_id);
    }
}
