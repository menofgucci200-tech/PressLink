<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PressingRole;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\Prestation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Comptoir : la commande se crée sur un seul écran, en quelques touchers
 * (client → pièce → Valider), y compris pour 50 pièces (pinceau de couleur,
 * quantité tapée) et pour un client qui redépose la même chose.
 */
class OrderCounterTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private Customer $customer;

    private Service $shirt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create();
        $user = User::factory()->create();
        $this->pressing->staff()->attach($user, ['role' => PressingRole::Employee->value, 'is_active' => true]);
        $this->actingAs($user);

        $this->customer = Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Traoré', 'phone' => '+2250708124400']);
        $this->pressing->customers()->attach($this->customer, ['joined_at' => now()]);

        $this->shirt = Service::factory()->for($this->pressing)->create(['name' => 'Chemise', 'price_fcfa' => 1000]);
        $this->shirt->setPrice($this->repassage(), 500);
    }

    private function repassage(): Prestation
    {
        return $this->pressing->prestations()->where('name', 'Repassage')->firstOrFail();
    }

    private function nettoyage(): Prestation
    {
        return $this->pressing->prestations()->where('name', 'Nettoyage')->firstOrFail();
    }

    public function test_an_order_is_created_in_three_taps(): void
    {
        Livewire::test(OrdersCreate::class)
            ->call('pickCustomer', $this->customer->id)
            ->call('addPiece', $this->shirt->id)
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('lastCreated.customer', 'Awa Traoré')
            ->assertSet('items', [])
            ->assertSet('selectedCustomerId', null);

        $order = Order::firstOrFail();
        $this->assertSame(500, $order->total_fcfa);
        $this->assertSame(0, $order->amount_paid_fcfa);
        $this->assertSame(now()->addDays(2)->toDateString(), $order->expected_at->toDateString());
    }

    public function test_validate_guides_to_what_is_missing_instead_of_failing(): void
    {
        Livewire::test(OrdersCreate::class)
            ->call('create')
            ->assertDispatched('comptoir-focus', target: 'client')
            ->call('pickCustomer', $this->customer->id)
            ->call('create')
            ->assertDispatched('comptoir-focus', target: 'pieces');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_color_brush_applies_to_the_next_pieces(): void
    {
        Livewire::test(OrdersCreate::class)
            ->call('setActiveColor', 'Blanc')
            ->call('addPiece', $this->shirt->id)
            ->call('addPiece', $this->shirt->id)
            ->call('setActiveColor', 'Noir')
            ->call('addPiece', $this->shirt->id)
            ->assertCount('items', 2)
            ->assertSet('items.0.name', 'Chemise · Repassage · Blanc')
            ->assertSet('items.0.quantity', 2)
            ->assertSet('items.1.name', 'Chemise · Repassage · Noir')
            ->call('setActiveColor', 'Noir')
            ->assertSet('activeColor', null);
    }

    public function test_a_quantity_can_be_typed_at_once(): void
    {
        Livewire::test(OrdersCreate::class)
            ->call('setActiveColor', 'Blanc')
            ->call('setPieceQuantity', $this->shirt->id, 20)
            ->assertSet('items.0.quantity', 20)
            ->assertSet('items.0.color', 'Blanc')
            ->call('setPieceQuantity', $this->shirt->id, 12)
            ->assertSet('items.0.quantity', 12)
            ->call('setItemQuantity', 0, 5000)
            ->assertSet('items.0.quantity', OrdersCreate::MAX_QUANTITY)
            ->call('setItemQuantity', 0, 0)
            ->assertSet('items', []);
    }

    public function test_lines_that_become_identical_are_merged(): void
    {
        Livewire::test(OrdersCreate::class)
            ->call('setActiveColor', 'Blanc')
            ->call('addPiece', $this->shirt->id)
            ->call('setActiveColor', '')
            ->call('addPiece', $this->shirt->id)
            ->assertCount('items', 2)
            ->call('setItemColor', 1, 'Blanc')
            ->assertCount('items', 1)
            ->assertSet('items.0.quantity', 2);
    }

    public function test_the_last_order_of_the_customer_can_be_repeated_at_current_prices(): void
    {
        (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => $this->shirt->id, 'prestation_id' => $this->repassage()->id, 'prestation_name' => 'Repassage', 'name' => 'Chemise · Repassage · Blanc', 'color' => 'Blanc', 'unit_price_fcfa' => 400, 'quantity' => 6],
            ['service_id' => null, 'prestation_id' => $this->nettoyage()->id, 'prestation_name' => 'Nettoyage', 'name' => 'Nappe brodée · Nettoyage', 'color' => null, 'unit_price_fcfa' => 2500, 'quantity' => 1],
        ]);

        Livewire::test(OrdersCreate::class)
            ->call('pickCustomer', $this->customer->id)
            ->call('repeatLastOrder')
            ->assertCount('items', 2)
            ->assertSet('items.0.base_name', 'Chemise')
            ->assertSet('items.0.color', 'Blanc')
            ->assertSet('items.0.quantity', 6)
            ->assertSet('items.0.unit_price_fcfa', 500)
            ->assertSet('items.1.base_name', 'Nappe brodée')
            ->assertSet('items.1.unit_price_fcfa', 2500);
    }

    public function test_the_most_used_service_is_preselected(): void
    {
        $this->shirt->setPrice($this->nettoyage(), 1000);
        (new CreateOrderAction)->handle($this->pressing, $this->customer, [
            ['service_id' => $this->shirt->id, 'prestation_id' => $this->nettoyage()->id, 'prestation_name' => 'Nettoyage', 'name' => 'Chemise · Nettoyage', 'unit_price_fcfa' => 1000, 'quantity' => 3],
        ]);

        Livewire::test(OrdersCreate::class)->assertSet('prestationId', $this->nettoyage()->id);
    }

    public function test_enter_in_the_search_picks_the_first_customer_found(): void
    {
        Livewire::test(OrdersCreate::class)
            ->set('clientSearch', '07 08 12')
            ->call('pickFirstCustomer')
            ->assertSet('selectedCustomerId', $this->customer->id);

        Livewire::test(OrdersCreate::class)
            ->set('clientSearch', 'awa tra')
            ->call('pickFirstCustomer')
            ->assertSet('selectedCustomerId', $this->customer->id);
    }

    public function test_an_unknown_number_opens_the_new_customer_form_prefilled(): void
    {
        Livewire::test(OrdersCreate::class)
            ->set('clientSearch', '0102030405')
            ->call('pickFirstCustomer')
            ->assertSet('showNewClientForm', true)
            ->assertSet('newPhone', '+2250102030405');
    }

    public function test_the_order_in_progress_is_kept_as_a_draft(): void
    {
        Livewire::test(OrdersCreate::class)
            ->call('pickCustomer', $this->customer->id)
            ->call('addPiece', $this->shirt->id);

        Livewire::test(OrdersCreate::class)
            ->assertSet('draftRestored', true)
            ->assertSet('selectedCustomerId', $this->customer->id)
            ->assertCount('items', 1)
            ->call('clearDraft')
            ->assertSet('items', []);

        Livewire::test(OrdersCreate::class)->assertSet('draftRestored', false)->assertSet('items', []);
    }

    public function test_the_counter_screen_renders(): void
    {
        $this->get(route('orders.create'))
            ->assertOk()
            ->assertSee('Nouvelle commande')
            ->assertSee('Choisir le client')
            ->assertSee('Chemise');
    }
}
