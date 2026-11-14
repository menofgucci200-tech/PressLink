<?php

namespace Tests\Feature;

use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Mail\DeliveryRequestAlert;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\PickupRequest;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Ramassage et livraison, lot 3 (côté appli client) : se faire livrer une
 * commande prête, suivre et annuler la livraison.
 */
class OrderDeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private Customer $customer;

    private CustomerAddress $address;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['presslink.delivery.launched' => true]);
        // Lundi 16 novembre 2026, 9 h.
        $this->travelTo(Carbon::parse('2026-11-16 09:00'));

        $this->pressing = Pressing::factory()->create(['name' => 'Pressing Élégance', 'opening_hours' => null]);
        Subscription::factory()->for($this->pressing)->active(SubscriptionPlan::Business)->create();
        $this->pressing->update(['delivery_enabled' => true, 'delivery_fee_fcfa' => 1500]);
        $this->pressing->serviceAreas()->create(['commune' => 'Cocody']);

        $this->customer = Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné']);
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);
        $this->address = CustomerAddress::factory()->for($this->customer)->create(['commune' => 'Cocody', 'neighborhood' => 'Riviera 3', 'is_default' => true]);

        $this->order = Order::factory()->for($this->pressing)->for($this->customer)->withStatus(OrderStatus::Prete)->create();
        $this->order->items()->create(['service_id' => null, 'name' => 'Costume', 'unit_price_fcfa' => 2500, 'quantity' => 2]);
        $this->order->recordPayment(2000, PaymentMethod::Cash);

        $this->withHeader('Authorization', 'Bearer '.$this->customer->createToken('test')->plainTextToken);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'address_id' => $this->address->id,
            'delivery_date' => '2026-11-17',
            'slot_from' => '14:00',
            'slot_to' => '18:00',
            'note' => 'Appelez en arrivant',
            ...$overrides,
        ];
    }

    private function adminWithEmail(): User
    {
        $admin = User::factory()->create(['email' => 'gerant@pressing.test']);
        $this->pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return $admin;
    }

    public function test_a_ready_order_offers_delivery_with_the_fee_and_the_slots(): void
    {
        $this->getJson("/api/v1/orders/{$this->order->id}/delivery")->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('fee_fcfa', 1500)
            ->assertJsonPath('communes', ['Cocody'])
            ->assertJsonPath('days.0.label', 'Aujourd\'hui')
            ->assertJsonPath('days.1.date', '2026-11-17');

        $this->getJson("/api/v1/orders/{$this->order->id}")->assertOk()
            ->assertJsonPath('delivery_offer.fee_fcfa', 1500)
            ->assertJsonPath('delivery', null)
            ->assertJsonPath('picked_up', false);

        // Gratuite au-delà du montant choisi par le pressing.
        $this->pressing->update(['delivery_free_from_fcfa' => 5000]);
        $this->getJson("/api/v1/orders/{$this->order->id}/delivery")->assertJsonPath('fee_fcfa', 0);
    }

    public function test_delivery_is_not_offered_before_the_order_is_ready_or_when_the_service_is_off(): void
    {
        $inProgress = Order::factory()->for($this->pressing)->for($this->customer)->withStatus(OrderStatus::Traitement)->create();
        $this->getJson("/api/v1/orders/{$inProgress->id}/delivery")
            ->assertJsonPath('available', false)
            ->assertJsonPath('reason', 'La livraison se demande quand la commande est prête.');
        $this->getJson("/api/v1/orders/{$inProgress->id}")->assertJsonPath('delivery_offer', null);

        config(['presslink.delivery.launched' => false]);
        $this->getJson("/api/v1/orders/{$this->order->id}/delivery")->assertJsonPath('available', false);
        $this->postJson("/api/v1/orders/{$this->order->id}/delivery", $this->payload())->assertStatus(422);
    }

    public function test_the_customer_asks_for_delivery_and_the_pressing_is_alerted(): void
    {
        Mail::fake();
        $this->adminWithEmail();

        $this->postJson("/api/v1/orders/{$this->order->id}/delivery", $this->payload())->assertCreated()
            ->assertJsonPath('status', 'scheduled')
            ->assertJsonPath('status_label', 'Livraison prévue')
            ->assertJsonPath('schedule_label', 'demain, 14:00 – 18:00')
            ->assertJsonPath('delivery_fee_fcfa', 1500)
            ->assertJsonPath('amount_to_collect_fcfa', 4500)
            ->assertJsonPath('can_cancel', true);

        $delivery = OrderDelivery::sole();
        $this->assertSame('Riviera 3', $delivery->neighborhood);
        $this->assertSame('Appelez en arrivant', $delivery->note);
        Mail::assertSent(DeliveryRequestAlert::class, fn (DeliveryRequestAlert $mail) => ! $mail->cancelled && $mail->hasTo('gerant@pressing.test'));

        $this->getJson("/api/v1/orders/{$this->order->id}")->assertOk()
            ->assertJsonPath('delivery.status', 'scheduled')
            ->assertJsonPath('delivery_offer', null);

        // Une seule livraison prévue par commande.
        $this->postJson("/api/v1/orders/{$this->order->id}/delivery", $this->payload())->assertStatus(422)
            ->assertJsonPath('message', 'Une livraison est déjà prévue pour cette commande.');
    }

    public function test_the_commune_and_the_slot_must_be_offered(): void
    {
        $abobo = CustomerAddress::factory()->for($this->customer)->create(['commune' => 'Abobo']);
        $this->postJson("/api/v1/orders/{$this->order->id}/delivery", $this->payload(['address_id' => $abobo->id]))
            ->assertStatus(422)->assertJsonPath('message', 'Pressing Élégance ne livre pas à Abobo.');

        $this->postJson("/api/v1/orders/{$this->order->id}/delivery", $this->payload(['slot_from' => '19:00', 'slot_to' => '21:00']))
            ->assertStatus(422)->assertJsonPath('message', 'Ce créneau n\'est plus disponible. Choisissez-en un autre.');

        $this->assertSame(0, OrderDelivery::count());
    }

    public function test_the_customer_can_cancel_a_scheduled_delivery(): void
    {
        Mail::fake();
        $this->adminWithEmail();
        $this->postJson("/api/v1/orders/{$this->order->id}/delivery", $this->payload())->assertCreated();

        $this->postJson("/api/v1/orders/{$this->order->id}/delivery/cancel")->assertOk()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('can_cancel', false);

        $this->assertSame(OrderDeliveryStatus::Cancelled, OrderDelivery::sole()->status);
        Mail::assertSent(DeliveryRequestAlert::class, fn (DeliveryRequestAlert $mail) => $mail->cancelled);
        $this->postJson("/api/v1/orders/{$this->order->id}/delivery/cancel")->assertStatus(422);

        // Elle peut être redemandée.
        $this->getJson("/api/v1/orders/{$this->order->id}")->assertJsonPath('delivery_offer.fee_fcfa', 1500);
    }

    public function test_orders_of_other_customers_are_out_of_reach(): void
    {
        $other = Order::factory()->for($this->pressing)->withStatus(OrderStatus::Prete)->create();

        $this->getJson("/api/v1/orders/{$other->id}/delivery")->assertNotFound();
        $this->postJson("/api/v1/orders/{$other->id}/delivery", $this->payload())->assertNotFound();
        $this->postJson("/api/v1/orders/{$other->id}/delivery/cancel")->assertNotFound();
    }

    public function test_the_app_knows_a_delivered_order_and_a_picked_up_one(): void
    {
        OrderDelivery::factory()->for($this->order)->create(['status' => OrderDeliveryStatus::Delivered, 'delivered_at' => now()]);
        $pickup = PickupRequest::factory()->for($this->pressing)->for($this->customer)->create(['status' => 'picked_up', 'order_id' => $this->order->id]);

        $this->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.0.delivered', true);
        $this->getJson("/api/v1/orders/{$this->order->id}")
            ->assertJsonPath('delivered', true)
            ->assertJsonPath('picked_up', true)
            ->assertJsonPath('delivery.status_label', 'Livrée');
        $this->getJson("/api/v1/pickup-requests/{$pickup->id}")
            ->assertJsonPath('order_id', $this->order->id)
            ->assertJsonPath('status_label', 'Linge ramassé');
    }
}
