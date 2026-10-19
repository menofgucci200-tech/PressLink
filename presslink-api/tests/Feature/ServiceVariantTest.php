<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Services\Index as ServicesIndex;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Variantes d'une pièce à prix différent — ex. Chemise manche courte vs manche
 * longue — gérées directement dans le tableau des services.
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

    public function test_admin_can_add_a_variant_and_price_it_per_service(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $service = Service::factory()->for($pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);
        $repassage = $pressing->prestations()->where('name', 'Repassage')->firstOrFail();

        $component = Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->call('newVariant', $service->id)
            ->set('variantName', 'Manche longue')
            ->call('saveVariant')
            ->assertHasNoErrors();

        $variant = $service->variants()->where('name', 'Manche longue')->firstOrFail();
        $component->call('setPrice', $service->id, $variant->id, $repassage->id, '700');

        $this->assertSame(700, $service->priceFor($repassage, $variant));
    }

    public function test_a_variant_name_must_be_unique_for_the_piece(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $service = Service::factory()->for($pressing)->create();
        ServiceVariant::factory()->for($service)->create(['name' => 'Manche longue']);

        Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->call('newVariant', $service->id)
            ->set('variantName', 'manche longue')
            ->call('saveVariant')
            ->assertHasErrors('variantName');
    }

    public function test_an_unused_variant_is_deleted_with_its_prices(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $service = Service::factory()->for($pressing)->create();
        $variant = ServiceVariant::factory()->for($service)->create();

        Livewire::actingAs($admin)->test(ServicesIndex::class)->call('deleteVariant', $variant->id);

        $this->assertModelMissing($variant);
        $this->assertDatabaseMissing('service_prices', ['service_variant_id' => $variant->id]);
    }

    public function test_admin_cannot_manage_variants_of_another_pressings_piece(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $foreign = Service::factory()->for($otherPressing)->create();
        $foreignVariant = ServiceVariant::factory()->for($foreign)->create();
        $repassage = $pressing->prestations()->firstOrFail();

        Livewire::actingAs($admin)->test(ServicesIndex::class)->call('newVariant', $foreign->id)->assertNotFound();
        Livewire::actingAs($admin)->test(ServicesIndex::class)->call('deleteVariant', $foreignVariant->id)->assertNotFound();
        Livewire::actingAs($admin)->test(ServicesIndex::class)->call('setPrice', $foreign->id, null, $repassage->id, '100')->assertNotFound();

        $this->assertModelExists($foreignVariant);
    }

    public function test_a_service_of_another_pressing_cannot_be_used_to_price_a_piece(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $piece = Service::factory()->for($pressing)->create();
        $foreignService = $otherPressing->prestations()->firstOrFail();

        Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->call('setPrice', $piece->id, null, $foreignService->id, '100')
            ->assertNotFound();

        $this->assertNull($piece->priceFor($foreignService));
    }
}
