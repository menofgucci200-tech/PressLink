<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Détail des articles ajoutés à une commande : variante (prix propre) et
 * couleur, ou article entièrement personnalisé — cf. Orders\Create.
 */
class OrderItemDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_adding_an_item_with_a_variant_uses_its_own_price_and_a_composed_name(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $service = Service::factory()->for($pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);
        $variant = ServiceVariant::factory()->for($service)->create(['name' => 'Manche longue', 'price_fcfa' => 1500]);

        $this->actingAs($admin);

        Livewire::test(OrdersCreate::class)
            ->set('pickerService', (string) $service->id)
            ->set('pickerVariant', (string) $variant->id)
            ->set('pickerColor', 'Bleu')
            ->call('addPickedItem')
            ->assertSet('items.0.name', 'Chemise · Manche longue · Bleu')
            ->assertSet('items.0.color', 'Bleu')
            ->assertSet('items.0.unit_price_fcfa', 1500)
            ->assertSet('items.0.service_id', $service->id)
            ->assertSet('items.0.service_variant_id', $variant->id);
    }

    public function test_a_service_with_active_variants_requires_picking_one(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $service = Service::factory()->for($pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);
        ServiceVariant::factory()->for($service)->create();

        $this->actingAs($admin);

        Livewire::test(OrdersCreate::class)
            ->set('pickerService', (string) $service->id)
            ->call('addPickedItem')
            ->assertHasErrors('pickerVariant')
            ->assertSet('items', []);
    }

    public function test_adding_a_custom_variant_via_autre_uses_the_typed_name_and_price(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $service = Service::factory()->for($pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);
        ServiceVariant::factory()->for($service)->create(['name' => 'Manche longue']);

        $this->actingAs($admin);

        Livewire::test(OrdersCreate::class)
            ->set('pickerService', (string) $service->id)
            ->set('pickerVariant', 'other')
            ->set('pickerCustomName', 'Sans col')
            ->set('pickerCustomPrice', 1800)
            ->call('addPickedItem')
            ->assertSet('items.0.name', 'Chemise · Sans col')
            ->assertSet('items.0.unit_price_fcfa', 1800)
            ->assertSet('items.0.service_variant_id', null);
    }

    public function test_adding_a_fully_custom_article_not_tied_to_any_service(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(OrdersCreate::class)
            ->set('pickerService', 'other')
            ->set('pickerCustomName', 'Nappe')
            ->set('pickerCustomPrice', 2500)
            ->call('addPickedItem')
            ->assertSet('items.0.name', 'Nappe')
            ->assertSet('items.0.unit_price_fcfa', 2500)
            ->assertSet('items.0.service_id', null);
    }

    public function test_identical_items_are_merged_by_quantity(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $service = Service::factory()->for($pressing)->create(['name' => 'Pantalon', 'price_fcfa' => 1500]);

        $this->actingAs($admin);

        Livewire::test(OrdersCreate::class)
            ->set('pickerService', (string) $service->id)
            ->call('addPickedItem')
            ->set('pickerService', (string) $service->id)
            ->call('addPickedItem')
            ->assertCount('items', 1)
            ->assertSet('items.0.quantity', 2);
    }
}
