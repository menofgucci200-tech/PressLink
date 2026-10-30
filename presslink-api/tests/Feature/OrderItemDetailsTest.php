<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PressingRole;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\User;
use App\Support\ColorPalette;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Création d'une commande : on choisit d'abord le service (Repassage,
 * Nettoyage…), puis on touche les pièces ; le prix d'une pièce dépend du
 * service. Cf. Orders\Create.
 */
class OrderItemDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create();
        $user = User::factory()->create();
        $this->pressing->staff()->attach($user, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $this->actingAs($user);
    }

    private function repassage()
    {
        return $this->pressing->prestations()->where('name', 'Repassage')->firstOrFail();
    }

    private function nettoyage()
    {
        return $this->pressing->prestations()->where('name', 'Nettoyage')->firstOrFail();
    }

    private function shirt(): Service
    {
        $shirt = Service::factory()->for($this->pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);
        $shirt->setPrice($this->repassage(), 500);

        return $shirt;
    }

    public function test_a_piece_is_priced_according_to_the_chosen_service(): void
    {
        $shirt = $this->shirt();

        Livewire::test(OrdersCreate::class)
            ->call('selectPrestation', $this->repassage()->id)
            ->call('addPiece', $shirt->id)
            ->assertSet('items.0.unit_price_fcfa', 500)
            ->assertSet('items.0.name', 'Chemise · Repassage')
            ->assertSet('items.0.prestation_name', 'Repassage')
            ->call('selectPrestation', $this->nettoyage()->id)
            ->call('addPiece', $shirt->id)
            ->assertSet('items.1.unit_price_fcfa', 1000)
            ->assertSet('items.1.name', 'Chemise · Nettoyage')
            ->assertCount('items', 2);
    }

    public function test_the_first_service_is_preselected_so_a_piece_can_be_tapped_right_away(): void
    {
        $shirt = $this->shirt();

        Livewire::test(OrdersCreate::class)
            ->assertSet('prestationId', $this->repassage()->id)
            ->call('addPiece', $shirt->id)
            ->assertSet('items.0.name', 'Chemise · Repassage');
    }

    public function test_a_piece_without_a_price_for_the_service_is_refused(): void
    {
        $suit = Service::factory()->for($this->pressing)->create(['name' => 'Costume', 'price_fcfa' => 4000]); // Nettoyage seulement

        Livewire::test(OrdersCreate::class)
            ->call('selectPrestation', $this->repassage()->id)
            ->call('addPiece', $suit->id)
            ->assertSet('items', []);
    }

    public function test_only_pieces_priced_for_the_service_are_listed(): void
    {
        $shirt = $this->shirt();
        Service::factory()->for($this->pressing)->create(['name' => 'Costume', 'price_fcfa' => 4000]);

        // Chaque service a sa grille de pièces (affichée quand il est choisi).
        $names = fn (array $groups, int $prestationId) => collect($groups[$prestationId])->map(fn (array $tile) => $tile['piece']->name)->all();

        Livewire::test(OrdersCreate::class)
            ->assertViewHas('pieceGroups', fn (array $groups) => in_array('Chemise', $names($groups, $this->repassage()->id), true)
                && ! in_array('Costume', $names($groups, $this->repassage()->id), true)
                && in_array('Costume', $names($groups, $this->nettoyage()->id), true));
    }

    public function test_tapping_a_piece_again_increments_its_quantity(): void
    {
        $pants = Service::factory()->for($this->pressing)->create(['name' => 'Pantalon', 'price_fcfa' => 1500]);

        Livewire::test(OrdersCreate::class)
            ->call('selectPrestation', $this->nettoyage()->id)
            ->call('addPiece', $pants->id)
            ->call('addPiece', $pants->id)
            ->assertCount('items', 1)
            ->assertSet('items.0.quantity', 2)
            ->call('decrementPiece', $pants->id)
            ->assertSet('items.0.quantity', 1)
            ->call('decrementPiece', $pants->id)
            ->assertSet('items', []);
    }

    public function test_a_piece_with_variants_asks_which_one_and_uses_its_own_price(): void
    {
        $shirt = $this->shirt();
        $long = ServiceVariant::factory()->for($shirt)->create(['name' => 'Manche longue', 'price_fcfa' => 1500]);
        $shirt->setPrice($this->repassage(), 700, $long);

        Livewire::test(OrdersCreate::class)
            ->call('selectPrestation', $this->repassage()->id)
            ->call('addPiece', $shirt->id)
            ->assertSet('variantPieceId', $shirt->id)
            ->assertSet('items', [])
            ->call('addVariant', $shirt->id, $long->id)
            ->assertSet('variantPieceId', null)
            ->assertSet('items.0.name', 'Chemise · Manche longue · Repassage')
            ->assertSet('items.0.unit_price_fcfa', 700)
            ->assertSet('items.0.service_variant_id', $long->id);
    }

    public function test_a_color_can_be_added_to_a_line(): void
    {
        $shirt = $this->shirt();

        Livewire::test(OrdersCreate::class)
            ->call('selectPrestation', $this->repassage()->id)
            ->call('addPiece', $shirt->id)
            ->call('setItemColor', 0, 'Bleu')
            ->assertSet('items.0.name', 'Chemise · Repassage · Bleu')
            ->assertSet('items.0.color', 'Bleu')
            ->call('setItemColor', 0, '  ')
            ->assertSet('items.0.name', 'Chemise · Repassage')
            ->assertSet('items.0.color', null);
    }

    public function test_a_color_picked_with_the_color_picker_becomes_the_closest_color_name(): void
    {
        $shirt = $this->shirt();

        Livewire::test(OrdersCreate::class)
            ->call('selectPrestation', $this->repassage()->id)
            ->call('addPiece', $shirt->id)
            ->call('setItemColorFromPicker', 0, '#7dd3fc')
            ->assertSet('items.0.color', 'Bleu ciel')
            ->assertSet('items.0.name', 'Chemise · Repassage · Bleu ciel')
            ->call('setItemColorFromPicker', 0, 'pas-une-couleur')
            ->assertSet('items.0.color', 'Bleu ciel');
    }

    public function test_the_color_palette_names_common_colors(): void
    {
        $this->assertSame('Rouge', ColorPalette::nameFor('#dc2626'));
        $this->assertSame('Noir', ColorPalette::nameFor('#000000'));
        $this->assertSame('Blanc', ColorPalette::nameFor('#fafafa'));
        $this->assertSame('Vert', ColorPalette::nameFor('#16a34a'));
        $this->assertNull(ColorPalette::nameFor('rouge'));
        $this->assertSame('#16A34A', ColorPalette::hex('Vert'));
    }

    public function test_a_custom_piece_uses_the_typed_name_and_price_under_the_chosen_service(): void
    {
        Livewire::test(OrdersCreate::class)
            ->call('selectPrestation', $this->nettoyage()->id)
            ->set('customName', 'Nappe brodée')
            ->set('customPrice', 2500)
            ->call('addCustomPiece')
            ->assertSet('items.0.name', 'Nappe brodée · Nettoyage')
            ->assertSet('items.0.unit_price_fcfa', 2500)
            ->assertSet('items.0.service_id', null)
            ->assertSet('items.0.prestation_id', $this->nettoyage()->id);
    }

    public function test_a_custom_piece_needs_a_name_and_a_price(): void
    {
        Livewire::test(OrdersCreate::class)
            ->call('selectPrestation', $this->nettoyage()->id)
            ->call('addCustomPiece')
            ->assertHasErrors(['customName', 'customPrice']);
    }

    public function test_a_disabled_service_cannot_be_chosen(): void
    {
        $repassage = $this->repassage();
        $repassage->update(['is_active' => false]);

        Livewire::test(OrdersCreate::class)->call('selectPrestation', $repassage->id)->assertNotFound();
    }

    public function test_a_service_of_another_pressing_cannot_be_chosen(): void
    {
        $foreign = Pressing::factory()->create()->prestations()->firstOrFail();

        Livewire::test(OrdersCreate::class)->call('selectPrestation', $foreign->id)->assertNotFound();
    }

    public function test_the_created_order_keeps_the_service_of_each_line(): void
    {
        $shirt = $this->shirt();
        $customer = Customer::factory()->create();
        $this->pressing->customers()->attach($customer, ['joined_at' => now()]);

        Livewire::test(OrdersCreate::class)
            ->set('selectedCustomerId', $customer->id)
            ->call('selectPrestation', $this->repassage()->id)
            ->call('addPiece', $shirt->id)
            ->call('addPiece', $shirt->id)
            ->set('paymentChoice', 'later')
            ->call('create')
            ->assertHasNoErrors();

        $item = $this->pressing->orders()->firstOrFail()->items()->firstOrFail();
        $this->assertSame($this->repassage()->id, $item->prestation_id);
        $this->assertSame('Repassage', $item->prestation_name);
        $this->assertSame($shirt->id, $item->service_id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(1000, $item->subtotal_fcfa);
    }

    public function test_the_action_refuses_a_piece_or_service_of_another_pressing(): void
    {
        $customer = Customer::factory()->create();
        $this->pressing->customers()->attach($customer, ['joined_at' => now()]);
        $foreignPiece = Service::factory()->for(Pressing::factory()->create())->create();
        $foreignService = Pressing::factory()->create()->prestations()->firstOrFail();

        foreach ([['service_id' => $foreignPiece->id, 'prestation_id' => null], ['service_id' => null, 'prestation_id' => $foreignService->id]] as $ids) {
            try {
                (new CreateOrderAction)->handle($this->pressing, $customer, [[...$ids, 'name' => 'X', 'unit_price_fcfa' => 100, 'quantity' => 1]]);
                $this->fail('Une pièce ou un service étranger aurait dû être refusé.');
            } catch (InvalidArgumentException) {
                $this->assertSame(0, $this->pressing->orders()->count());
            }
        }
    }

    public function test_the_ticket_composed_in_the_browser_is_checked_and_repriced_on_validation(): void
    {
        $shirt = $this->shirt();
        $customer = Customer::factory()->create();
        $this->pressing->customers()->attach($customer, ['joined_at' => now()]);
        $line = fn (array $overrides) => [
            'service_id' => $shirt->id, 'service_variant_id' => null, 'prestation_id' => $this->repassage()->id, 'prestation_name' => 'Repassage',
            'base_name' => 'Chemise', 'name' => 'Chemise · Repassage', 'color' => null, 'unit_price_fcfa' => 500, 'quantity' => 1, ...$overrides,
        ];

        // Prix modifié dans le navigateur : le prix du catalogue est relu en base.
        Livewire::test(OrdersCreate::class)
            ->set('selectedCustomerId', $customer->id)
            ->set('items', [
                $line(['unit_price_fcfa' => 1, 'quantity' => 2, 'base_name' => 'Nom trafiqué']),
                ['service_id' => null, 'service_variant_id' => null, 'prestation_id' => $this->nettoyage()->id, 'prestation_name' => 'Nettoyage',
                    'base_name' => 'Nappe brodée', 'name' => 'Nappe brodée', 'color' => 'Bleu', 'unit_price_fcfa' => 2500, 'quantity' => 1],
            ])
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('errorMessage', null);

        $order = $this->pressing->orders()->with('items')->sole();
        $this->assertSame(3500, (int) $order->total_fcfa);
        $this->assertSame(['Chemise · Repassage', 'Nappe brodée · Nettoyage · Bleu'], $order->items->pluck('name')->all());
        $this->assertSame([500, 2500], $order->items->pluck('unit_price_fcfa')->map(fn ($p) => (int) $p)->all());

        // Pièce d'un autre pressing ou plus proposée pour ce service : refusée, rien n'est créé.
        $foreign = Service::factory()->create(['name' => 'Ailleurs']);
        Livewire::test(OrdersCreate::class)
            ->set('selectedCustomerId', $customer->id)
            ->set('items', [$line(['service_id' => $foreign->id, 'base_name' => 'Ailleurs'])])
            ->call('create')
            ->assertSet('errorMessage', "« Ailleurs » n'est plus proposé à ce prix : retirez-le du ticket puis ajoutez-le de nouveau.");
        $this->assertSame(1, $this->pressing->orders()->count());
    }
}
