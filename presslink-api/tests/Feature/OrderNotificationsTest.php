<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Pressing;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderReadyNotification;
use App\Notifications\OrderRecoveredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * RB-06 : une notification est déclenchée automatiquement lors des
 * événements configurés — Cahier §9 (créée / prête / récupérée au MVP).
 */
class OrderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(): array
    {
        return ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1];
    }

    public function test_creating_an_order_notifies_the_customer(): void
    {
        Notification::fake();

        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();

        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);

        Notification::assertSentTo($customer, OrderCreatedNotification::class, function ($notification) use ($order, $customer) {
            return $notification->toArray($customer)['order_id'] === $order->id;
        });
        Notification::assertNotSentTo($customer, OrderReadyNotification::class);
    }

    public function test_marking_an_order_ready_notifies_the_customer(): void
    {
        Notification::fake();

        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);

        $order->update(['status' => OrderStatus::Traitement]);
        $order->update(['status' => OrderStatus::Prete]);

        Notification::assertSentTo($customer, OrderReadyNotification::class);
        Notification::assertNotSentTo($customer, OrderRecoveredNotification::class);
    }

    public function test_recovering_an_order_notifies_the_customer(): void
    {
        Notification::fake();

        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);

        $order->update(['status' => OrderStatus::Traitement]);
        $order->update(['status' => OrderStatus::Prete]);
        $order->update(['status' => OrderStatus::Recuperee]);

        Notification::assertSentTo($customer, OrderRecoveredNotification::class);
    }

    public function test_customer_can_list_their_notifications(): void
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);

        $token = $customer->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications');

        $response->assertOk();
        $this->assertSame(1, $response->json('total'));
        $this->assertSame('Commande enregistrée', $response->json('data.0.data.title'));
    }

    public function test_customer_can_mark_a_notification_as_read(): void
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);

        $notification = $customer->notifications()->first();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_customer_cannot_read_another_customers_notification(): void
    {
        $pressing = Pressing::factory()->create();
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        (new CreateOrderAction)->handle($pressing, $owner, [$this->makeItem()]);

        $notification = $owner->notifications()->first();
        $token = $intruder->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertNotFound();
    }
}
