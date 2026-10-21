<?php

namespace Tests\Feature;

use App\Enums\OrderIssueCategory;
use App\Enums\OrderIssueStatus;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Pressings\Show as AdminPressingsShow;
use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Indicateurs business et liste « À surveiller » de la vue globale
 * Super Admin.
 */
class SuperAdminDashboardInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
    }

    /** @return array<string, int> */
    private function watchlistCounts(): array
    {
        $watchlist = Livewire::test(AdminDashboard::class)->viewData('watchlist');

        return collect($watchlist)->pluck('count', 'key')->all();
    }

    public function test_monthly_recurring_revenue_counts_active_subscriptions_only(): void
    {
        Subscription::factory()->active(SubscriptionPlan::Starter)->create();
        Subscription::factory()->active(SubscriptionPlan::Premium)->create(['billing_cycle' => 'annual']);
        Subscription::factory()->create(['plan' => SubscriptionPlan::Business]); // essai : exclu

        Livewire::test(AdminDashboard::class)
            ->assertViewHas('monthlyRecurringRevenue', 20_000 + (int) round(500_000 / 12))
            ->assertViewHas('payingPressings', 2)
            ->assertViewHas('planBreakdown', fn (array $rows) => array_column($rows, 'count') === [1, 1, 1]);
    }

    public function test_order_volume_ignores_canceled_orders_and_compares_with_previous_period(): void
    {
        $pressing = Pressing::factory()->create();
        Order::factory()->for($pressing)->create(['total_fcfa' => 6_000]);
        Order::factory()->for($pressing)->withStatus(OrderStatus::Annulee)->create(['total_fcfa' => 9_000]);
        Order::factory()->for($pressing)->create(['total_fcfa' => 4_000, 'created_at' => now()->subDays(40)]);

        Livewire::test(AdminDashboard::class)
            ->set('period', '30')
            ->assertViewHas('orderVolume', 6_000)
            ->assertViewHas('totalOrders', 2)
            ->assertViewHas('volumeChangePct', 50)
            ->assertViewHas('ordersChangePct', 100)
            ->set('period', 'all')
            ->assertViewHas('orderVolume', 10_000)
            ->assertViewHas('volumeChangePct', null);
    }

    public function test_orders_chart_covers_the_whole_period(): void
    {
        $pressing = Pressing::factory()->create();
        Order::factory()->for($pressing)->count(3)->create();
        Order::factory()->for($pressing)->create(['created_at' => now()->subDays(3)]);

        Livewire::test(AdminDashboard::class)
            ->set('period', '7')
            ->assertViewHas('chart', fn (array $chart) => count($chart['bars']) === 8
                && array_sum(array_column($chart['bars'], 'count')) === 4
                && $chart['max'] === 3);
    }

    public function test_watchlist_flags_subscriptions_that_need_attention(): void
    {
        Subscription::factory()->create(['trial_ends_at' => now()->addDays(3)]);
        Subscription::factory()->create(['trial_ends_at' => now()->subDay()]);
        Subscription::factory()->create(['status' => SubscriptionStatus::Expired]);
        Subscription::factory()->active(SubscriptionPlan::Starter)->create(['orders_used' => 1_400]);

        $counts = $this->watchlistCounts();

        $this->assertSame(2, $counts['expired']);
        $this->assertSame(1, $counts['trial-ending']);
        $this->assertSame(1, $counts['quota']);
    }

    public function test_watchlist_flags_dormant_pressings_and_open_issues(): void
    {
        $dormant = Pressing::factory()->create(['name' => 'Pressing Endormi', 'created_at' => now()->subMonth()]);
        Order::factory()->for($dormant)->create(['created_at' => now()->subDays(20)]);

        $busy = Pressing::factory()->create(['created_at' => now()->subMonth()]);
        $order = Order::factory()->for($busy)->create();
        OrderIssue::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'category' => OrderIssueCategory::cases()[0],
            'description' => 'Tache restée',
            'status' => OrderIssueStatus::Open,
        ]);

        Pressing::factory()->create(); // récent : pas encore « inactif »

        Livewire::test(AdminDashboard::class)
            ->assertViewHas('watchlist', function (array $watchlist) use ($dormant) {
                $byKey = collect($watchlist)->keyBy('key');

                return $byKey['dormant']['pressings']->pluck('id')->all() === [$dormant->id]
                    && $byKey['issues']['count'] === 1;
            })
            ->assertSee('Pressing Endormi');
    }

    public function test_top_pressings_are_ranked_by_orders_in_the_period(): void
    {
        $small = Pressing::factory()->create(['name' => 'Petit Pressing']);
        $big = Pressing::factory()->create(['name' => 'Grand Pressing']);
        Pressing::factory()->create(['name' => 'Pressing Sans Commande']);
        Order::factory()->for($small)->create();
        Order::factory()->for($big)->count(3)->create(['total_fcfa' => 1_000]);

        Livewire::test(AdminDashboard::class)
            ->assertViewHas('topPressings', fn ($top) => $top->pluck('name')->all() === ['Grand Pressing', 'Petit Pressing']
                && (int) $top->first()->period_volume === 3_000);
    }

    public function test_super_admin_can_change_plan_and_billing_cycle(): void
    {
        $subscription = Subscription::factory()->active(SubscriptionPlan::Starter)->create();

        Livewire::test(AdminPressingsShow::class, ['pressing' => $subscription->pressing])
            ->set('plan', SubscriptionPlan::Business->value)
            // La formule choisie propose son nombre de commandes par mois.
            ->assertSet('ordersLimit', (string) SubscriptionPlan::Business->ordersLimit())
            ->set('billingCycle', 'annual')
            ->call('saveSubscription')
            ->assertHasNoErrors();

        $subscription->refresh();
        $this->assertSame(SubscriptionPlan::Business, $subscription->plan);
        $this->assertSame(SubscriptionPlan::Business->ordersLimit(), $subscription->orders_limit);
        $this->assertSame('annual', $subscription->billing_cycle);
    }
}
