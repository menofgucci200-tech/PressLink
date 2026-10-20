<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Livewire\CommandPalette;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardPowerToolsTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create();
        $this->staff = User::factory()->create();
        $this->pressing->staff()->attach($this->staff, ['role' => PressingRole::Admin->value, 'is_active' => true]);
    }

    private function order(Pressing $pressing, int $total = 5000, int $paid = 0, string $lastName = 'Koné'): Order
    {
        $customer = Customer::factory()->create(['last_name' => $lastName]);
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        return (new CreateOrderAction)->handle(
            $pressing,
            $customer,
            [['service_id' => null, 'service_variant_id' => null, 'name' => 'Chemise', 'color' => null, 'unit_price_fcfa' => $total, 'quantity' => 1]],
            amountPaidFcfa: $paid,
        );
    }

    public function test_the_palette_finds_orders_and_clients_of_the_current_pressing_only(): void
    {
        $mine = $this->order($this->pressing, lastName: 'Traoré');
        $other = $this->order(Pressing::factory()->create(), lastName: 'Traoré');
        $this->actingAs($this->staff);

        Livewire::test(CommandPalette::class)
            ->set('query', 'Traoré')
            ->assertSee($mine->order_number)
            ->assertDontSee($other->order_number)
            ->assertSee(route('clients.show', $mine->customer), false);
    }

    public function test_the_palette_lists_actions_and_filters_them_without_accents(): void
    {
        $this->actingAs($this->staff);

        Livewire::test(CommandPalette::class)
            ->assertSee('Nouvelle commande')
            ->assertSee('Profil du pressing')
            ->set('query', 'parametres')
            ->assertSee('Horaires, conditions, magasinage')
            ->assertDontSee('Nouvelle commande');
    }

    public function test_employees_do_not_get_admin_actions_in_the_palette(): void
    {
        $employee = User::factory()->create();
        $this->pressing->staff()->attach($employee, ['role' => PressingRole::Employee->value, 'is_active' => true]);
        $this->actingAs($employee);

        Livewire::test(CommandPalette::class)
            ->assertSee('Nouvelle commande')
            ->assertDontSee('Paramètres du pressing')
            ->assertDontSee('Tarifs');
    }

    public function test_the_admin_palette_needs_a_super_admin(): void
    {
        $this->order($this->pressing);
        $this->actingAs($this->staff);

        Livewire::test(CommandPalette::class, ['admin' => true])
            ->assertSet('admin', false)
            ->assertDontSee('Vue globale');

        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($superAdmin);

        Livewire::test(CommandPalette::class, ['admin' => true])
            ->set('query', $this->pressing->name)
            ->assertSee(route('admin.pressings.show', $this->pressing), false);
    }

    public function test_bulk_status_change_skips_orders_that_cannot_move_or_still_owe_money(): void
    {
        $paid = $this->order($this->pressing, 5000, 5000);
        $owing = $this->order($this->pressing, 5000, 1000);
        $justReceived = $this->order($this->pressing);
        foreach ([$paid, $owing] as $order) {
            $order->update(['status' => OrderStatus::Traitement]);
            $order->update(['status' => OrderStatus::Prete]);
        }
        $this->actingAs($this->staff);

        Livewire::test(OrdersIndex::class)
            ->set('selected', [(string) $paid->id, (string) $owing->id, (string) $justReceived->id])
            ->call('bulkTransition', OrderStatus::Recuperee->value)
            ->assertSet('selected', [])
            ->assertDispatched('toast');

        $this->assertSame(OrderStatus::Recuperee, $paid->fresh()->status);
        $this->assertSame(OrderStatus::Prete, $owing->fresh()->status);
        $this->assertSame(OrderStatus::Recue, $justReceived->fresh()->status);
    }

    public function test_bulk_actions_ignore_orders_of_another_pressing(): void
    {
        $foreign = $this->order(Pressing::factory()->create());
        $this->actingAs($this->staff);

        Livewire::test(OrdersIndex::class)
            ->set('selected', [(string) $foreign->id])
            ->call('bulkTransition', OrderStatus::Traitement->value);

        $this->assertSame(OrderStatus::Recue, $foreign->fresh()->status);
    }

    public function test_selected_orders_print_as_one_receipt_per_page_from_the_current_pressing_only(): void
    {
        $first = $this->order($this->pressing);
        $second = $this->order($this->pressing);
        $foreign = $this->order(Pressing::factory()->create());
        $this->actingAs($this->staff);

        $this->get(route('orders.receipts', ['ids' => implode(',', [$first->id, $second->id, $foreign->id])]))
            ->assertOk()
            ->assertSee($first->order_number)
            ->assertSee($second->order_number)
            ->assertDontSee($foreign->order_number)
            ->assertSee('Imprimer les 2 reçus');

        $this->get(route('orders.receipts', ['ids' => (string) $foreign->id]))->assertNotFound();
    }

    public function test_empty_lists_explain_what_to_do(): void
    {
        $this->actingAs($this->staff);

        Livewire::test(OrdersIndex::class)
            ->assertSee('Créer la première commande')
            ->set('search', 'introuvable')
            ->assertSee('Aucune commande ne correspond à ces filtres')
            ->assertSee('Réinitialiser les filtres');
    }

    public function test_the_dashboard_layout_offers_the_palette_and_keyboard_shortcuts(): void
    {
        $this->actingAs($this->staff);

        $this->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Aller au contenu')
            ->assertSee('data-palette', false)
            ->assertSee('id="shortcut-map"', false)
            ->assertSee('Raccourcis clavier');
    }
}
