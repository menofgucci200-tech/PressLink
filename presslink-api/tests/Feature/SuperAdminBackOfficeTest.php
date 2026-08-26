<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Pressings\Create as AdminPressingsCreate;
use App\Livewire\Admin\Pressings\Index as AdminPressingsIndex;
use App\Livewire\Admin\Pressings\Show as AdminPressingsShow;
use App\Livewire\Auth\Login;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminBackOfficeTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_regular_staff_cannot_access_the_admin_dashboard(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(AdminDashboard::class)->assertStatus(403);
    }

    public function test_regular_staff_cannot_access_the_pressings_list(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(AdminPressingsIndex::class)->assertStatus(403);
    }

    public function test_login_redirects_a_super_admin_to_the_back_office(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $superAdmin->update(['password' => Hash::make('secret123')]);

        Livewire::test(Login::class)
            ->set('login', $superAdmin->email)
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertRedirect('/admin');
    }

    public function test_super_admin_sees_global_kpis(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        Pressing::factory()->create(['status' => PressingStatus::Active]);
        Pressing::factory()->create(['status' => PressingStatus::Suspended]);

        $this->actingAs($superAdmin);

        Livewire::test(AdminDashboard::class)
            ->assertStatus(200)
            ->assertSee('1', false);
    }

    public function test_super_admin_can_list_and_search_pressings(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        Pressing::factory()->create(['name' => 'Pressing Élégance', 'city' => 'Cocody']);
        Pressing::factory()->create(['name' => 'Pressing Marcory']);

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsIndex::class)
            ->assertSee('Pressing Élégance')
            ->assertSee('Pressing Marcory')
            ->set('search', 'Élégance')
            ->assertSee('Pressing Élégance')
            ->assertDontSee('Pressing Marcory');
    }

    public function test_super_admin_can_suspend_and_reactivate_a_pressing(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $pressing = Pressing::factory()->create(['status' => PressingStatus::Active]);

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsIndex::class)->call('toggleStatus', $pressing->id);
        $this->assertSame(PressingStatus::Suspended, $pressing->fresh()->status);

        Livewire::test(AdminPressingsIndex::class)->call('toggleStatus', $pressing->id);
        $this->assertSame(PressingStatus::Active, $pressing->fresh()->status);
    }

    public function test_super_admin_can_create_a_pressing_with_a_trial_subscription_and_no_admin_yet(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsCreate::class)
            ->set('name', 'Pressing Nouveau')
            ->set('phone', '+2250701020304')
            ->call('create')
            ->assertSet('createdPressing', fn ($pressing) => $pressing !== null);

        $pressing = Pressing::where('name', 'Pressing Nouveau')->firstOrFail();

        $this->assertSame(0, $pressing->staff()->count());
        $this->assertNotEmpty($pressing->code);

        $subscription = Subscription::where('pressing_id', $pressing->id)->firstOrFail();
        $this->assertSame(SubscriptionPlan::Starter, $subscription->plan);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame(SubscriptionPlan::Starter->ordersLimit(), $subscription->orders_limit);
    }

    public function test_super_admin_can_set_a_custom_pressing_code_on_creation(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsCreate::class)
            ->set('name', 'Pressing Nouveau')
            ->set('code', 'pn-1234')
            ->set('phone', '+2250701020304')
            ->call('create');

        $pressing = Pressing::where('name', 'Pressing Nouveau')->firstOrFail();
        $this->assertSame('PN-1234', $pressing->code);
    }

    public function test_super_admin_can_view_pressing_detail_and_update_its_subscription(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $pressing = Pressing::factory()->create();
        $this->makeStaff($pressing, PressingRole::Admin);
        Subscription::factory()->for($pressing)->create([
            'plan' => SubscriptionPlan::Starter,
            'orders_limit' => SubscriptionPlan::Starter->ordersLimit(),
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsShow::class, ['pressing' => $pressing])
            ->assertStatus(200)
            ->assertSee($pressing->name)
            ->set('plan', SubscriptionPlan::Business->value)
            ->set('ordersLimit', '')
            ->call('saveSubscription')
            ->assertSet('saved', true);

        $subscription = $pressing->fresh()->subscription;
        $this->assertSame(SubscriptionPlan::Business, $subscription->plan);
        $this->assertNull($subscription->orders_limit);
    }

    public function test_super_admin_can_reset_a_staff_members_password(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $pressing = Pressing::factory()->create();
        $member = $this->makeStaff($pressing, PressingRole::Admin);
        $originalHash = $member->password;

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsShow::class, ['pressing' => $pressing])
            ->call('resetStaffPassword', $member->id)
            ->assertSet('passwordResetForUserId', $member->id)
            ->assertViewHas('staff');

        $member->refresh();
        $this->assertNotSame($originalHash, $member->password);
    }

    public function test_super_admin_cannot_reset_the_password_of_staff_from_another_pressing(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $foreignMember = $this->makeStaff($otherPressing, PressingRole::Admin);

        $this->actingAs($superAdmin);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(AdminPressingsShow::class, ['pressing' => $pressing])
            ->call('resetStaffPassword', $foreignMember->id);
    }
}
