<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Livewire\Admin\Pressings\Show as AdminPressingShow;
use App\Livewire\Admin\SalesAgents\Index as AdminSalesAgents;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\PressingSignup;
use App\Livewire\Sales\Claims;
use App\Livewire\Sales\Pressings\Create as SalesPressingsCreate;
use App\Livewire\Sales\Pressings\Show as SalesPressingsShow;
use App\Models\Pressing;
use App\Models\SalesAgent;
use App\Models\SalesAgentClaim;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Commerciaux de terrain : créés par le Super Admin, ils inscrivent des
 * pressings (ou les font s'inscrire avec leur code), les suivent dans leur
 * espace et signalent ceux qui se sont inscrits seuls.
 */
class SalesAgentsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function agent(array $attributes = []): SalesAgent
    {
        return SalesAgent::factory()->create(['code' => 'AWA-1234', ...$attributes]);
    }

    private function pressingWithOwner(string $phone = '+2250701020304', ?SalesAgent $agent = null): Pressing
    {
        $pressing = Pressing::factory()->create(['phone' => '+2252722000000']);
        Subscription::startTrialFor($pressing);
        $owner = User::factory()->create(['phone' => $phone]);
        $pressing->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        if ($agent) {
            $pressing->attachToSalesAgent($agent);
        }

        return $pressing;
    }

    public function test_the_super_admin_creates_a_sales_agent_who_logs_into_his_own_space(): void
    {
        Livewire::actingAs($this->superAdmin())->test(AdminSalesAgents::class)
            ->set('name', 'Awa Koné')
            ->set('phone', '+2250708124400')
            ->set('password', 'provisoire1')
            ->call('create')
            ->assertHasNoErrors()
            ->assertSee('Awa Koné est créé');

        $agent = SalesAgent::sole();
        $this->assertStringStartsWith('AWA-', $agent->code);
        $this->assertSame(20, $agent->commissionRate());
        auth()->logout();

        Livewire::test(Login::class)
            ->set('login', '07 08 12 44 00')
            ->set('password', 'provisoire1')
            ->call('authenticate')
            ->assertRedirect(route('sales.dashboard'));

        $this->get(route('sales.dashboard'))->assertOk()->assertSee($agent->code)->assertSee('Mes pressings');
        $this->get(route('dashboard'))->assertRedirect(route('sales.dashboard'));
        $this->get(route('orders.index'))->assertRedirect(route('sales.dashboard'));
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_only_sales_agents_reach_the_sales_space(): void
    {
        $pressing = $this->pressingWithOwner();

        $this->actingAs($pressing->admins()->first())->get(route('sales.dashboard'))->assertForbidden();
    }

    public function test_a_suspended_agent_can_no_longer_log_in(): void
    {
        $agent = $this->agent(['suspended_at' => now()]);
        $agent->user->update(['phone' => '+2250708124400', 'password' => 'provisoire1']);

        Livewire::test(Login::class)
            ->set('login', '0708124400')
            ->set('password', 'provisoire1')
            ->call('authenticate')
            ->assertHasErrors('login');
        $this->assertGuest();

        $this->actingAs($agent->user)->get(route('sales.dashboard'))->assertRedirect(route('login'));
    }

    public function test_an_agent_signs_up_a_pressing_whose_free_month_starts_now(): void
    {
        $this->travelTo('2026-11-18 10:00:00');
        $agent = $this->agent();

        Livewire::actingAs($agent->user)->test(SalesPressingsCreate::class)
            ->set('pressingName', 'Pressing Bel Air')
            ->set('city', 'Yopougon')
            ->set('pressingPhone', '27 23 45 67 89')
            ->set('ownerName', 'Yao Kouamé')
            ->set('ownerPhone', '05 06 07 08 09')
            ->set('password', 'provisoire1')
            ->call('create')
            ->assertHasErrors('termsShown')
            ->set('termsShown', true)
            ->call('create')
            ->assertHasNoErrors()
            ->assertSee('Pressing Bel Air est inscrit');

        $pressing = Pressing::where('name', 'Pressing Bel Air')->sole();
        $this->assertTrue($pressing->salesAgent->is($agent));
        $this->assertSame('2026-12-18', $pressing->subscription->trial_ends_at->toDateString());
        $owner = $pressing->admins()->sole();
        $this->assertSame('+2250506070809', $owner->phone);
        $this->assertTrue($owner->must_change_password);
    }

    public function test_a_pressing_signing_up_with_the_agent_code_is_attached_to_him(): void
    {
        $this->travelTo('2026-11-18 10:00:00');
        RateLimiter::clear('inscription-pressing:127.0.0.1');
        $agent = $this->agent();

        $this->get(route('signup', ['code' => 'AWA-1234']))->assertOk()->assertSee('AWA-1234');

        $form = fn () => Livewire::withQueryParams(['code' => 'awa-1234'])->test(PressingSignup::class)
            ->set('pressingName', 'Pressing du Plateau')
            ->set('city', 'Plateau')
            ->set('pressingPhone', '27 22 44 55 66')
            ->call('next')
            ->set('ownerName', 'Awa Kouassi')
            ->set('ownerPhone', '07 08 12 44 00')
            ->set('password', 'motdepasse1')
            ->set('acceptTerms', true);

        $form()->set('salesCode', 'INCONNU-1')->call('register')->assertHasErrors('salesCode');
        $form()->call('register')->assertHasNoErrors();

        $this->assertTrue(Pressing::where('name', 'Pressing du Plateau')->sole()->salesAgent->is($agent));
    }

    public function test_an_agent_sees_only_his_pressings_without_their_customers(): void
    {
        $agent = $this->agent();
        $mine = $this->pressingWithOwner('+2250701020304', $agent);
        $other = $this->pressingWithOwner('+2250701020305');
        SubscriptionRequest::create(['pressing_id' => $mine->id, 'kind' => SubscriptionRequest::KIND_PLAN, 'plan' => SubscriptionPlan::Business, 'cycle' => 'monthly', 'amount_fcfa' => 30_000, 'status' => SubscriptionRequest::DONE, 'handled_at' => now()]);
        SubscriptionRequest::create(['pressing_id' => $mine->id, 'kind' => SubscriptionRequest::KIND_ORDERS, 'extra_orders' => 50, 'amount_fcfa' => 1_000, 'status' => SubscriptionRequest::DONE, 'handled_at' => now()]);

        $this->actingAs($agent->user);
        $this->get(route('sales.pressings.index'))->assertOk()->assertSee($mine->name)->assertDontSee($other->name);
        $this->get(route('sales.pressings.show', $mine))->assertOk()
            ->assertSee('Mois offert')
            ->assertSee('30 000 F')
            ->assertDontSee('1 000 F');
        $this->get(route('sales.pressings.show', $other))->assertNotFound();

        Livewire::test(SalesPressingsShow::class, ['pressing' => $mine])
            ->call('edit')
            ->set('name', 'Nouveau nom')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Nouveau nom', $mine->fresh()->name);
    }

    public function test_a_claim_is_matched_by_phone_and_attached_once_the_super_admin_approves_it(): void
    {
        $agent = $this->agent();
        $pressing = $this->pressingWithOwner('+2250701020304');

        Livewire::actingAs($agent->user)->test(Claims::class)
            ->set('pressingName', 'Pressing Étoile')
            ->set('phone', '07 01 02 03 04')
            ->set('note', 'Présenté le 12 novembre')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('En attente')
            ->set('pressingName', 'Pressing Étoile')
            ->set('phone', '07 01 02 03 04')
            ->call('submit')
            ->assertHasErrors('phone');

        $claim = SalesAgentClaim::sole();
        $this->assertTrue($claim->pressing->is($pressing));

        Livewire::actingAs($this->superAdmin())->test(AdminSalesAgents::class)
            ->assertSee('Signalements à vérifier')
            ->call('approveClaim', $claim->id);

        $this->assertSame(SalesAgentClaim::APPROVED, $claim->fresh()->status);
        $this->assertTrue($pressing->fresh()->salesAgent->is($agent));
    }

    public function test_the_super_admin_attaches_a_pressing_to_an_agent_from_its_page(): void
    {
        $agent = $this->agent();
        $pressing = $this->pressingWithOwner();

        Livewire::actingAs($this->superAdmin())->test(AdminPressingShow::class, ['pressing' => $pressing])
            ->assertSee('Apporté par')
            ->set('salesAgentId', (string) $agent->id)
            ->call('saveSalesAgent');

        $this->assertTrue($pressing->fresh()->salesAgent->is($agent));
        $this->assertNotNull($pressing->fresh()->sales_agent_attached_at);

        $this->get(route('admin.pressings.index', ['commercial' => $agent->id]))->assertOk()->assertSee($pressing->name);
    }

    public function test_the_commission_rate_is_common_unless_the_agent_has_his_own(): void
    {
        $agent = $this->agent();

        Livewire::actingAs($this->superAdmin())->test(AdminSalesAgents::class)
            ->set('defaultRate', '25')
            ->call('saveDefaultRate')
            ->set("rates.{$agent->id}", '30')
            ->call('saveRate', $agent->id)
            ->assertHasNoErrors();

        $this->assertSame(25, SalesAgent::defaultCommissionRate());
        $this->assertSame(30, $agent->fresh()->commissionRate());
    }
}
