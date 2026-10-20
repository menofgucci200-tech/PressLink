<?php

namespace Tests\Feature;

use App\Actions\Cash\CashDaySummary;
use App\Actions\Orders\CancelOrderAction;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Orders\CreateOrderAction;
use App\Enums\ActivityAction;
use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PressingRole;
use App\Livewire\Orders\Show as OrdersShow;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Annulation encadrée : motif obligatoire, plus d'annulation une fois la
 * commande prête, l'administrateur seul une fois le travail commencé, et
 * l'argent déjà versé rendu (sortie de caisse) ou conservé.
 */
class OrderCancellationTest extends TestCase
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

    private function staff(PressingRole $role): User
    {
        $user = User::factory()->create();
        $this->pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    private function order(int $paid = 0, OrderStatus $status = OrderStatus::Recue): Order
    {
        $order = (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => null, 'name' => 'Costume', 'unit_price_fcfa' => 5000, 'quantity' => 1],
        ], amountPaidFcfa: $paid);

        foreach (match ($status) {
            OrderStatus::Traitement => [OrderStatus::Traitement],
            OrderStatus::Prete => [OrderStatus::Traitement, OrderStatus::Prete],
            default => [],
        } as $step) {
            $order->update(['status' => $step]);
        }

        return $order->fresh();
    }

    public function test_an_employee_cancels_a_received_order_with_a_reason_kept_in_the_history(): void
    {
        $employee = $this->staff(PressingRole::Employee);
        $this->actingAs($employee);
        $order = $this->order();

        (new CancelOrderAction)->handle($order, $employee, CancellationReason::Duplicate, 'Saisie deux fois');

        $order->refresh();
        $this->assertSame(OrderStatus::Annulee, $order->status);
        $this->assertSame(CancellationReason::Duplicate, $order->cancellation_reason);
        $this->assertSame('Commande en double : Saisie deux fois', $order->statusHistory()->latest('id')->first()->note);
        $this->assertSame($employee->id, $order->statusHistory()->latest('id')->first()->changed_by);
        $this->assertStringContainsString('Commande en double', ActivityLog::where('action', ActivityAction::OrderCancelled)->firstOrFail()->description);
    }

    public function test_once_work_has_started_only_the_admin_can_cancel(): void
    {
        $employee = $this->staff(PressingRole::Employee);
        $admin = $this->staff(PressingRole::Admin);
        $order = $this->order(status: OrderStatus::Traitement);

        $this->assertFalse(CancelOrderAction::canCancel($employee, $order));
        $this->assertTrue(CancelOrderAction::canCancel($admin, $order));

        $this->actingAs($employee);
        try {
            (new CancelOrderAction)->handle($order, $employee, CancellationReason::CustomerWithdrew);
            $this->fail('Un employé ne doit pas annuler une commande en traitement.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('gérant', $e->getMessage());
        }

        $this->actingAs($admin);
        (new CancelOrderAction)->handle($order, $admin, CancellationReason::CustomerWithdrew);
        $this->assertSame(OrderStatus::Annulee, $order->fresh()->status);
    }

    public function test_a_ready_order_can_no_longer_be_cancelled_even_by_the_admin(): void
    {
        $admin = $this->staff(PressingRole::Admin);
        $this->actingAs($admin);
        $order = $this->order(status: OrderStatus::Prete);

        $this->assertFalse(CancelOrderAction::canCancel($admin, $order));
        $this->expectExceptionMessage('Une commande prête ne peut plus être annulée');

        (new CancelOrderAction)->handle($order, $admin, CancellationReason::EntryError);
    }

    public function test_other_reason_requires_a_note(): void
    {
        $employee = $this->staff(PressingRole::Employee);
        $this->actingAs($employee);

        $this->expectExceptionMessage('Précisez le motif');

        (new CancelOrderAction)->handle($this->order(), $employee, CancellationReason::Other, '   ');
    }

    public function test_a_refund_is_recorded_as_a_cash_outflow(): void
    {
        $employee = $this->staff(PressingRole::Employee);
        $this->actingAs($employee);
        $order = $this->order(paid: 2000);

        (new CancelOrderAction)->handle($order, $employee, CancellationReason::CustomerWithdrew, null, CancelOrderAction::REFUND, PaymentMethod::Cash);

        $order->refresh();
        $this->assertSame(0, $order->amount_paid_fcfa);
        $this->assertSame(0, $order->balance_due_fcfa);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'amount_fcfa' => -2000, 'method' => 'especes']);

        $cash = CashDaySummary::for($this->pressing, now());
        $this->assertSame(0, $cash['cash']);
        $this->assertSame(0, $cash['total']);
    }

    public function test_money_kept_stays_paid_and_the_choice_is_required(): void
    {
        $employee = $this->staff(PressingRole::Employee);
        $this->actingAs($employee);
        $order = $this->order(paid: 2000);

        try {
            (new CancelOrderAction)->handle($order, $employee, CancellationReason::CustomerWithdrew);
            $this->fail('Le sort de l\'argent versé doit être indiqué.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('rendu ou conservé', $e->getMessage());
        }

        (new CancelOrderAction)->handle($order, $employee, CancellationReason::CustomerWithdrew, null, CancelOrderAction::KEEP);

        $this->assertSame(2000, $order->fresh()->amount_paid_fcfa);
        $this->assertSame(0, $order->fresh()->balance_due_fcfa);
        $this->assertDatabaseMissing('order_payments', ['order_id' => $order->id, 'amount_fcfa' => -2000]);
    }

    public function test_a_plain_status_change_cannot_cancel(): void
    {
        $this->actingAs($this->staff(PressingRole::Admin));

        $this->expectException(RuntimeException::class);

        (new ChangeOrderStatusAction)->handle($this->order(), OrderStatus::Annulee);
    }

    public function test_the_order_page_cancels_through_the_form(): void
    {
        $employee = $this->staff(PressingRole::Employee);
        $this->actingAs($employee);
        $order = $this->order(paid: 1000);

        Livewire::test(OrdersShow::class, ['order' => $order])
            ->call('openCancelForm')
            ->assertSet('showCancelForm', true)
            ->call('cancelOrder')
            ->assertHasErrors(['cancelReason' => 'required', 'cancelMoney' => 'required'])
            ->set('cancelReason', CancellationReason::EntryError->value)
            ->set('cancelMoney', CancelOrderAction::REFUND)
            ->set('refundMethod', PaymentMethod::Wave->value)
            ->call('cancelOrder')
            ->assertHasNoErrors()
            ->assertSee('Motif :')
            ->assertSee('Erreur de saisie')
            ->assertSee('Remboursé au client : 1 000 F');

        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'amount_fcfa' => -1000, 'method' => 'wave']);
    }

    public function test_an_employee_cannot_open_the_form_on_an_order_in_progress(): void
    {
        $this->actingAs($this->staff(PressingRole::Employee));
        $order = $this->order(status: OrderStatus::Traitement);

        Livewire::test(OrdersShow::class, ['order' => $order])
            ->assertSee('réservée au propriétaire et au gérant', false)
            ->call('openCancelForm')
            ->assertForbidden();
    }

    public function test_the_customer_sees_the_reason_but_not_the_internal_note(): void
    {
        $employee = $this->staff(PressingRole::Employee);
        $this->actingAs($employee);
        $order = $this->order();
        (new CancelOrderAction)->handle($order, $employee, CancellationReason::ItemRefused, 'Tache impossible à enlever, client mécontent');

        $token = $this->customer->createToken('app')->plainTextToken;
        $json = $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/orders/{$order->id}")->assertOk();

        $json->assertJsonPath('cancellation_reason_label', 'Pièce refusée ou irréparable');
        $this->assertStringNotContainsString('client mécontent', $json->getContent());
    }
}
