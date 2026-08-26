<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Couvre les règles métier fondamentales du Cahier des fonctionnalités §23
 * (RB-01 à RB-10) sur le modèle de données de la Phase 1.
 */
class OrderBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(int $unitPrice = 1000, int $quantity = 1): array
    {
        return ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => $unitPrice, 'quantity' => $quantity];
    }

    public function test_order_requires_at_least_one_item(): void
    {
        // RB-04
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        (new CreateOrderAction)->handle($pressing, $customer, []);
    }

    public function test_order_total_is_computed_from_its_items(): void
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();

        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            $this->makeItem(1000, 2),
            $this->makeItem(1500, 1),
        ]);

        $this->assertSame(3500, $order->fresh()->total_fcfa);
    }

    public function test_order_creation_generates_a_unique_order_number(): void
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();

        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);

        $this->assertMatchesRegularExpression('/^PL-\d{6}$/', $order->order_number);
    }

    public function test_status_changes_are_recorded_in_history(): void
    {
        // RB-05
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);

        $order->update(['status' => OrderStatus::Traitement]);
        $order->update(['status' => OrderStatus::Prete]);

        $this->assertSame(
            [OrderStatus::Recue, OrderStatus::Traitement, OrderStatus::Prete],
            $order->statusHistory()->pluck('status')->all(),
        );
    }

    public function test_only_a_ready_order_can_be_marked_as_recovered(): void
    {
        // RB-07
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);

        $this->expectException(RuntimeException::class);

        $order->update(['status' => OrderStatus::Recuperee]);
    }

    public function test_recovering_an_order_stamps_who_and_when(): void
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);

        $order->update(['status' => OrderStatus::Traitement]);
        $order->update(['status' => OrderStatus::Prete]);
        $order->update(['status' => OrderStatus::Recuperee]);

        $order->refresh();
        $this->assertNotNull($order->recovered_at);
    }

    public function test_expired_subscription_blocks_new_orders(): void
    {
        // RB-09
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        Subscription::factory()->for($pressing)->create([
            'orders_limit' => 1,
            'orders_used' => 1,
        ]);

        $this->expectException(RuntimeException::class);

        (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);
    }

    public function test_deleting_an_order_is_a_soft_delete(): void
    {
        // RB-10
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);

        $order->delete();

        $this->assertSoftDeleted($order);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_staff_cannot_view_orders_of_a_pressing_they_do_not_belong_to(): void
    {
        // RB-01 / RB-08
        $myPressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();

        $employee = User::factory()->create();
        $myPressing->staff()->attach($employee, ['role' => PressingRole::Employee->value, 'is_active' => true]);

        $customer = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($otherPressing, $customer, [$this->makeItem()]);

        $this->assertFalse($employee->can('view', $order));
    }

    public function test_admin_can_view_orders_of_their_own_pressing(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = User::factory()->create();
        $pressing->staff()->attach($admin, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        $customer = Customer::factory()->create();
        $order = (new CreateOrderAction)->handle($pressing, $customer, [$this->makeItem()]);

        $this->assertTrue($admin->can('view', $order));
    }
}
