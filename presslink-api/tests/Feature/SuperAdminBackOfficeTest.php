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

    public function test_super_admin_can_create_a_pressing_with_its_first_admin_and_trial_subscription(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsCreate::class)
            ->set('name', 'Pressing Nouveau')
            ->set('phone', '+2250701020304')
            ->set('adminName', 'Fatou Diabate')
            ->set('adminEmail', 'fatou@pressing-nouveau.test')
            ->set('adminPhone', '+2250701020305')
            ->call('create')
            ->assertSet('generatedPassword', fn ($password) => is_string($password) && strlen($password) > 0);

        $pressing = Pressing::where('name', 'Pressing Nouveau')->firstOrFail();
        $admin = User::where('email', 'fatou@pressing-nouveau.test')->firstOrFail();

        $this->assertSame(
            PressingRole::Admin,
            $pressing->staff()->where('users.id', $admin->id)->first()->pivot->role,
        );

        $subscription = Subscription::where('pressing_id', $pressing->id)->firstOrFail();
        $this->assertSame(SubscriptionPlan::Starter, $subscription->plan);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame(SubscriptionPlan::Starter->ordersLimit(), $subscription->orders_limit);
    }

    public function test_the_admin_created_for_a_new_pressing_can_log_in(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        $component = Livewire::test(AdminPressingsCreate::class)
            ->set('name', 'Pressing Nouveau')
            ->set('phone', '+2250701020304')
            ->set('adminName', 'Fatou Diabate')
            ->set('adminEmail', 'fatou@pressing-nouveau.test')
            ->set('adminPhone', '+2250701020305')
            ->call('create');

        $password = $component->get('generatedPassword');

        $this->post('/logout');

        Livewire::test(Login::class)
            ->set('login', 'fatou@pressing-nouveau.test')
            ->set('password', $password)
            ->call('authenticate');

        $this->assertAuthenticatedAs(User::where('email', 'fatou@pressing-nouveau.test')->firstOrFail());
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
}
