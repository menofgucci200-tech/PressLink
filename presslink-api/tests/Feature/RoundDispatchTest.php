<?php

namespace Tests\Feature;

use App\Actions\Deliveries\OrderDeliveries;
use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Livewire\Orders\ScheduleDelivery;
use App\Livewire\Orders\Show;
use App\Livewire\Round\Index;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\PickupRequest;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\DeliveryScheduledNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le pressing organise lui-même la tournée : il programme la livraison d'une
 * commande prête depuis sa fiche, et confie chaque passage à un coursier.
 * Chaque coursier voit ses passages et ceux qui ne sont confiés à personne.
 */
class RoundDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private User $manager;

    private User $moussa;

    private User $kone;

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
        $this->pressing->serviceAreas()->create(['commune' => 'Yopougon']);

        $this->manager = User::factory()->create(['name' => 'Gérante']);
        $this->pressing->staff()->attach($this->manager, ['role' => PressingRole::Admin->value, 'role_id' => $this->pressing->defaultRole(Role::MANAGER)->id, 'is_active' => true]);
        $this->moussa = $this->courier('Moussa');
        $this->kone = $this->courier('Koné');

        $this->customer = Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné']);
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);
    }

    private function courier(string $name): User
    {
        $courier = User::factory()->create(['name' => $name]);
        $this->pressing->staff()->attach($courier, ['role' => PressingRole::Employee->value, 'role_id' => $this->pressing->defaultRole(Role::COURIER)->id, 'is_active' => true]);

        return $courier;
    }

    /** Commande prête de 5 000 F, dont 2 000 F déjà payés. */
    private function readyOrder(): Order
    {
        $order = Order::factory()->for($this->pressing)->for($this->customer)->withStatus(OrderStatus::Prete)->create();
        $order->items()->create(['service_id' => null, 'name' => 'Costume', 'unit_price_fcfa' => 2500, 'quantity' => 2]);
        $order->recordPayment(2000, PaymentMethod::Cash);

        return $order->fresh();
    }

    public function test_the_pressing_schedules_the_delivery_of_a_ready_order_and_the_customer_is_told(): void
    {
        Notification::fake();
        $order = $this->readyOrder();
        CustomerAddress::factory()->for($this->customer)->create(['label' => 'Maison', 'commune' => 'Cocody', 'neighborhood' => 'Riviera 3', 'landmark' => 'Portail vert']);
        CustomerAddress::factory()->for($this->customer)->create(['label' => 'Bureau', 'commune' => 'Plateau', 'landmark' => 'Tour B']);
        $this->actingAs($this->manager);

        Livewire::test(Show::class, ['order' => $order])->assertSee('Programmer la livraison');

        $component = Livewire::test(ScheduleDelivery::class, ['order' => $order])
            ->assertSee('Frais de livraison : 1 500 F')
            ->call('openForm');
        // Seules les adresses des communes desservies sont proposées.
        $saved = $this->customer->addresses()->where('label', 'Maison')->value('id');
        $this->assertSame(["saved-{$saved}", 'new'], array_keys($component->instance()->addressChoices()));

        $component->assertSet('addressChoice', "saved-{$saved}")
            ->assertSee('À encaisser à la porte : 4 500 F')
            ->set('date', '2026-11-17')
            ->set('slot', '14:00-18:00')
            ->set('courierId', (string) $this->moussa->id)
            ->set('note', 'Appeler en arrivant')
            ->call('schedule')
            ->assertHasNoErrors()
            ->assertRedirect(route('orders.show', $order));

        $delivery = OrderDelivery::sole();
        $this->assertSame(OrderDeliveryStatus::Scheduled, $delivery->status);
        $this->assertSame(['Maison', 'Cocody', 'Riviera 3', 'Portail vert'], [$delivery->address_label, $delivery->commune, $delivery->neighborhood, $delivery->landmark]);
        $this->assertSame(['2026-11-17', '14:00', '18:00'], [$delivery->delivery_date->toDateString(), $delivery->slot_from, $delivery->slot_to]);
        $this->assertSame(1500, $delivery->delivery_fee_fcfa);
        $this->assertSame($this->moussa->id, $delivery->courier_id);
        $this->assertSame('Appeler en arrivant', $delivery->note);
        Notification::assertSentTo($this->customer, DeliveryScheduledNotification::class);
        $this->assertTrue(ActivityLog::where('description', 'like', '%programmée par le pressing%confiée à Moussa')->exists());

        // Une seule livraison à la fois : le bouton disparaît.
        Livewire::test(Show::class, ['order' => $order->fresh()])->assertDontSee('Programmer la livraison')->assertSee('Livraison prévue');
    }

    public function test_a_new_address_must_be_in_a_served_commune_with_a_landmark(): void
    {
        Notification::fake();
        $order = $this->readyOrder();
        $this->actingAs($this->manager);

        Livewire::test(ScheduleDelivery::class, ['order' => $order])
            ->call('openForm')
            ->assertSet('addressChoice', 'new')
            ->set('commune', 'Plateau')
            ->call('schedule')
            ->assertHasErrors(['commune', 'landmark'])
            ->set('commune', 'Yopougon')
            ->set('neighborhood', 'Selmer')
            ->set('landmark', 'Derrière la mosquée')
            ->call('schedule')
            ->assertHasNoErrors();

        $delivery = OrderDelivery::sole();
        $this->assertSame(['Adresse', 'Yopougon', 'Selmer'], [$delivery->address_label, $delivery->commune, $delivery->neighborhood]);
        $this->assertNull($delivery->courier_id);
    }

    public function test_the_pickup_address_is_offered_first_for_linen_picked_up_at_home(): void
    {
        Notification::fake();
        $order = $this->readyOrder();
        PickupRequest::factory()->for($this->pressing)->for($this->customer)->create([
            'order_id' => $order->id, 'status' => 'picked_up', 'commune' => 'Cocody', 'neighborhood' => 'Angré', 'landmark' => 'Immeuble bleu', 'address_label' => 'Chez moi',
        ]);
        $this->actingAs($this->manager);

        Livewire::test(ScheduleDelivery::class, ['order' => $order->fresh()])
            ->call('openForm')
            ->assertSet('addressChoice', 'pickup')
            ->call('schedule')
            ->assertHasNoErrors();

        $this->assertSame(['Chez moi', 'Angré', 'Immeuble bleu'], array_values(OrderDelivery::sole()->only(['address_label', 'neighborhood', 'landmark'])));
    }

    public function test_only_the_counter_schedules_and_only_for_a_ready_order_of_a_pressing_that_delivers(): void
    {
        $order = $this->readyOrder();

        // Le coursier ne programme pas.
        $this->actingAs($this->moussa);
        Livewire::test(ScheduleDelivery::class, ['order' => $order])->assertDontSee('Programmer la livraison')->call('openForm')->assertForbidden();

        $this->actingAs($this->manager);
        $inProgress = Order::factory()->for($this->pressing)->for($this->customer)->withStatus(OrderStatus::Traitement)->create();
        Livewire::test(ScheduleDelivery::class, ['order' => $inProgress])->assertDontSee('Programmer la livraison');

        $this->pressing->update(['delivery_enabled' => false]);
        Livewire::test(ScheduleDelivery::class, ['order' => $order->fresh()])->assertDontSee('Programmer la livraison');

        $this->expectExceptionMessage('ne propose pas la livraison');
        app(OrderDeliveries::class)->schedule($order->fresh(), $this->manager, ['commune' => 'Cocody', 'landmark' => 'Ici'], ['delivery_date' => '2026-11-17', 'slot_from' => '08:00', 'slot_to' => '12:00']);
    }

    public function test_the_manager_hands_out_the_stops_and_each_courier_sees_his_own_and_the_unassigned_ones(): void
    {
        $forMoussa = OrderDelivery::factory()->for($this->readyOrder())->create(['delivery_date' => '2026-11-16', 'slot_from' => '08:00', 'slot_to' => '12:00']);
        $pickup = PickupRequest::factory()->accepted()->for($this->pressing)
            ->for(Customer::factory()->create(['first_name' => 'Yao', 'last_name' => 'Ba']))
            ->create(['pickup_date' => '2026-11-16', 'slot_from' => '14:00', 'slot_to' => '18:00']);
        $nobodys = PickupRequest::factory()->accepted()->for($this->pressing)
            ->for(Customer::factory()->create(['first_name' => 'Kofi', 'last_name' => 'Diallo']))
            ->create(['pickup_date' => '2026-11-16', 'slot_from' => '08:00', 'slot_to' => '12:00']);

        $this->actingAs($this->manager);
        Livewire::test(Index::class)
            ->assertSee('Confié à')
            ->call('assign', "delivery-{$forMoussa->id}", (string) $this->moussa->id)
            ->call('assign', "pickup-{$pickup->id}", (string) $this->kone->id)
            ->assertDispatched('toast', type: 'success')
            // Le filtre montre la tournée d'un coursier.
            ->set('courierFilter', (string) $this->kone->id)
            ->assertSee('Yao Ba')->assertDontSee('Awa Koné')->assertDontSee('Kofi Diallo')
            ->set('courierFilter', 'none')
            ->assertSee('Kofi Diallo')->assertDontSee('Yao Ba');

        $this->assertSame($this->moussa->id, $forMoussa->fresh()->courier_id);
        $this->assertSame($this->kone->id, $pickup->fresh()->courier_id);

        // Moussa : sa livraison et le ramassage de personne, pas celui de Koné.
        $this->actingAs($this->moussa);
        $this->get(route('round.index'))->assertOk()
            ->assertSee('Awa Koné')->assertSee('Kofi Diallo')->assertDontSee('Yao Ba')
            ->assertSee('Confié à vous')
            ->assertSeeInOrder(['data-testid="round-badge"', '2'], false);
        Livewire::test(Index::class)->assertDontSee('data-testid="assign-courier"', false)
            ->call('assign', "pickup-{$nobodys->id}", (string) $this->moussa->id)->assertForbidden();

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(Index::class)->call('startCollect', $pickup->id);
    }

    public function test_a_courier_cannot_finish_a_stop_handed_to_someone_else_and_strangers_are_refused(): void
    {
        Notification::fake();
        $delivery = OrderDelivery::factory()->for($this->readyOrder())->create(['delivery_date' => '2026-11-16', 'courier_id' => $this->kone->id]);

        try {
            app(OrderDeliveries::class)->markMissed($delivery, $this->moussa);
            $this->fail('Moussa ne doit pas pouvoir clore la livraison de Koné.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Cette livraison est confiée à un autre coursier.', $e->getMessage());
        }

        // La gérante peut toujours la clore.
        app(OrderDeliveries::class)->markMissed($delivery, $this->manager);
        $this->assertSame(OrderDeliveryStatus::Missed, $delivery->fresh()->status);

        // On ne confie un passage qu'à un coursier de l'équipe.
        $outsider = User::factory()->create(['name' => 'Inconnu']);
        $other = OrderDelivery::factory()->for($this->readyOrder())->create(['delivery_date' => '2026-11-16']);
        $this->expectExceptionMessage('Inconnu ne fait pas la tournée de ce pressing.');
        app(OrderDeliveries::class)->assign($other, $this->manager, $outsider);
    }
}
