<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderReadyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OrderEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function order(Customer $customer): Order
    {
        return Order::factory()->for($customer)->create();
    }

    public function test_customer_with_email_gets_created_and_ready_emails(): void
    {
        $customer = Customer::factory()->create(['email' => 'aicha@example.com']);
        $order = $this->order($customer);

        $this->assertContains('mail', (new OrderCreatedNotification($order))->via($customer));
        $this->assertContains('mail', (new OrderReadyNotification($order))->via($customer));
    }

    public function test_no_email_without_address_or_after_unsubscribing(): void
    {
        $withoutEmail = Customer::factory()->create(['email' => null]);
        $unsubscribed = Customer::factory()->create(['email' => 'x@example.com', 'email_notifications' => false]);

        $this->assertNotContains('mail', (new OrderReadyNotification($this->order($withoutEmail)))->via($withoutEmail));
        $this->assertNotContains('mail', (new OrderReadyNotification($this->order($unsubscribed)))->via($unsubscribed));
    }

    public function test_ready_email_renders_with_items_total_and_unsubscribe_link(): void
    {
        $customer = Customer::factory()->create(['first_name' => 'Aïcha', 'email' => 'aicha@example.com']);
        $pressing = Pressing::factory()->create(['name' => 'Pressing Élégance']);
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 3],
        ]);

        $html = (string) (new OrderReadyNotification($order))->toMail($customer)->render();

        $this->assertStringContainsString('Bonjour Aïcha', $html);
        $this->assertStringContainsString('Chemise', $html);
        $this->assertStringContainsString('3 000 FCFA', $html);
        $this->assertStringContainsString('images/email-logo.png', $html);
        $this->assertStringContainsString('emails/desabonnement', $html);
    }

    public function test_status_change_sends_the_ready_email(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create(['email' => 'aicha@example.com']);
        $order = $this->order($customer);

        $order->update(['status' => OrderStatus::Traitement]);
        $order->update(['status' => OrderStatus::Prete]);

        Notification::assertSentTo($customer, OrderReadyNotification::class,
            fn ($notification, array $channels) => in_array('mail', $channels, true));
    }

    public function test_signed_unsubscribe_link_turns_emails_off(): void
    {
        $customer = Customer::factory()->create(['email' => 'aicha@example.com']);

        $this->get(URL::signedRoute('emails.unsubscribe', ['customer' => $customer->id]))
            ->assertOk()->assertSee('Désinscription confirmée');
        $this->assertFalse($customer->fresh()->email_notifications);

        $this->get(route('emails.unsubscribe', ['customer' => $customer->id]))->assertForbidden();
    }

    public function test_customer_can_toggle_emails_from_the_app(): void
    {
        $customer = Customer::factory()->create(['email' => 'aicha@example.com']);

        $this->actingAs($customer, 'sanctum')->putJson('/api/v1/customer/profile', [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'gender' => $customer->gender->value,
            'email' => 'aicha@example.com',
            'email_notifications' => false,
        ])->assertOk()->assertJsonPath('email_notifications', false);
    }
}
