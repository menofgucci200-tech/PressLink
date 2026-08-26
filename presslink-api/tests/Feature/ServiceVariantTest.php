<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Services\Variants;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Déclinaisons d'un service à prix différent — ex. Chemise manche courte
 * vs manche longue (cf. Orders\Create pour leur utilisation).
 */
class ServiceVariantTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_employee_cannot_access_the_variants_page(): void
    {
        $pressing = Pressing::factory()->create();
        $employee = $this->makeStaff($pressing, PressingRole::Employee);
        $service = Service::factory()->for($pressing)->create();

        $this->actingAs($employee);

        Livewire::test(Variants::class, ['service' => $service])->assertStatus(403);
    }

    public function test_admin_can_create_a_variant_with_its_own_price(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $service = Service::factory()->for($pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);

        $this->actingAs($admin);

        Livewire::test(Variants::class, ['service' => $service])
            ->set('name', 'Manche longue')
            ->set('priceFcfa', 1500)
            ->call('createVariant');

        $this->assertDatabaseHas('service_variants', [
            'service_id' => $service->id,
            'name' => 'Manche longue',
            'price_fcfa' => 1500,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_toggle_a_variant_active_status(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $service = Service::factory()->for($pressing)->create();
        $variant = ServiceVariant::factory()->for($service)->create(['is_active' => true]);

        $this->actingAs($admin);

        Livewire::test(Variants::class, ['service' => $service])->call('toggleActive', $variant->id);

        $this->assertFalse($variant->fresh()->is_active);
    }

    public function test_admin_cannot_manage_variants_of_another_pressings_service(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $otherService = Service::factory()->for($otherPressing)->create();

        $this->actingAs($admin);

        Livewire::test(Variants::class, ['service' => $otherService])->assertStatus(403);
    }
}
