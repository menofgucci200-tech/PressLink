<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Livewire\Admin\SalesAgents\Show as AdminSalesAgentShow;
use App\Models\Pressing;
use App\Models\SalesAgent;
use App\Models\SalesCommission;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Commissions : 20 % (taux du commercial) de chaque paiement de l'abonnement
 * d'un pressing apporté, tant qu'il reste abonné ; hors options, packs de
 * commandes et frais de paiement. L'équipe les paie mois par mois.
 */
class SalesCommissionsTest extends TestCase
{
    use RefreshDatabase;

    private function agent(array $attributes = []): SalesAgent
    {
        return SalesAgent::factory()->create($attributes);
    }

    private function pressingOf(?SalesAgent $agent): Pressing
    {
        $pressing = Pressing::factory()->create();
        Subscription::startTrialFor($pressing);
        $pressing->staff()->attach(User::factory()->create(), ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $pressing->attachToSalesAgent($agent);

        return $pressing;
    }

    private function pay(Pressing $pressing, string $kind, int $amount, array $attributes = []): SubscriptionRequest
    {
        $request = SubscriptionRequest::create([
            'pressing_id' => $pressing->id,
            'kind' => $kind,
            'cycle' => SubscriptionRequest::MONTHLY,
            'amount_fcfa' => $amount,
            'status' => SubscriptionRequest::PENDING,
            ...$attributes,
        ]);
        $request->apply(byCurrentUser: false);

        return $request->fresh();
    }

    public function test_each_subscription_payment_earns_the_agent_his_rate(): void
    {
        $this->travelTo('2026-12-20 10:00:00');
        $agent = $this->agent();
        $pressing = $this->pressingOf($agent);

        $this->pay($pressing, SubscriptionRequest::KIND_PLAN, 50_000, ['plan' => SubscriptionPlan::Premium]);
        $this->travelTo('2027-01-20 10:00:00');
        $this->pay($pressing, SubscriptionRequest::KIND_RENEWAL, 50_000);

        $commissions = $agent->commissions()->orderBy('earned_at')->get();
        $this->assertCount(2, $commissions);
        $this->assertSame([10_000, 10_000], $commissions->pluck('amount_fcfa')->all());
        $this->assertSame([20, 20], $commissions->pluck('rate')->all());
        $this->assertSame(['2026-12', '2027-01'], $commissions->map(fn ($c) => $c->earned_at->format('Y-m'))->all());
    }

    public function test_options_order_packs_and_unattached_pressings_earn_nothing(): void
    {
        $agent = $this->agent();
        $pressing = $this->pressingOf($agent);

        $this->pay($pressing, SubscriptionRequest::KIND_ORDERS, 1_500, ['extra_orders' => 100]);
        $this->pay($pressing, SubscriptionRequest::KIND_OPTION, 2_000, ['feature' => 'caisse']);
        $this->pay($this->pressingOf(null), SubscriptionRequest::KIND_PLAN, 30_000, ['plan' => SubscriptionPlan::Business]);

        $this->assertSame(0, SalesCommission::count());
    }

    public function test_the_agent_own_rate_applies_and_a_payment_is_counted_once(): void
    {
        $agent = $this->agent(['commission_rate' => 25]);
        $request = $this->pay($this->pressingOf($agent), SubscriptionRequest::KIND_PLAN, 30_000, ['plan' => SubscriptionPlan::Business]);

        SalesCommission::recordFor($request);

        $this->assertSame(7_500, SalesCommission::sole()->amount_fcfa);
    }

    public function test_the_agent_sees_his_earnings_and_the_super_admin_marks_a_month_paid(): void
    {
        $this->travelTo('2026-12-20 10:00:00');
        $agent = $this->agent();
        foreach (range(1, 3) as $i) {
            $this->pay($this->pressingOf($agent), SubscriptionRequest::KIND_PLAN, 50_000, ['plan' => SubscriptionPlan::Premium]);
        }

        $this->actingAs($agent->user);
        $this->get(route('sales.dashboard'))->assertOk()->assertSee('30 000 F');
        $this->get(route('sales.earnings'))->assertOk()->assertSee('30 000 F')->assertSee('À recevoir');

        $admin = User::factory()->create(['is_super_admin' => true]);
        Livewire::actingAs($admin)->test(AdminSalesAgentShow::class, ['salesAgent' => $agent])
            ->assertSee('Marquer 30 000 F payés')
            ->set('references.2026-12', 'Wave 31/12')
            ->call('markMonthPaid', '2026-12')
            ->assertSee('Payé');

        $this->assertSame(0, $agent->commissions()->unpaid()->count());
        $this->assertSame('Wave 31/12', SalesCommission::first()->payout_reference);

        $this->actingAs($agent->user)->get(route('sales.earnings'))->assertOk()->assertSee('Payée le');
    }
}
