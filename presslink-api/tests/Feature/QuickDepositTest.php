<?php

namespace Tests\Feature;

use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\CreateOrderAction;
use App\Enums\ActivityAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PressingRole;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Livewire\Orders\QuickDeposit;
use App\Livewire\Orders\Show as OrdersShow;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\Pressing;
use App\Models\Prestation;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\OrderDetailsCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Dépôt rapide (grosses commandes des laveries) : le client, le nombre de
 * pièces et un numéro tout de suite, puis les photos ; le détail et les prix
 * sont saisis plus tard, et la commande ne peut pas être prête avant.
 */
class QuickDepositTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private Customer $customer;

    private Service $shirt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create();
        $user = User::factory()->create();
        $this->pressing->staff()->attach($user, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $this->actingAs($user);

        $this->customer = Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Traoré']);
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);

        $this->shirt = Service::factory()->for($this->pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);
        $this->shirt->setPrice($this->repassage(), 500);
    }

    private function repassage(): Prestation
    {
        return $this->pressing->prestations()->where('name', 'Repassage')->firstOrFail();
    }

    private function deposit(int $pieces = 3): Order
    {
        return (new CreateOrderAction)->quickDeposit($this->pressing, $this->customer, $pieces);
    }

    private function completeWith(Order $deposit, int $shirts): Order
    {
        return (new CreateOrderAction)->completeQuickDeposit($deposit, [
            ['service_id' => $this->shirt->id, 'prestation_id' => $this->repassage()->id, 'prestation_name' => 'Repassage', 'name' => 'Chemise · Repassage', 'color' => null, 'unit_price_fcfa' => 500, 'quantity' => $shirts],
        ]);
    }

    public function test_a_deposit_needs_only_the_customer_and_the_piece_count(): void
    {
        Livewire::test(QuickDeposit::class)
            ->call('pickCustomer', $this->customer->id)
            ->set('declaredPieces', '40')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Dépôt enregistré')
            ->assertSee('data-testid="photo-camera"', false);

        $order = Order::firstOrFail();
        $this->assertTrue($order->details_pending);
        $this->assertSame(40, $order->declared_pieces);
        $this->assertSame(OrderStatus::Recue, $order->status);
        $this->assertSame(0, $order->total_fcfa);
        $this->assertSame(0, $order->items()->count());
        $this->assertTrue(ActivityLog::where('action', ActivityAction::QuickDepositCreated->value)->exists());
    }

    public function test_the_customer_and_the_count_are_required(): void
    {
        Livewire::test(QuickDeposit::class)
            ->set('declaredPieces', '5')
            ->call('save')
            ->assertHasErrors('clientSearch');

        Livewire::test(QuickDeposit::class)
            ->call('pickCustomer', $this->customer->id)
            ->set('declaredPieces', '0')
            ->call('save')
            ->assertHasErrors('declaredPieces');

        $this->assertSame(0, Order::count());
    }

    public function test_a_second_tap_does_not_create_a_second_deposit(): void
    {
        Livewire::test(QuickDeposit::class)
            ->call('pickCustomer', $this->customer->id)
            ->set('declaredPieces', '10')
            ->call('save')
            ->call('save');

        $this->assertSame(1, Order::count());
    }

    public function test_photos_are_taken_on_the_new_deposit_then_the_screen_resets(): void
    {
        Storage::fake(OrderPhoto::DISK);

        Livewire::test(QuickDeposit::class)
            ->call('pickCustomer', $this->customer->id)
            ->set('declaredPieces', '10')
            ->call('save')
            ->set('photo', UploadedFile::fake()->image('a.jpg', 800, 600))
            ->call('savePhoto')
            ->assertHasNoErrors()
            ->call('nextDeposit')
            ->assertSet('depositId', null)
            ->assertSet('selectedCustomerId', null)
            ->assertSet('declaredPieces', '')
            ->assertDontSee('Dépôt enregistré');

        $this->assertSame(1, Order::firstOrFail()->photos()->count());
    }

    public function test_a_deposit_counts_in_the_subscription_quota(): void
    {
        $subscription = Subscription::factory()->for($this->pressing)->create();
        $this->pressing->unsetRelation('subscription');
        $used = (int) $subscription->orders_used;

        $this->deposit();

        $this->assertSame($used + 1, (int) $subscription->fresh()->orders_used);
    }

    public function test_a_deposit_is_never_shown_as_paid(): void
    {
        $deposit = $this->deposit();

        $this->assertSame(PaymentStatus::Unpaid, $deposit->paymentStatus());
        $this->assertSame('À compléter', $deposit->totalLabel());
        $this->assertSame(0, $this->pressing->filteredOrders(['payment' => PaymentStatus::Paid->value])->count());
    }

    public function test_a_deposit_can_start_processing_but_cannot_be_ready_before_it_is_completed(): void
    {
        $deposit = $this->deposit();
        $status = new ChangeOrderStatusAction;

        $status->handle($deposit, OrderStatus::Traitement);

        try {
            $status->handle($deposit->fresh(), OrderStatus::Prete);
            $this->fail('Un dépôt sans prix ne doit pas pouvoir être prêt.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Complétez', $e->getMessage());
        }

        $this->assertSame(OrderStatus::Traitement, $deposit->fresh()->status);

        $status->handle($this->completeWith($deposit->fresh(), 3), OrderStatus::Prete);
        $this->assertSame(OrderStatus::Prete, $deposit->fresh()->status);
    }

    public function test_completing_uses_the_counter_screen_and_keeps_the_customer(): void
    {
        Notification::fake();
        $deposit = $this->deposit(2);

        Livewire::test(OrdersCreate::class, ['order' => (string) $deposit->id])
            ->assertSet('selectedCustomerId', $this->customer->id)
            ->assertSee('Compléter le dépôt '.$deposit->order_number)
            ->assertSee('2 pièces comptées au dépôt')
            ->call('addPiece', $this->shirt->id)
            ->call('addPiece', $this->shirt->id)
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(route('orders.show', $deposit));

        $deposit->refresh();
        $this->assertFalse($deposit->details_pending);
        $this->assertSame(1000, $deposit->total_fcfa);
        $this->assertSame(1, $deposit->items()->count());
        $this->assertSame(1, Order::count());
        Notification::assertSentTo($this->customer, OrderDetailsCompletedNotification::class);
    }

    public function test_the_deposit_is_paid_when_completed(): void
    {
        $deposit = $this->deposit(1);

        Livewire::test(OrdersCreate::class, ['order' => (string) $deposit->id])
            ->call('addPiece', $this->shirt->id)
            ->set('paymentChoice', 'full')
            ->call('create')
            ->assertHasNoErrors();

        $deposit->refresh();
        $this->assertSame(500, $deposit->amount_paid_fcfa);
        $this->assertSame(PaymentStatus::Paid, $deposit->paymentStatus());
    }

    public function test_a_different_count_does_not_block_but_is_logged(): void
    {
        $deposit = $this->deposit(5);

        $this->completeWith($deposit, 4);

        $log = ActivityLog::where('action', ActivityAction::QuickDepositCompleted->value)->firstOrFail();
        $this->assertStringContainsString('5 comptées au dépôt', $log->description);
        Livewire::test(OrdersShow::class, ['order' => $deposit->fresh()])->assertSee('5 pièces comptées au dépôt, 4 saisies ensuite');
    }

    public function test_a_deposit_cannot_be_completed_twice(): void
    {
        $deposit = $this->deposit(1);
        $this->completeWith($deposit, 1);

        $this->expectException(RuntimeException::class);
        $this->completeWith($deposit->fresh(), 1);
    }

    public function test_opening_a_completed_deposit_goes_back_to_the_order(): void
    {
        $deposit = $this->completeWith($this->deposit(1), 1);

        Livewire::test(OrdersCreate::class, ['order' => (string) $deposit->id])
            ->assertRedirect(route('orders.show', $deposit));
    }

    public function test_a_deposit_of_another_pressing_cannot_be_completed(): void
    {
        $other = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $other->customers()->attach($customer, ['joined_at' => now()]);
        $foreign = Order::factory()->create(['pressing_id' => $other->id, 'customer_id' => $customer->id, 'details_pending' => true, 'declared_pieces' => 1]);

        $this->get(route('orders.complete', $foreign))->assertNotFound();
    }

    public function test_the_list_flags_and_filters_deposits_to_complete(): void
    {
        $deposit = $this->deposit(7);
        $normal = (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => null, 'service_variant_id' => null, 'name' => 'Chemise', 'color' => null, 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);

        Livewire::test(OrdersIndex::class)
            ->assertSee('1 dépôt à compléter')
            ->assertSee($normal->order_number)
            ->set('awaitingDetails', true)
            ->assertSee($deposit->order_number)
            ->assertDontSee($normal->order_number);
    }

    public function test_the_order_page_asks_to_complete_and_hides_payment(): void
    {
        $deposit = $this->deposit(7);

        Livewire::test(OrdersShow::class, ['order' => $deposit])
            ->assertSee('Dépôt à compléter')
            ->assertSee(route('orders.complete', $deposit), false)
            ->assertSee('7 pièces')
            ->assertDontSee('Encaisser');
    }

    public function test_the_receipt_shows_the_count_without_a_price(): void
    {
        $deposit = $this->deposit(12);

        $this->get(route('orders.receipt', $deposit))
            ->assertOk()
            ->assertSee($deposit->order_number)
            ->assertSee('12 pièces')
            ->assertSee('Le détail et le prix seront communiqués')
            ->assertDontSee('Payée');
    }

    public function test_the_customer_app_receives_the_pending_state(): void
    {
        $deposit = $this->deposit(9);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/orders/'.$deposit->id)
            ->assertOk()
            ->assertJsonPath('details_pending', true)
            ->assertJsonPath('declared_pieces', 9)
            ->assertJsonPath('payment_status', PaymentStatus::Unpaid->value);
    }

    public function test_the_routes_open(): void
    {
        $this->get(route('orders.quick-deposit'))->assertOk()->assertSee('Dépôt rapide');

        $deposit = $this->deposit(2);
        $this->get(route('orders.complete', $deposit))->assertOk()->assertSee('2 pièces comptées au dépôt');
    }
}
