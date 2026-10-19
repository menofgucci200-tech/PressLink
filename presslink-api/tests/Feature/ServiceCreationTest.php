<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Services\Index as ServicesIndex;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pressing;
use App\Models\Prestation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module « Services » : les services d'un pressing (Repassage, Nettoyage…),
 * les pièces (Chemise, Robe…) et leur prix selon le service.
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

    public function test_every_new_pressing_starts_with_repassage_and_nettoyage(): void
    {
        $pressing = Pressing::factory()->create();

        $this->assertSame(['Repassage', 'Nettoyage'], $pressing->prestations()->pluck('name')->all());
        $this->assertSame(['repassage', 'nettoyage'], $pressing->prestations()->pluck('icon')->all());
    }

    public function test_admin_can_create_a_service_with_an_icon(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->call('newPrestation')
            ->set('prestationName', 'Retouches')
            ->assertSet('prestationIcon', 'retouche')
            ->call('choosePrestationIcon', 'detachage')
            ->call('savePrestation')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('prestations', ['pressing_id' => $pressing->id, 'name' => 'Retouches', 'icon' => 'detachage']);
    }

    public function test_admin_can_rename_and_change_the_icon_of_a_service(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $repassage = $pressing->prestations()->where('name', 'Repassage')->firstOrFail();

        Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->call('editPrestation', $repassage->id)
            ->set('prestationName', 'Repassage express')
            ->call('choosePrestationIcon', 'express')
            ->call('savePrestation')
            ->assertHasNoErrors();

        $this->assertSame('Repassage express', $repassage->fresh()->name);
        $this->assertSame('express', $repassage->fresh()->icon);
    }

    public function test_two_services_cannot_share_a_name(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->call('newPrestation')
            ->set('prestationName', 'nettoyage')
            ->call('savePrestation')
            ->assertHasErrors('prestationName');

        $this->assertSame(2, $pressing->prestations()->count());
    }

    public function test_an_unused_service_is_deleted_but_a_used_one_is_only_disabled(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $unused = Prestation::factory()->for($pressing)->create(['name' => 'Teinture']);
        $used = $pressing->prestations()->where('name', 'Repassage')->firstOrFail();
        $order = Order::factory()->for($pressing)->create();
        OrderItem::factory()->for($order)->create(['prestation_id' => $used->id, 'prestation_name' => 'Repassage']);

        Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->call('deletePrestation', $unused->id)
            ->call('deletePrestation', $used->id);

        $this->assertModelMissing($unused);
        $this->assertTrue($used->fresh()->exists);
        $this->assertFalse($used->fresh()->is_active);
    }

    public function test_deleting_a_service_removes_its_prices(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $extra = Prestation::factory()->for($pressing)->create(['name' => 'Express']);
        $shirt = Service::factory()->for($pressing)->create(['name' => 'Chemise']);
        $shirt->setPrice($extra, 2000);

        Livewire::actingAs($admin)->test(ServicesIndex::class)->call('deletePrestation', $extra->id);

        $this->assertDatabaseMissing('service_prices', ['prestation_id' => $extra->id]);
    }

    public function test_admin_creates_a_piece_with_its_price_per_service_and_an_icon(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        [$repassage, $nettoyage] = $pressing->prestations()->get()->all();

        Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->call('newPiece')
            ->set('pieceName', 'Chemise blanche')
            ->assertSet('pieceIcon', 'chemise')
            ->set("newPiecePrices.{$repassage->id}", '500')
            ->set("newPiecePrices.{$nettoyage->id}", '1000')
            ->call('savePiece')
            ->assertHasNoErrors();

        $piece = $pressing->services()->where('name', 'Chemise blanche')->firstOrFail();
        $this->assertSame('chemise', $piece->icon);
        $this->assertSame(500, $piece->priceFor($repassage));
        $this->assertSame(1000, $piece->priceFor($nettoyage));
    }

    public function test_an_empty_price_means_the_piece_is_not_offered_for_that_service(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        [$repassage, $nettoyage] = $pressing->prestations()->get()->all();

        Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->call('newPiece')
            ->set('pieceName', 'Costume')
            ->set("newPiecePrices.{$nettoyage->id}", '4000')
            ->call('savePiece');

        $piece = $pressing->services()->where('name', 'Costume')->firstOrFail();
        $this->assertNull($piece->priceFor($repassage));
        $this->assertSame(4000, $piece->priceFor($nettoyage));
    }

    public function test_a_price_can_be_edited_and_cleared_in_the_table(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $repassage = $pressing->prestations()->where('name', 'Repassage')->firstOrFail();
        $piece = Service::factory()->for($pressing)->create(['name' => 'Pantalon', 'price_fcfa' => 1500]);

        Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->call('setPrice', $piece->id, null, $repassage->id, '700')
            ->call('setPrice', $piece->id, null, $repassage->id, '750');
        $this->assertSame(750, $piece->priceFor($repassage));
        $this->assertSame(1, $piece->prices()->where('prestation_id', $repassage->id)->count());

        Livewire::actingAs($admin)->test(ServicesIndex::class)->call('setPrice', $piece->id, null, $repassage->id, '');
        $this->assertNull($piece->priceFor($repassage));
    }

    public function test_an_invalid_price_is_refused(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $repassage = $pressing->prestations()->where('name', 'Repassage')->firstOrFail();
        $piece = Service::factory()->for($pressing)->create();

        Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->call('setPrice', $piece->id, null, $repassage->id, 'abc')
            ->call('setPrice', $piece->id, null, $repassage->id, '-5');

        $this->assertNull($piece->priceFor($repassage));
    }

    public function test_the_old_tarifs_address_redirects_to_services(): void
    {
        $pressing = Pressing::factory()->create();

        $this->actingAs($this->makeStaff($pressing, PressingRole::Admin))
            ->get('/tarifs')->assertRedirect('/services')->assertStatus(301);
    }

    public function test_employee_cannot_access_the_services_page(): void
    {
        $pressing = Pressing::factory()->create();
        $employee = $this->makeStaff($pressing, PressingRole::Employee);

        $this->actingAs($employee);

        Livewire::test(ServicesIndex::class)->assertStatus(403);
    }

    public function test_the_page_renders_icons_for_services_and_pieces(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        Service::factory()->for($pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);

        Livewire::actingAs($admin)->test(ServicesIndex::class)
            ->assertSee('Repassage')
            ->assertSee('Nettoyage')
            ->assertSee('Chemise')
            ->assertSeeHtml('viewBox="0 0 64 64"');
    }
}
