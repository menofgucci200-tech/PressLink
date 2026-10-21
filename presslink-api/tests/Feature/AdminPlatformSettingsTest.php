<?php

namespace Tests\Feature;

use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\PressingRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Livewire\Admin\Plans\Index as AdminPlans;
use App\Livewire\Admin\Roles\Index as AdminRoles;
use App\Livewire\Admin\Services\Index as AdminServices;
use App\Livewire\Subscription\Show as SubscriptionShow;
use App\Models\Pressing;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Super Admin : formules (prix, commandes par mois, fonctions incluses),
 * essai gratuit, options, rôles rattachés à un pressing et services
 * rattachés aux pressings choisis.
 */
class AdminPlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function owner(Pressing $pressing): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return $user;
    }

    public function test_only_the_super_admin_reaches_the_platform_pages(): void
    {
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->owner($pressing));

        Livewire::test(AdminPlans::class)->assertForbidden();
        Livewire::test(AdminRoles::class)->assertForbidden();
        Livewire::test(AdminServices::class)->assertForbidden();
    }

    public function test_super_admin_sets_prices_orders_per_month_and_features_of_each_plan(): void
    {
        $following = Subscription::factory()->active(SubscriptionPlan::Starter)->create();
        $custom = Subscription::factory()->active(SubscriptionPlan::Starter)->create(['orders_limit' => 42]);

        Livewire::actingAs($this->superAdmin())->test(AdminPlans::class)
            ->set('plans.starter.monthly', '15000')
            ->set('plans.starter.annual', '150000')
            ->set('plans.starter.orders_limit', '2000')
            ->set('plans.starter.features', ['exports'])
            ->set('plans.business.features', ['caisse', 'roles', 'journal'])
            ->set('trialDays', '30')
            ->set('optionPrices.caisse', '5000')
            ->set('orderPacks', [['count' => '300', 'price' => '3000']])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(15_000, SubscriptionPlan::Starter->monthlyPriceFcfa());
        $this->assertSame(150_000, SubscriptionPlan::Starter->annualPriceFcfa());
        $this->assertSame(2_000, SubscriptionPlan::Starter->ordersLimit());
        $this->assertTrue(SubscriptionPlan::Starter->includes(Feature::Exports));
        $this->assertFalse(SubscriptionPlan::Business->includes(Feature::Exports));
        $this->assertSame(SubscriptionPlan::Starter, Feature::Exports->includedFrom());
        $this->assertSame(30, Subscription::trialDays());
        $this->assertSame(5_000, Feature::Cash->priceFcfa());

        // Les pressings qui suivaient le nombre de leur formule passent au nouveau.
        $this->assertSame(2_000, $following->fresh()->orders_limit);
        $this->assertSame(42, $custom->fresh()->orders_limit);
    }

    public function test_option_and_order_pack_prices_show_on_the_subscription_page(): void
    {
        Setting::write('option_prices', ['exports' => 7500]);
        Setting::write('order_packs', [['count' => 300, 'price' => 3000]]);
        $pressing = Pressing::factory()->create();
        Subscription::factory()->for($pressing)->active(SubscriptionPlan::Starter)->create();

        Livewire::actingAs($this->owner($pressing))->test(SubscriptionShow::class)
            ->assertSee('7 500 F / mois')
            ->assertSee('+ 300 commandes')
            ->assertSee('3 000 F')
            ->call('requestOrders', 300)
            ->assertHasNoErrors();
    }

    public function test_every_feature_is_open_during_the_free_trial_and_closes_after(): void
    {
        $pressing = Pressing::factory()->create();
        $owner = $this->owner($pressing);
        $subscription = Subscription::factory()->for($pressing)->create([
            'plan' => SubscriptionPlan::Starter,
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(5),
        ]);

        $this->assertTrue($owner->may(Permission::ReportsExport, $pressing));
        $this->assertTrue($owner->may(Permission::CashClose, $pressing));

        $subscription->update(['trial_ends_at' => now()->subDay()]);
        $owner->forgetRoles();

        $this->assertFalse($owner->may(Permission::ReportsExport, $pressing->fresh()));
    }

    public function test_super_admin_creates_a_role_for_one_pressing_and_assigns_it(): void
    {
        $pressing = Pressing::factory()->create();
        $other = Pressing::factory()->create();
        $owner = $this->owner($pressing);
        $employee = User::factory()->create();
        $pressing->staff()->attach($employee, ['role' => PressingRole::Employee->value, 'is_active' => true]);

        $component = Livewire::actingAs($this->superAdmin())->test(AdminRoles::class, ['pressingId' => (string) $pressing->id])
            ->call('newRole')
            ->set('roleName', 'Caissière')
            ->set('rolePermissions', [Permission::OrdersCreate->value, Permission::PaymentsRecord->value, Permission::CashClose->value])
            ->call('save')
            ->assertHasNoErrors();

        $role = $pressing->roles()->where('name', 'Caissière')->sole();
        $this->assertSame([Permission::OrdersCreate->value, Permission::PaymentsRecord->value, Permission::CashClose->value], $role->permissions);
        $this->assertFalse($other->roles()->where('name', 'Caissière')->exists());

        $component->call('assignRole', $employee->id, $role->id);
        $this->assertSame($role->id, $pressing->staff()->whereKey($employee->id)->first()->pivot->role_id);

        // Le dernier propriétaire ne peut pas perdre son rôle.
        $component->call('assignRole', $owner->id, $role->id)
            ->assertSet('errorMessage', 'Le pressing doit garder au moins un propriétaire actif.');
    }

    public function test_super_admin_can_add_a_role_to_every_pressing(): void
    {
        $pressings = Pressing::factory()->count(3)->create();

        Livewire::actingAs($this->superAdmin())->test(AdminRoles::class, ['pressingId' => (string) $pressings->first()->id])
            ->call('newRole')
            ->set('roleName', 'Repasseur')
            ->set('rolePermissions', [Permission::OrdersWorkshop->value])
            ->set('applyToAll', true)
            ->call('save')
            ->assertHasNoErrors();

        foreach ($pressings as $pressing) {
            $this->assertTrue($pressing->roles()->where('name', 'Repasseur')->exists());
        }
    }

    public function test_the_owner_role_cannot_be_edited(): void
    {
        $pressing = Pressing::factory()->create();
        $ownerRole = $pressing->defaultRole(Role::OWNER);

        Livewire::actingAs($this->superAdmin())->test(AdminRoles::class, ['pressingId' => (string) $pressing->id])
            ->call('editRole', $ownerRole->id)
            ->assertForbidden();
    }

    public function test_super_admin_attaches_a_new_service_to_chosen_pressings(): void
    {
        $chosen = Pressing::factory()->create();
        $alsoChosen = Pressing::factory()->create();
        $left = Pressing::factory()->create();

        Livewire::actingAs($this->superAdmin())->test(AdminServices::class)
            ->call('newService')
            ->set('name', 'Teinture')
            ->set('target', 'some')
            ->set('pressingIds', [(string) $chosen->id, (string) $alsoChosen->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($chosen->prestations()->where('name', 'Teinture')->where('icon', 'teinture')->exists());
        $this->assertTrue($alsoChosen->prestations()->where('name', 'Teinture')->exists());
        $this->assertFalse($left->prestations()->where('name', 'Teinture')->exists());

        // Puis à tous : pas de doublon là où il existe déjà.
        Livewire::actingAs($this->superAdmin())->test(AdminServices::class)
            ->call('attachExisting', 'Teinture')
            ->set('target', 'all')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $chosen->prestations()->where('name', 'Teinture')->count());
        $this->assertTrue($left->prestations()->where('name', 'Teinture')->exists());
    }

    public function test_choosing_pressings_is_required_when_not_attaching_to_all(): void
    {
        Livewire::actingAs($this->superAdmin())->test(AdminServices::class)
            ->call('newService')
            ->set('name', 'Express')
            ->call('save')
            ->assertHasErrors(['pressingIds']);
    }
}
