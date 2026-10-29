<?php

namespace Tests\Feature;

use App\Actions\Demo\ResetDemoPressing;
use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\CreateOrderAction;
use App\Enums\ActivityAction;
use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Exceptions\PressingClosedException;
use App\Livewire\Cash\Index as CashIndex;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Orders\Show as OrdersShow;
use App\Livewire\Pressing\OpenStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * En dehors des horaires, l'accueil des clients est en lecture seule
 * (commandes, nouveaux clients, paiements, remises, annulations) ; l'atelier,
 * la clôture de caisse et les réglages continuent. L'administrateur peut
 * ouvrir exceptionnellement, ce qui est inscrit au journal.
 */
class OpeningHoursTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private Customer $customer;

    /** Lundi 19 octobre 2026. */
    private const MONDAY = '2026-10-19';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::MONDAY.' 10:00');

        $hours = [];
        foreach (['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'] as $day) {
            $hours[$day] = ['open' => '08:00', 'close' => '19:00', 'closed' => false];
        }
        $hours['dimanche'] = ['open' => null, 'close' => null, 'closed' => true];

        $this->pressing = Pressing::factory()->create(['opening_hours' => $hours]);
        $this->customer = Customer::factory()->create();
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staff(PressingRole $role = PressingRole::Employee): User
    {
        $user = User::factory()->create();
        $this->pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);
        $this->actingAs($user);

        return $user;
    }

    private function order(int $paid = 0): Order
    {
        return (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => null, 'name' => 'Costume', 'unit_price_fcfa' => 5000, 'quantity' => 1],
        ], amountPaidFcfa: $paid);
    }

    private function closeShop(): void
    {
        Carbon::setTestNow(self::MONDAY.' 20:00');
        $this->pressing->refresh();
    }

    public function test_opening_follows_the_schedule_without_margin(): void
    {
        $this->assertTrue($this->pressing->isOpenNow());

        Carbon::setTestNow(self::MONDAY.' 18:59');
        $this->assertTrue($this->pressing->isOpenNow());

        Carbon::setTestNow(self::MONDAY.' 19:00');
        $this->assertFalse($this->pressing->isOpenNow());
        $this->assertSame('demain à 08:00', $this->pressing->nextOpeningLabel());

        Carbon::setTestNow('2026-10-24 20:00'); // samedi soir, fermé le dimanche
        $this->assertSame('lundi à 08:00', $this->pressing->nextOpeningLabel());

        Carbon::setTestNow(self::MONDAY.' 07:00');
        $this->assertSame('aujourd\'hui à 08:00', $this->pressing->nextOpeningLabel());
    }

    public function test_no_order_can_be_created_when_closed(): void
    {
        $this->staff();
        $piece = Service::factory()->for($this->pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);
        $this->closeShop();

        try {
            $this->order();
            $this->fail('Une commande ne doit pas être créée pressing fermé.');
        } catch (PressingClosedException $e) {
            $this->assertStringContainsString('réouverture demain à 08:00', $e->getMessage());
        }

        Livewire::test(OrdersCreate::class)
            ->assertSee('Pressing fermé')
            ->call('pickCustomer', $this->customer->id)
            ->call('addPiece', $piece->id)
            ->call('create')
            ->assertSet('errorMessage', fn ($message) => str_contains($message, 'Pressing fermé'));

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_workshop_work_continues_but_hand_over_payment_and_cancellation_wait(): void
    {
        $admin = $this->staff(PressingRole::Admin);
        $order = $this->order(1000);
        $this->closeShop();

        $change = new ChangeOrderStatusAction;
        $change->handle($order, OrderStatus::Traitement);
        $change->handle($order->fresh(), OrderStatus::Prete);
        $this->assertSame(OrderStatus::Prete, $order->fresh()->status);

        $this->expectException(PressingClosedException::class);
        $change->handle($order->fresh(), OrderStatus::Recuperee);
    }

    public function test_cancellation_and_payments_wait_for_opening(): void
    {
        $admin = $this->staff(PressingRole::Admin);
        $order = $this->order(1000);
        $this->closeShop();

        try {
            (new CancelOrderAction)->handle($order, $admin, CancellationReason::EntryError, null, CancelOrderAction::KEEP);
            $this->fail('L\'annulation doit attendre l\'ouverture.');
        } catch (PressingClosedException) {
            $this->assertSame(OrderStatus::Recue, $order->fresh()->status);
        }

        Livewire::test(OrdersShow::class, ['order' => $order])
            ->assertSee('l\'encaissement reprendra à l\'ouverture', false)
            ->assertDontSee('Annuler la commande…')
            ->set('paymentAmount', '1000')
            ->call('recordPayment')
            ->assertHasErrors('paymentAmount');

        $this->assertSame(1000, $order->fresh()->amount_paid_fcfa);
    }

    public function test_no_new_customer_when_closed(): void
    {
        $this->staff();
        $this->closeShop();

        Livewire::test(ClientsIndex::class)
            ->set('firstName', 'Koffi')->set('lastName', 'Bamba')->set('phone', '0708091011')
            ->call('createClient')
            ->assertSet('errorMessage', fn ($message) => str_contains($message, 'Pressing fermé'));

        $this->assertDatabaseMissing('customers', ['phone' => '+2250708091011']);
    }

    public function test_cash_closing_stays_possible_after_closing_time(): void
    {
        $this->staff(PressingRole::Admin);
        $this->closeShop();

        Livewire::test(CashIndex::class)
            ->set('declaredCash', '0')
            ->call('close')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('cash_closings', 1);
    }

    public function test_the_admin_opens_exceptionally_and_it_is_logged(): void
    {
        $this->staff(PressingRole::Admin);
        $this->closeShop();

        Livewire::test(OpenStatus::class)->call('openExceptionally', 'hour');

        $this->pressing->refresh();
        $this->assertTrue($this->pressing->isOpenNow());
        $this->assertSame('21:00', $this->pressing->exceptional_open_until->format('H:i'));
        $this->assertDatabaseHas('activity_logs', ['pressing_id' => $this->pressing->id, 'action' => ActivityAction::ExceptionalOpening->value]);
        $this->order();

        Carbon::setTestNow(self::MONDAY.' 21:01');
        $this->assertFalse($this->pressing->fresh()->isOpenNow());
    }

    public function test_an_employee_cannot_open_exceptionally(): void
    {
        $this->staff();
        $this->closeShop();

        Livewire::test(OpenStatus::class)->call('openExceptionally', 'day')->assertForbidden();
        $this->assertFalse($this->pressing->fresh()->isOpenNow());
    }

    public function test_the_dashboard_shows_the_closed_banner(): void
    {
        $this->staff(PressingRole::Admin);
        $this->closeShop();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pressing fermé · réouverture demain à 08:00')
            ->assertSee('Ouvrir exceptionnellement');
    }

    public function test_the_demo_pressing_stays_open_at_any_hour(): void
    {
        $this->pressing->update(['code' => ResetDemoPressing::PRESSING_CODE, 'is_demo' => true]);
        $this->closeShop();

        $this->assertTrue($this->pressing->acceptsCustomers());
        $this->order();
        $this->assertDatabaseCount('orders', 1);
    }
}
