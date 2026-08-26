<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PressingRole;
use App\Livewire\Dashboard;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dashboard mutualisé — un propriétaire rattaché à plusieurs pressings
 * atterrit sur une vue d'ensemble consolidée et peut basculer sur le
 * dashboard détaillé d'une pressing précise (cf. sélecteur sidebar).
 */
class MultiPressingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function attachAsAdmin(User $user, Pressing $pressing): void
    {
        $pressing->staff()->attach($user, ['role' => PressingRole::Admin->value, 'is_active' => true]);
    }

    public function test_single_pressing_staff_lands_directly_on_their_pressing_dashboard(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = User::factory()->create();
        $this->attachAsAdmin($admin, $pressing);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertSee($pressing->name)
            ->assertDontSee("Vue d'ensemble", false);
    }

    public function test_multi_pressing_owner_lands_on_the_consolidated_overview(): void
    {
        $owner = User::factory()->create();
        $pressingA = Pressing::factory()->create(['name' => 'Pressing Nord']);
        $pressingB = Pressing::factory()->create(['name' => 'Pressing Sud']);
        $this->attachAsAdmin($owner, $pressingA);
        $this->attachAsAdmin($owner, $pressingB);

        Livewire::actingAs($owner)
            ->test(Dashboard::class)
            ->assertSee("Vue d'ensemble", false)
            ->assertSee('Pressing Nord')
            ->assertSee('Pressing Sud');
    }

    public function test_overview_aggregates_todays_order_counts_across_all_owned_pressings(): void
    {
        $owner = User::factory()->create();
        $pressingA = Pressing::factory()->create();
        $pressingB = Pressing::factory()->create();
        $this->attachAsAdmin($owner, $pressingA);
        $this->attachAsAdmin($owner, $pressingB);

        $customerA = Customer::factory()->create();
        $pressingA->customers()->attach($customerA, ['joined_at' => now()]);
        $customerB = Customer::factory()->create();
        $pressingB->customers()->attach($customerB, ['joined_at' => now()]);

        (new CreateOrderAction)->handle($pressingA, $customerA, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);
        (new CreateOrderAction)->handle($pressingB, $customerB, [
            ['service_id' => null, 'name' => 'Pantalon', 'unit_price_fcfa' => 1500, 'quantity' => 1],
        ]);

        Livewire::actingAs($owner)
            ->test(Dashboard::class)
            ->assertViewHas('totals', fn ($totals) => $totals['today_count'] === 2 && $totals['open_count'] === 2);
    }

    public function test_owner_can_switch_to_a_specific_pressing_dashboard_via_the_route(): void
    {
        $owner = User::factory()->create();
        $pressingA = Pressing::factory()->create(['name' => 'Pressing Nord']);
        $pressingB = Pressing::factory()->create(['name' => 'Pressing Sud']);
        $this->attachAsAdmin($owner, $pressingA);
        $this->attachAsAdmin($owner, $pressingB);

        $this->actingAs($owner)
            ->get(route('pressings.switch', $pressingB))
            ->assertRedirect(route('dashboard'));

        Livewire::actingAs($owner)
            ->test(Dashboard::class)
            ->assertSee('Pressing Sud')
            ->assertDontSee("Vue d'ensemble", false);
    }

    public function test_owner_cannot_switch_to_a_pressing_they_do_not_belong_to(): void
    {
        $owner = User::factory()->create();
        $pressingA = Pressing::factory()->create();
        $foreignPressing = Pressing::factory()->create();
        $this->attachAsAdmin($owner, $pressingA);

        $this->actingAs($owner)
            ->get(route('pressings.switch', $foreignPressing))
            ->assertForbidden();
    }

    public function test_returning_to_overview_clears_the_selected_pressing(): void
    {
        $owner = User::factory()->create();
        $pressingA = Pressing::factory()->create();
        $pressingB = Pressing::factory()->create();
        $this->attachAsAdmin($owner, $pressingA);
        $this->attachAsAdmin($owner, $pressingB);

        $this->actingAs($owner)->get(route('pressings.switch', $pressingA));
        $this->actingAs($owner)->get(route('pressings.overview'))->assertRedirect(route('dashboard'));

        Livewire::actingAs($owner)
            ->test(Dashboard::class)
            ->assertSee("Vue d'ensemble", false);
    }
}
