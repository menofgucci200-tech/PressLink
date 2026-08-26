<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PressingRole;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtres de la vue globale Super Admin (période, ville, administrateur) —
 * au-delà du périmètre strict MVP Phase 7.
 */
class SuperAdminDashboardFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_filtering_by_city_scopes_recent_pressings_and_counts(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        Pressing::factory()->create(['name' => 'Pressing Cocody', 'city' => 'Cocody']);
        Pressing::factory()->create(['name' => 'Pressing Marcory', 'city' => 'Marcory']);

        $this->actingAs($superAdmin);

        Livewire::test(AdminDashboard::class)
            ->assertSee('Pressing Cocody')
            ->assertSee('Pressing Marcory')
            ->set('city', 'Cocody')
            ->assertSee('Pressing Cocody')
            ->assertDontSee('Pressing Marcory');
    }

    public function test_filtering_by_administrator_scopes_pressings_to_their_own(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $adminA = User::factory()->create(['name' => 'Fatou Diabate']);
        $adminB = User::factory()->create(['name' => 'Ibrahim Kone']);
        $pressingA = Pressing::factory()->create(['name' => 'Pressing de Fatou']);
        $pressingB = Pressing::factory()->create(['name' => 'Pressing de Ibrahim']);
        $pressingA->staff()->attach($adminA, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $pressingB->staff()->attach($adminB, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        $this->actingAs($superAdmin);

        Livewire::test(AdminDashboard::class)
            ->set('administratorId', (string) $adminA->id)
            ->assertSee('Pressing de Fatou')
            ->assertDontSee('Pressing de Ibrahim');
    }

    public function test_filtering_by_period_scopes_the_orders_count(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);
        $order->forceFill(['created_at' => now()->subDays(45)])->save();

        $this->actingAs($superAdmin);

        Livewire::test(AdminDashboard::class)
            ->set('period', '30')
            ->assertViewHas('totalOrders', 0)
            ->set('period', 'all')
            ->assertViewHas('totalOrders', 1);
    }
}
