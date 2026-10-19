<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PressingRole;
use App\Livewire\Dashboard;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Livewire\Orders\Show as OrdersShow;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\User;
use App\Notifications\OrderReadyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);
    }

    private function staff(PressingRole $role = PressingRole::Admin): User
    {
        $user = User::factory()->create();
        $this->pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    private function order(int $total = 10000, int $paid = 0): Order
    {
        return (new CreateOrderAction)->handle(
            $this->pressing,
            $this->customer,
            [['service_id' => null, 'service_variant_id' => null, 'name' => 'Costume', 'color' => null, 'unit_price_fcfa' => $total, 'quantity' => 1]],
            amountPaidFcfa: $paid,
        );
    }

    private function wizardUpToPayment(): Testable
    {
        $service = Service::factory()->for($this->pressing)->create(['name' => 'Chemise', 'price_fcfa' => 2500]);

        return Livewire::test(OrdersCreate::class)
            ->call('pickCustomer', $this->customer->id)
            ->call('next')
            ->call('selectPrestation', $service->pressing->cleaningPrestation()->id)
            ->call('addPiece', $service->id)
            ->call('incrementItem', 0)
            ->call('next');
    }

    public function test_the_wizard_requires_a_payment_choice_before_confirmation(): void
    {
        $this->actingAs($this->staff());

        $this->wizardUpToPayment()
            ->call('next')
            ->assertHasErrors(['paymentChoice' => 'required'])
            ->assertSet('step', 3);
    }

    public function test_an_order_paid_in_full_at_drop_off_is_settled(): void
    {
        $this->actingAs($this->staff());

        $this->wizardUpToPayment()
            ->set('paymentChoice', 'full')
            ->set('paymentMethod', PaymentMethod::Wave->value)
            ->call('next')
            ->assertSet('step', 4)
            ->call('create');

        $order = Order::firstOrFail();
        $this->assertSame(5000, $order->amount_paid_fcfa);
        $this->assertSame(0, $order->balance_due_fcfa);
        $this->assertSame(PaymentStatus::Paid, $order->paymentStatus());
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'amount_fcfa' => 5000, 'method' => 'wave']);
    }

    public function test_a_deposit_leaves_the_rest_to_pay_at_pickup(): void
    {
        $this->actingAs($this->staff());

        $this->wizardUpToPayment()
            ->set('paymentChoice', 'partial')
            ->set('depositAmount', '2000')
            ->call('next')
            ->call('create');

        $order = Order::firstOrFail();
        $this->assertSame(2000, $order->amount_paid_fcfa);
        $this->assertSame(3000, $order->balance_due_fcfa);
        $this->assertSame(PaymentStatus::Partial, $order->paymentStatus());
    }

    public function test_a_deposit_must_stay_below_the_total(): void
    {
        $this->actingAs($this->staff());

        $this->wizardUpToPayment()
            ->set('paymentChoice', 'partial')
            ->set('depositAmount', '5000')
            ->call('next')
            ->assertHasErrors(['depositAmount' => 'max'])
            ->set('depositAmount', '0')
            ->call('next')
            ->assertHasErrors(['depositAmount' => 'min']);
    }

    public function test_paying_at_pickup_records_no_payment(): void
    {
        $this->actingAs($this->staff());

        $this->wizardUpToPayment()
            ->set('paymentChoice', 'later')
            ->call('next')
            ->call('create');

        $order = Order::firstOrFail();
        $this->assertSame(0, $order->amount_paid_fcfa);
        $this->assertSame(PaymentStatus::Unpaid, $order->paymentStatus());
        $this->assertDatabaseCount('order_payments', 0);
    }

    public function test_staff_can_record_the_balance_but_not_more_than_what_is_due(): void
    {
        $order = $this->order(10000, 4000);
        $this->actingAs($this->staff(PressingRole::Employee));

        Livewire::test(OrdersShow::class, ['order' => $order])
            ->call('openPaymentForm')
            ->assertSet('paymentAmount', '6000')
            ->set('paymentAmount', '7000')
            ->call('recordPayment')
            ->assertHasErrors(['paymentAmount' => 'max'])
            ->set('paymentAmount', '6000')
            ->set('paymentMethod', PaymentMethod::OrangeMoney->value)
            ->call('recordPayment')
            ->assertHasNoErrors();

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->paymentStatus());
    }

    public function test_settle_and_recover_collects_the_balance_and_hands_over_the_order(): void
    {
        $order = $this->order(8000, 3000);
        $order->update(['status' => OrderStatus::Traitement]);
        $order->update(['status' => OrderStatus::Prete]);
        $this->actingAs($this->staff(PressingRole::Employee));

        Livewire::test(OrdersShow::class, ['order' => $order])
            ->set('paymentMethod', PaymentMethod::Cash->value)
            ->call('settleAndRecover');

        $order->refresh();
        $this->assertSame(OrderStatus::Recuperee, $order->status);
        $this->assertSame(8000, $order->amount_paid_fcfa);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'amount_fcfa' => 5000, 'note' => 'Solde au retrait']);
    }

    public function test_only_pressing_admins_can_remove_a_payment(): void
    {
        $order = $this->order(6000, 6000);
        $payment = $order->payments()->firstOrFail();

        $this->actingAs($this->staff(PressingRole::Employee));
        Livewire::test(OrdersShow::class, ['order' => $order])
            ->call('removePayment', $payment->id)
            ->assertForbidden();

        $this->actingAs($this->staff(PressingRole::Admin));
        Livewire::test(OrdersShow::class, ['order' => $order])
            ->call('removePayment', $payment->id);

        $this->assertSame(0, $order->fresh()->amount_paid_fcfa);
    }

    public function test_the_receipt_shows_the_payment_and_the_remaining_balance(): void
    {
        $order = $this->order(12500, 5000);
        $this->actingAs($this->staff(PressingRole::Employee));

        $this->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Acompte versé')
            ->assertSee('7 500 F')
            ->assertSee('Reste à payer au retrait');

        $this->get(route('orders.receipt', ['order' => $order, 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_staff_of_another_pressing_cannot_open_the_receipt(): void
    {
        $order = $this->order();
        $other = Pressing::factory()->create();
        $intruder = User::factory()->create();
        $other->staff()->attach($intruder, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        $this->actingAs($intruder)->get(route('orders.receipt', $order))->assertForbidden();
    }

    public function test_orders_list_filters_by_payment_status(): void
    {
        $paid = $this->order(5000, 5000);
        $partial = $this->order(5000, 1000);
        $unpaid = $this->order(5000, 0);
        $this->actingAs($this->staff());

        Livewire::test(OrdersIndex::class)
            ->set('payment', 'acompte')
            ->assertSee($partial->order_number)
            ->assertDontSee($paid->order_number)
            ->assertDontSee($unpaid->order_number)
            ->set('payment', Pressing::UNSETTLED_FILTER)
            ->assertSee($partial->order_number)
            ->assertSee($unpaid->order_number)
            ->assertDontSee($paid->order_number);
    }

    public function test_dashboard_revenue_counts_payments_received_and_what_is_still_due(): void
    {
        $this->order(10000, 4000);
        $this->order(3000, 3000);
        $cancelled = $this->order(2000, 2000);
        $cancelled->update(['status' => OrderStatus::Annulee]);
        $this->actingAs($this->staff());

        Livewire::test(Dashboard::class)
            ->assertViewHas('revenuePeriod', 7000)
            ->assertViewHas('outstanding', 6000);
    }

    public function test_the_customer_app_sees_the_balance_and_the_ready_notification_mentions_it(): void
    {
        $order = $this->order(9000, 4000);
        Sanctum::actingAs($this->customer, guard: 'sanctum');

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('amount_paid_fcfa', 4000)
            ->assertJsonPath('balance_due_fcfa', 5000)
            ->assertJsonPath('payment_status', 'acompte')
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.method_label', 'Espèces');

        $body = (new OrderReadyNotification($order->fresh()))->body();
        $this->assertStringContainsString('Reste à payer au retrait : 5 000 F', $body);
    }
}
