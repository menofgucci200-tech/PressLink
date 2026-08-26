<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Services\Index as ServicesIndex;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Création d'un service — Cahier §17. Un admin peut créer ses variantes
 * (prix propre) en même temps que le service, pour éviter l'aller-retour
 * par la page Variantes.
 */
class ServiceCreationTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_admin_can_create_a_service_with_variants_in_one_step(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        Livewire::actingAs($admin)
            ->test(ServicesIndex::class)
            ->set('name', 'Chemise')
            ->set('priceFcfa', '1000')
            ->call('addVariantRow')
            ->call('addVariantRow')
            ->set('variantRows.0.name', 'Manche courte')
            ->set('variantRows.0.priceFcfa', '900')
            ->set('variantRows.1.name', 'Manche longue')
            ->set('variantRows.1.priceFcfa', '1200')
            ->call('createService')
            ->assertHasNoErrors();

        $service = $pressing->services()->where('name', 'Chemise')->firstOrFail();
        $this->assertSame(1000, $service->price_fcfa);
        $this->assertCount(2, $service->variants);
        $this->assertSame(900, $service->variants()->where('name', 'Manche courte')->value('price_fcfa'));
        $this->assertSame(1200, $service->variants()->where('name', 'Manche longue')->value('price_fcfa'));
    }

    public function test_empty_variant_rows_are_ignored(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        Livewire::actingAs($admin)
            ->test(ServicesIndex::class)
            ->set('name', 'Pantalon')
            ->set('priceFcfa', '1500')
            ->call('addVariantRow')
            ->call('createService')
            ->assertHasNoErrors();

        $service = $pressing->services()->where('name', 'Pantalon')->firstOrFail();
        $this->assertCount(0, $service->variants);
    }

    public function test_removing_a_variant_row_before_submit_excludes_it(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        Livewire::actingAs($admin)
            ->test(ServicesIndex::class)
            ->set('name', 'Robe')
            ->set('priceFcfa', '3000')
            ->call('addVariantRow')
            ->call('addVariantRow')
            ->set('variantRows.0.name', 'Courte')
            ->set('variantRows.0.priceFcfa', '2500')
            ->set('variantRows.1.name', 'Longue')
            ->set('variantRows.1.priceFcfa', '3500')
            ->call('removeVariantRow', 0)
            ->call('createService')
            ->assertHasNoErrors();

        $service = $pressing->services()->where('name', 'Robe')->firstOrFail();
        $this->assertCount(1, $service->variants);
        $this->assertSame('Longue', $service->variants->first()->name);
    }

    public function test_employee_cannot_access_service_creation(): void
    {
        $pressing = Pressing::factory()->create();
        $employee = $this->makeStaff($pressing, PressingRole::Employee);

        $this->actingAs($employee);

        Livewire::test(ServicesIndex::class)->assertStatus(403);
    }
}
