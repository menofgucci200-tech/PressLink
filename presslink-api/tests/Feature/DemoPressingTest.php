<?php

namespace Tests\Feature;

use App\Actions\Demo\ResetDemoPressing;
use App\Enums\OrderStatus;
use App\Livewire\Admin\Dashboard;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Environnement de démo en production : pressing fictif complet,
 * (ré)initialisable depuis la console super admin.
 */
class DemoPressingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_creates_a_complete_demo_pressing(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        Livewire::test(Dashboard::class)
            ->call('resetDemo')
            ->assertSet('demoAccess.code', ResetDemoPressing::PRESSING_CODE)
            ->assertSee('demo.gerant');

        $pressing = Pressing::where('code', ResetDemoPressing::PRESSING_CODE)->firstOrFail();
        $this->assertSame(2, $pressing->staff()->count());
        $this->assertSame(4, $pressing->customers()->count());
        $this->assertSame(5, $pressing->services()->count());
        $statuses = $pressing->orders()->pluck('status')->map(fn ($s) => $s->value)->unique()->sort()->values()->all();
        $this->assertSame(['prete', 'recue', 'recuperee', 'traitement'], $statuses);
        $this->assertTrue($pressing->subscription->allowsNewOrder());
    }

    public function test_demo_accounts_can_log_in_with_the_generated_password(): void
    {
        $password = (new ResetDemoPressing)->handle()['password'];

        $this->assertTrue(Auth::validate(['login' => ResetDemoPressing::MANAGER_LOGIN, 'password' => $password]));
        $this->postJson('/api/v1/auth/customer/login', ['phone' => '+2250000000001', 'password' => $password])->assertOk();
    }

    public function test_reset_replaces_the_previous_demo_without_duplicates(): void
    {
        $first = (new ResetDemoPressing)->handle();
        $second = (new ResetDemoPressing)->handle();

        $this->assertNotSame($first['password'], $second['password']);
        $this->assertSame(1, Pressing::where('code', ResetDemoPressing::PRESSING_CODE)->count());
        $this->assertSame(4, Customer::where('phone', 'like', '+22500000000%')->count());
        $this->assertSame(1, User::where('login', ResetDemoPressing::MANAGER_LOGIN)->count());
        $this->assertSame(5, $second['pressing']->orders()->count());
        $this->assertSame(OrderStatus::Prete->value, $second['pressing']->orders()->oldest('id')->first()->status->value);
    }

    public function test_only_super_admins_can_reset_the_demo(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)->assertForbidden();
    }

    public function test_reset_never_touches_other_pressings(): void
    {
        $other = Pressing::factory()->create();

        (new ResetDemoPressing)->handle();
        (new ResetDemoPressing)->handle();

        $this->assertModelExists($other);
    }

    public function test_demo_pressing_is_excluded_from_console_statistics(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $real = Pressing::factory()->create(['name' => 'Pressing Réel']);
        (new ResetDemoPressing)->handle();

        Livewire::test(Dashboard::class)
            ->assertViewHas('activePressings', 1)
            ->assertViewHas('totalClients', 0)
            ->assertViewHas('totalOrders', 0)
            ->assertViewHas('recentPressings', fn ($pressings) => $pressings->pluck('code')->all() === [$real->code]);
    }
}
