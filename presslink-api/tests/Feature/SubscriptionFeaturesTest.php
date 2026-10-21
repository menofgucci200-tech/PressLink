<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Livewire\Admin\Pressings\Show as AdminPressingShow;
use App\Livewire\Subscription\Show as SubscriptionShow;
use App\Livewire\Team\Index as TeamIndex;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fonctions réservées à une formule (App\Enums\Feature), options achetées à
 * la carte et commandes en plus, demandées depuis la page Abonnement et
 * activées par l'équipe PressLink.
 */
class SubscriptionFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role, ?string $roleKey = null): User
    {
        $user = User::factory()->create();
        $pivot = ['role' => $role->value, 'is_active' => true];
        if ($roleKey !== null) {
            $pivot['role_id'] = $pressing->defaultRole($roleKey)->id;
        }
        $pressing->staff()->attach($user, $pivot);

        return $user;
    }

    private function pressingOn(SubscriptionPlan $plan, array $attributes = []): Pressing
    {
        $pressing = Pressing::factory()->create();
        Subscription::factory()->for($pressing)->active($plan)->create($attributes);

        return $pressing;
    }

    public function test_business_includes_starter_and_premium_includes_everything(): void
    {
        $this->assertFalse(SubscriptionPlan::Starter->includes(Feature::Cash));
        $this->assertTrue(SubscriptionPlan::Business->includes(Feature::Cash));
        $this->assertTrue(SubscriptionPlan::Business->includes(Feature::Exports));
        $this->assertFalse(SubscriptionPlan::Business->includes(Feature::Group));

        foreach (Feature::cases() as $feature) {
            $this->assertTrue(SubscriptionPlan::Premium->includes($feature));
        }
    }

    public function test_starter_owner_keeps_the_essentials_but_not_the_reserved_features(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Starter);
        $owner = $this->makeStaff($pressing, PressingRole::Admin);

        $this->assertTrue($owner->may(Permission::OrdersCreate, $pressing));
        $this->assertTrue($owner->may(Permission::PaymentsRecord, $pressing));
        $this->assertTrue($owner->may(Permission::RevenueView, $pressing));
        $this->assertFalse($owner->may(Permission::CashClose, $pressing));
        $this->assertFalse($owner->may(Permission::ReportsExport, $pressing));
        $this->assertFalse($owner->may(Permission::ActivityView, $pressing));
        $this->assertFalse($owner->may(Permission::RolesManage, $pressing));
        $this->assertSame(Feature::Exports, $owner->featureLockedFor(Permission::ReportsExport, $pressing));
    }

    public function test_a_pressing_without_subscription_keeps_every_feature(): void
    {
        $pressing = Pressing::factory()->create();
        $owner = $this->makeStaff($pressing, PressingRole::Admin);

        $this->assertTrue($owner->may(Permission::CashClose, $pressing));
        $this->assertTrue($owner->may(Permission::ReportsExport, $pressing));
    }

    public function test_starter_owner_is_sent_to_the_option_instead_of_a_blank_refusal(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Starter);
        $owner = $this->makeStaff($pressing, PressingRole::Admin);
        $this->actingAs($owner);

        $this->get(route('cash.index'))->assertRedirect(route('subscription.show', ['option' => 'caisse']));
        $this->get(route('activity.index'))->assertRedirect(route('subscription.show', ['option' => 'journal']));
        $this->get(route('orders.export', ['format' => 'csv']))->assertRedirect(route('subscription.show', ['option' => 'exports']));
    }

    public function test_a_manager_of_a_starter_pressing_is_refused_the_cash_page(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Starter);
        $manager = $this->makeStaff($pressing, PressingRole::Employee, Role::MANAGER);

        $this->actingAs($manager)->get(route('cash.index'))->assertForbidden();
    }

    public function test_a_purchased_option_unlocks_the_feature_without_changing_plan(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Starter, ['addons' => [Feature::Exports->value]]);
        $owner = $this->makeStaff($pressing, PressingRole::Admin);

        $this->assertTrue($owner->may(Permission::ReportsExport, $pressing));
        $this->assertFalse($owner->may(Permission::CashClose, $pressing));
        $this->actingAs($owner)->get(route('orders.export', ['format' => 'csv']))->assertOk();
    }

    public function test_the_group_needs_premium_or_the_group_option(): void
    {
        $first = $this->pressingOn(SubscriptionPlan::Business);
        $second = $this->pressingOn(SubscriptionPlan::Business);
        $owner = User::factory()->create();
        $first->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $second->staff()->attach($owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        $this->assertTrue($owner->administersSeveralPressings());
        $this->assertFalse($owner->managesGroup());
        $this->actingAs($owner)->get(route('group.orders'))->assertRedirect(route('subscription.show', ['option' => 'groupe']));

        $first->subscription->update(['plan' => SubscriptionPlan::Premium]);
        $owner->forgetRoles();

        $this->assertTrue($owner->managesGroup());
    }

    public function test_starter_owner_sees_the_custom_roles_offer_on_the_team_page(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Starter);
        $owner = $this->makeStaff($pressing, PressingRole::Admin);

        Livewire::actingAs($owner)->test(TeamIndex::class)
            ->assertSee('Rôles sur mesure')
            ->assertDontSee('+ Nouveau rôle');
    }

    public function test_owner_requests_an_option_and_presslink_activates_it(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Starter);
        $owner = $this->makeStaff($pressing, PressingRole::Admin);

        Livewire::actingAs($owner)->test(SubscriptionShow::class, ['option' => 'exports'])
            ->assertSee('Ajouter cette option')
            ->call('requestOption', 'exports')
            ->assertSee('Demande envoyée')
            // Une seconde demande identique n'est pas dupliquée.
            ->call('requestOption', 'exports');

        $request = SubscriptionRequest::sole();
        $this->assertSame(Feature::Exports, $request->feature);
        $this->assertTrue($request->isPending());

        $admin = User::factory()->create(['is_super_admin' => true]);
        Livewire::actingAs($admin)->test(AdminPressingShow::class, ['pressing' => $pressing])
            ->assertSee($request->label())
            ->call('applyRequest', $request->id)
            ->assertSet('addons', ['exports']);

        $this->assertSame(SubscriptionRequest::DONE, $request->fresh()->status);
        $this->assertTrue($pressing->fresh()->hasFeature(Feature::Exports));
    }

    public function test_extra_orders_unblock_the_month_and_expire_with_it(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Starter, ['orders_limit' => 2, 'orders_used' => 2]);
        $owner = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $subscription = $pressing->subscription;

        $this->assertFalse($subscription->allowsNewOrder());

        Livewire::actingAs($owner)->test(SubscriptionShow::class)
            ->assertSee('Les 2 commandes de ce mois sont utilisées')
            ->assertSee('+ 50 commandes')->assertSee('1 000 F');

        Livewire::actingAs($owner)->test(SubscriptionShow::class)->call('requestOrders', 50);
        SubscriptionRequest::sole()->apply();

        $subscription->refresh();
        $this->assertSame(52, $subscription->ordersLimitThisMonth());
        (new CreateOrderAction)->handle($pressing->fresh(), $customer, [['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1]]);

        // Le mois suivant, les commandes ajoutées ne comptent plus.
        $subscription->update(['extra_orders_month' => now()->subMonthNoOverflow()->format('Y-m')]);
        $this->assertSame(2, $subscription->fresh()->ordersLimitThisMonth());
    }

    public function test_only_offered_order_packs_can_be_requested(): void
    {
        $pressing = $this->pressingOn(SubscriptionPlan::Starter);
        $owner = $this->makeStaff($pressing, PressingRole::Admin);

        Livewire::actingAs($owner)->test(SubscriptionShow::class)
            ->call('requestOrders', 99999)
            ->assertStatus(422);

        $this->assertSame(0, SubscriptionRequest::count());
    }
}
