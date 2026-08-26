<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Enums\PressingStatus;
use App\Enums\SubscriptionPlan;
use App\Livewire\Admin\Clients\Index as AdminClientsIndex;
use App\Livewire\Admin\Orders\Index as AdminOrdersIndex;
use App\Livewire\Admin\Pressings\Index as AdminPressingsIndex;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Enrichissements Super Admin au-delà du périmètre strict MVP Phase 7 :
 * filtres sur la liste des pressings, vue Commandes et vue Clients
 * plateforme (support : retrouver une commande/un client sans connaître
 * son pressing d'origine).
 */
class SuperAdminGlobalViewsTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_super_admin_can_filter_pressings_by_status_and_plan(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $activePressing = Pressing::factory()->create(['name' => 'Pressing Actif', 'status' => PressingStatus::Active]);
        $suspendedPressing = Pressing::factory()->create(['name' => 'Pressing Suspendu', 'status' => PressingStatus::Suspended]);
        Subscription::factory()->for($activePressing)->create(['plan' => SubscriptionPlan::Business]);
        Subscription::factory()->for($suspendedPressing)->create(['plan' => SubscriptionPlan::Starter]);

        $this->actingAs($superAdmin);

        Livewire::test(AdminPressingsIndex::class)
            ->set('status', PressingStatus::Suspended->value)
            ->assertSee('Pressing Suspendu')
            ->assertDontSee('Pressing Actif')
            ->set('status', '')
            ->set('plan', SubscriptionPlan::Business->value)
            ->assertSee('Pressing Actif')
            ->assertDontSee('Pressing Suspendu');
    }

    public function test_regular_staff_cannot_access_the_admin_orders_view(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(AdminOrdersIndex::class)->assertStatus(403);
    }

    public function test_super_admin_can_see_orders_across_all_pressings(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $pressingA = Pressing::factory()->create();
        $pressingB = Pressing::factory()->create();
        $customerA = Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Traore']);
        $pressingA->customers()->attach($customerA, ['joined_at' => now()]);
        $customerB = Customer::factory()->create(['first_name' => 'Marc', 'last_name' => 'Koffi']);
        $pressingB->customers()->attach($customerB, ['joined_at' => now()]);

        $orderA = (new CreateOrderAction)->handle($pressingA, $customerA, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);
        (new CreateOrderAction)->handle($pressingB, $customerB, [
            ['service_id' => null, 'name' => 'Pantalon', 'unit_price_fcfa' => 1500, 'quantity' => 1],
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(AdminOrdersIndex::class)
            ->assertSee($orderA->order_number)
            ->assertSee('Awa Traore')
            ->assertSee('Marc Koffi')
            ->set('search', 'Awa')
            ->assertSee('Awa Traore')
            ->assertDontSee('Marc Koffi')
            ->set('search', '')
            ->set('status', OrderStatus::Recuperee->value)
            ->assertDontSee('Awa Traore');
    }

    public function test_regular_staff_cannot_access_the_admin_clients_view(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(AdminClientsIndex::class)->assertStatus(403);
    }

    public function test_super_admin_can_search_clients_across_all_pressings(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create(['first_name' => 'Fatou', 'last_name' => 'Diabate', 'phone' => '+2250701020304']);
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $otherCustomer = Customer::factory()->create(['first_name' => 'Ibrahim', 'last_name' => 'Sanogo']);

        $this->actingAs($superAdmin);

        Livewire::test(AdminClientsIndex::class)
            ->assertSee('Fatou Diabate')
            ->assertSee('Ibrahim Sanogo')
            ->set('search', 'Fatou')
            ->assertSee('Fatou Diabate')
            ->assertDontSee('Ibrahim Sanogo');
    }
}
