<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Dashboard;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(Pressing $pressing): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return $user;
    }

    private function makeOrder(Pressing $pressing, OrderStatus $status, array $attributes = []): Order
    {
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        return Order::factory()->for($pressing)->for($customer)->withStatus($status)->create($attributes);
    }

    public function test_an_order_ready_for_several_days_gets_a_reminder_then_a_forgotten_alert(): void
    {
        $pressing = Pressing::factory()->create();
        $order = $this->makeOrder($pressing, OrderStatus::Prete);
        $order->statusHistory()->delete();
        OrderStatusHistory::create(['order_id' => $order->id, 'status' => OrderStatus::Prete, 'created_at' => now()->subDays(6)]);

        $alert = Order::withReadySince()->find($order->id)->delayAlert();
        $this->assertSame(['label' => 'Prête depuis 6 j', 'tone' => 'warning'], $alert);

        OrderStatusHistory::query()->update(['created_at' => now()->subDays(20)]);
        $this->assertSame('danger', Order::withReadySince()->find($order->id)->delayAlert()['tone']);
    }

    public function test_an_order_past_its_expected_date_is_flagged_late(): void
    {
        $pressing = Pressing::factory()->create();
        $late = $this->makeOrder($pressing, OrderStatus::Traitement, ['expected_at' => now()->subDays(2)]);
        $onTime = $this->makeOrder($pressing, OrderStatus::Traitement, ['expected_at' => now()->addDay()]);
        $collected = $this->makeOrder($pressing, OrderStatus::Recuperee, ['expected_at' => now()->subDays(2)]);

        $this->assertSame(['label' => 'En retard de 2 j', 'tone' => 'danger'], $late->delayAlert());
        $this->assertNull($onTime->delayAlert());
        $this->assertNull($collected->delayAlert());
    }

    public function test_dashboard_kpi_cards_open_the_orders_list_filtered_by_status_and_period(): void
    {
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->makeAdmin($pressing));

        $url = route('orders.index', ['status' => 'prete', 'dateFrom' => now()->subDays(6)->toDateString(), 'dateTo' => now()->toDateString()]);

        Livewire::test(Dashboard::class)
            ->call('setPeriod', '7d')
            ->assertSeeHtml('href="'.e($url).'"');
    }

    public function test_orders_list_rows_are_clickable_and_status_counts_follow_the_search(): void
    {
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->makeAdmin($pressing));
        $kouame = $this->makeOrder($pressing, OrderStatus::Prete);
        $kouame->customer->update(['last_name' => 'Kouamé']);
        $this->makeOrder($pressing, OrderStatus::Prete);
        $this->makeOrder($pressing, OrderStatus::Recue);

        Livewire::test(OrdersIndex::class)
            ->assertViewHas('counts', fn (array $counts) => $counts['prete'] === 2 && $counts['recue'] === 1)
            ->assertSeeHtml('data-href="'.route('orders.show', $kouame).'"')
            ->set('search', 'Kouamé')
            ->assertViewHas('counts', fn (array $counts) => ($counts['prete'] ?? 0) === 1 && ! isset($counts['recue']));
    }

    public function test_super_admin_chart_bars_link_to_the_orders_of_that_day(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $pressing = Pressing::factory()->create();
        $this->makeOrder($pressing, OrderStatus::Recue);
        $this->actingAs($admin);

        $today = now()->toDateString();

        Livewire::test(AdminDashboard::class)
            ->set('period', '7')
            ->assertSeeHtml('href="'.e(route('admin.orders.index', ['du' => $today, 'au' => $today])).'"')
            ->assertSeeHtml('href="'.e(route('admin.pressings.index', ['status' => 'active'])).'"');
    }
}
