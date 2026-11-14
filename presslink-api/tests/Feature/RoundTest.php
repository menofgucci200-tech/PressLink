<?php

namespace Tests\Feature;

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\CreateOrderAction;
use App\Actions\Pickups\PickupRequests;
use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\PickupRequestStatus;
use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Livewire\Round\Index;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\PickupRequest;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\DeliveryMissedNotification;
use App\Notifications\DeliveryRescheduledNotification;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderRecoveredNotification;
use App\Notifications\PickupMissedNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ramassage et livraison, lot 3 (côté pressing) : la tournée du coursier,
 * le linge ramassé qui devient un dépôt rapide, la livraison payée à la
 * porte et le client absent.
 */
class RoundTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private User $courier;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['presslink.delivery.launched' => true]);
        // Lundi 16 novembre 2026, 9 h.
        $this->travelTo(Carbon::parse('2026-11-16 09:00'));

        $this->pressing = Pressing::factory()->create(['name' => 'Pressing Élégance', 'opening_hours' => null]);
        Subscription::factory()->for($this->pressing)->active(SubscriptionPlan::Business)->create();
        $this->pressing->update(['pickup_enabled' => true, 'delivery_enabled' => true, 'pickup_fee_fcfa' => 1000, 'delivery_fee_fcfa' => 1500]);
        $this->pressing->serviceAreas()->create(['commune' => 'Cocody']);

        // Le coursier n'a que l'accès « Tournée ».
        $role = Role::create(['pressing_id' => $this->pressing->id, 'key' => 'coursier', 'name' => 'Coursier', 'permissions' => [Permission::DeliveriesRun->value]]);
        $this->courier = User::factory()->create(['name' => 'Moussa']);
        $this->pressing->staff()->attach($this->courier, ['role' => PressingRole::Employee->value, 'role_id' => $role->id, 'is_active' => true]);

        $this->customer = Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné']);
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);
    }

    private function acceptedPickup(array $attributes = []): PickupRequest
    {
        return PickupRequest::factory()->accepted()->for($this->pressing)->for($this->customer)
            ->create(['pickup_date' => '2026-11-16', 'slot_from' => '14:00', 'slot_to' => '18:00', ...$attributes]);
    }

    /** Commande prête de 5 000 F, dont 2 000 F déjà payés. */
    private function readyOrder(): Order
    {
        $order = Order::factory()->for($this->pressing)->for($this->customer)->withStatus(OrderStatus::Prete)->create();
        $order->items()->create(['service_id' => null, 'name' => 'Costume', 'unit_price_fcfa' => 2500, 'quantity' => 2]);
        $order->recordPayment(2000, PaymentMethod::Cash);

        return $order->fresh();
    }

    private function scheduledDelivery(Order $order, array $attributes = []): OrderDelivery
    {
        return OrderDelivery::factory()->for($order)->create(['delivery_date' => '2026-11-16', 'delivery_fee_fcfa' => 1500, ...$attributes]);
    }

    public function test_the_round_lists_the_days_stops_in_slot_order_with_late_ones_first(): void
    {
        $late = $this->acceptedPickup(['pickup_date' => '2026-11-15', 'slot_from' => '08:00', 'slot_to' => '12:00']);
        $this->acceptedPickup(['customer_id' => Customer::factory()->create(['first_name' => 'Yao', 'last_name' => 'N\'Guessan'])->id]);
        $this->scheduledDelivery($this->readyOrder(), ['slot_from' => '08:00', 'slot_to' => '12:00']);
        $tomorrow = PickupRequest::factory()->accepted()->for($this->pressing)
            ->create(['customer_id' => Customer::factory()->create(['first_name' => 'Kofi', 'last_name' => 'Ba'])->id, 'pickup_date' => '2026-11-17']);
        PickupRequest::factory()->for($this->pressing)->create(['pickup_date' => '2026-11-16']); // pas encore acceptée

        $this->actingAs($this->courier);
        $this->get(route('dashboard'))->assertSeeInOrder(['data-testid="round-badge"', '3'], false);

        $this->get(route('round.index'))->assertOk()
            ->assertSeeInOrder(['En retard', 'Awa Koné', 'Livraison', 'Awa Koné', 'À encaisser : 4 500 F', 'Yao N&#039;Guessan'], false)
            ->assertDontSee('Kofi Ba');

        Livewire::test(Index::class, ['day' => '2026-11-17'])->assertSee('Kofi Ba')->assertDontSee('Yao');
        $this->assertSame(PickupRequestStatus::Accepted, $late->fresh()->status);
        $this->assertNotNull($tomorrow);
    }

    public function test_collecting_the_linen_creates_a_quick_deposit_even_when_the_pressing_is_closed(): void
    {
        Notification::fake();
        $this->pressing->update(['opening_hours' => collect(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'])
            ->mapWithKeys(fn ($day) => [$day => ['closed' => false, 'open' => '15:00', 'close' => '19:00']])->all()]);
        $request = $this->acceptedPickup(['note' => 'Sonnez deux fois', 'items_estimate' => 6]);
        $this->actingAs($this->courier);

        Livewire::test(Index::class)
            ->call('startCollect', $request->id)
            ->assertSet('pieces', '6')
            ->set('pieces', '7')
            ->set('note', 'Une chemise tachée')
            ->call('confirmCollect')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $request->refresh();
        $order = $request->order;
        $this->assertSame(PickupRequestStatus::PickedUp, $request->status);
        $this->assertSame($this->courier->id, $request->picked_up_by);
        $this->assertTrue($order->details_pending);
        $this->assertSame(7, $order->declared_pieces);
        $this->assertSame("Une chemise tachée\nMessage du client : Sonnez deux fois", $order->notes);
        $this->assertSame(1, $this->pressing->subscription->fresh()->orders_used);
        Notification::assertSentTo($this->customer, OrderCreatedNotification::class,
            fn (OrderCreatedNotification $n) => $n->title() === 'Linge ramassé' && str_contains($n->body(), '7 pièce(s)'));

        // Une demande ramassée ne se ramasse pas deux fois.
        Livewire::test(Index::class)->call('startCollect', $request->id)->call('confirmCollect')->assertDispatched('toast', type: 'error');
        $this->assertSame(1, Order::count());
    }

    public function test_the_pickup_fee_is_added_with_the_details_unless_the_order_is_free(): void
    {
        $owner = User::factory()->create();
        $this->pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $this->pressing->update(['delivery_free_from_fcfa' => 10000]);
        $complete = function (int $unitPrice) use ($owner): Order {
            $request = $this->acceptedPickup(['customer_id' => Customer::factory()->create()->id]);
            $this->pressing->customers()->attach($request->customer_id, ['joined_at' => now()]);
            $this->actingAs($this->courier);
            $order = app(PickupRequests::class)->markPickedUp($request, $this->courier, 3);
            $this->actingAs($owner);

            return app(CreateOrderAction::class)->completeQuickDeposit($order,
                [['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => $unitPrice, 'quantity' => 3]]);
        };

        $paying = $complete(1000);
        $this->assertSame(4000, (int) $paying->total_fcfa);
        $this->assertTrue($paying->items->contains('name', Order::PICKUP_FEE_LINE));
        $this->assertSame(3, $paying->pieceCount());

        $free = $complete(4000);
        $this->assertSame(12000, (int) $free->total_fcfa);
        $this->assertFalse($free->items->contains('name', Order::PICKUP_FEE_LINE));
    }

    public function test_a_missed_pickup_is_closed_and_the_customer_told(): void
    {
        Notification::fake();
        $request = $this->acceptedPickup();
        $this->actingAs($this->courier);

        Livewire::test(Index::class)
            ->call('startMissed', 'pickup', $request->id)
            ->call('confirmMissed')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame(PickupRequestStatus::Missed, $request->fresh()->status);
        $this->assertSame(0, Order::count());
        Notification::assertSentTo($this->customer, PickupMissedNotification::class);
    }

    public function test_delivering_collects_the_balance_and_the_fee_at_the_door(): void
    {
        Notification::fake();
        $order = $this->readyOrder();
        $delivery = $this->scheduledDelivery($order);
        $this->actingAs($this->courier);

        $this->get(route('round.index'))->assertSee('À encaisser : 4 500 F')->assertSee('Dont frais de livraison : 1 500 F');

        Livewire::test(Index::class)
            ->call('startDeliver', $delivery->id)
            ->call('confirmDeliver')
            ->assertHasErrors('paymentMethod')
            ->set('paymentMethod', PaymentMethod::Wave->value)
            ->call('confirmDeliver')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success', message: 'Commande livrée : 4 500 F encaissés.');

        $order->refresh();
        $this->assertSame(OrderStatus::Recuperee, $order->status);
        $this->assertSame(6500, (int) $order->total_fcfa);
        $this->assertSame(0, $order->balance_due_fcfa);
        $this->assertTrue($order->items->contains('name', Order::DELIVERY_FEE_LINE));
        $this->assertSame(2, $order->pieceCount());
        $payment = $order->payments->last();
        $this->assertSame(4500, (int) $payment->amount_fcfa);
        $this->assertSame(PaymentMethod::Wave, $payment->method);
        $this->assertSame($this->courier->id, $payment->recorded_by);
        $this->assertSame(OrderDeliveryStatus::Delivered, $delivery->fresh()->status);
        Notification::assertSentTo($this->customer, OrderRecoveredNotification::class, fn ($n) => $n->title() === 'Commande livrée');

        $this->get(route('round.index'))->assertSee('livré à 09:00')->assertDontSee('data-testid="round-stop"', false);
    }

    public function test_a_missed_delivery_keeps_the_order_ready_and_tells_the_customer(): void
    {
        Notification::fake();
        $order = $this->readyOrder();
        $delivery = $this->scheduledDelivery($order);
        $this->actingAs($this->courier);

        Livewire::test(Index::class)->call('startMissed', 'delivery', $delivery->id)->call('confirmMissed');

        $this->assertSame(OrderDeliveryStatus::Missed, $delivery->fresh()->status);
        $this->assertSame(OrderStatus::Prete, $order->fresh()->status);
        $this->assertSame(5000, (int) $order->fresh()->total_fcfa);
        Notification::assertSentTo($this->customer, DeliveryMissedNotification::class);
    }

    public function test_a_delivery_can_be_rescheduled(): void
    {
        Notification::fake();
        $delivery = $this->scheduledDelivery($this->readyOrder());
        $this->actingAs($this->courier);

        Livewire::test(Index::class)
            ->call('startReschedule', $delivery->id)
            ->assertSet('slot', '14:00-18:00')
            ->set('date', '2026-11-18')
            ->call('confirmReschedule')
            ->assertHasNoErrors();

        $delivery->refresh();
        $this->assertSame('2026-11-18', $delivery->delivery_date->toDateString());
        $this->assertTrue($delivery->rescheduled);
        Notification::assertSentTo($this->customer, DeliveryRescheduledNotification::class,
            fn ($n) => str_contains($n->body(), 'mercredi 18 novembre, 14:00 – 18:00'));
    }

    public function test_handing_the_order_over_at_the_counter_cancels_the_delivery(): void
    {
        $owner = User::factory()->create();
        $this->pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $order = $this->readyOrder();
        $delivery = $this->scheduledDelivery($order);
        $this->actingAs($owner);

        app(ChangeOrderStatusAction::class)->handle($order, OrderStatus::Recuperee, PaymentMethod::Cash);

        $delivery->refresh();
        $this->assertSame(OrderDeliveryStatus::Cancelled, $delivery->status);
        $this->assertSame('Commande récupérée au pressing', $delivery->cancel_reason);
        $this->assertFalse($order->fresh()->items->contains('name', Order::DELIVERY_FEE_LINE));
    }

    public function test_a_member_without_the_round_access_only_reads_it(): void
    {
        $request = $this->acceptedPickup();
        $role = Role::create(['pressing_id' => $this->pressing->id, 'key' => 'laveur', 'name' => 'Laveur', 'permissions' => []]);
        $washer = User::factory()->create();
        $this->pressing->staff()->attach($washer, ['role' => PressingRole::Employee->value, 'role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($washer);

        $this->get(route('round.index'))->assertOk()->assertSee('Awa Koné')->assertDontSee('wire:click="startCollect', false);
        Livewire::test(Index::class)->call('startCollect', $request->id)->assertForbidden();
        $this->assertSame(PickupRequestStatus::Accepted, $request->fresh()->status);
    }

    public function test_the_round_needs_the_feature_and_stops_of_other_pressings_are_out_of_reach(): void
    {
        $starter = Pressing::factory()->create();
        Subscription::factory()->for($starter)->active(SubscriptionPlan::Starter)->create();
        $user = User::factory()->create();
        $starter->staff()->attach($user, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $this->actingAs($user)->get(route('round.index'))->assertForbidden();

        $other = PickupRequest::factory()->accepted()->create();
        $this->actingAs($this->courier);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(Index::class)->call('startCollect', $other->id);
    }

    public function test_new_pressings_give_the_round_access_to_their_staff_roles(): void
    {
        $this->assertContains(Permission::DeliveriesRun->value, Role::defaults()[Role::EMPLOYEE]['permissions']);
        $this->assertContains(Permission::DeliveriesRun->value, Role::defaults()[Role::MANAGER]['permissions']);
    }
}
