<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PressingRole;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Orders\Index as OrdersIndex;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersListLiveTest extends TestCase
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

    private function order(array $attributes = [], ?Customer $customer = null): Order
    {
        $customer ??= Customer::factory()->create();
        $this->pressing->customers()->syncWithoutDetaching([$customer->id => ['joined_at' => now()]]);

        return Order::factory()->for($this->pressing)->for($customer)->create($attributes);
    }

    public function test_orders_can_be_sorted_by_total_in_both_directions(): void
    {
        $small = $this->order(['total_fcfa' => 1000]);
        $big = $this->order(['total_fcfa' => 9000]);

        Livewire::test(OrdersIndex::class)
            ->call('sortBy', 'total')
            ->assertSet('sort', 'total')
            ->assertSet('direction', 'desc')
            ->assertSeeInOrder([$big->order_number, $small->order_number])
            ->call('sortBy', 'total')
            ->assertSet('direction', 'asc')
            ->assertSeeInOrder([$small->order_number, $big->order_number]);
    }

    public function test_unknown_sort_columns_are_ignored(): void
    {
        Livewire::test(OrdersIndex::class)
            ->call('sortBy', 'password')
            ->assertSet('sort', 'recent');
    }

    public function test_orders_created_meanwhile_are_announced_instead_of_shifting_the_list(): void
    {
        $existing = $this->order();
        $component = Livewire::test(OrdersIndex::class);

        $newOne = $this->order();

        $component->call('checkForNewOrders')
            ->assertSet('newOrdersCount', 1)
            ->assertSee('1 nouvelle commande')
            ->assertDontSee($newOne->order_number)
            ->assertSee($existing->order_number)
            ->call('showNewOrders')
            ->assertSet('newOrdersCount', 0)
            ->assertSee($newOne->order_number);
    }

    public function test_the_preview_panel_shows_the_order_and_changes_its_status(): void
    {
        $order = $this->order(['status' => OrderStatus::Recue]);

        Livewire::test(OrdersIndex::class)
            ->call('preview', $order->id)
            ->assertSee('Aperçu de la commande '.$order->order_number)
            ->assertSee('Ouvrir la fiche')
            ->call('previewTransition', OrderStatus::Traitement->value);

        $this->assertSame(OrderStatus::Traitement, $order->fresh()->status);
    }

    public function test_the_preview_panel_never_shows_an_order_of_another_pressing(): void
    {
        $foreign = Order::factory()->create(['status' => OrderStatus::Recue]);

        Livewire::test(OrdersIndex::class)
            ->call('preview', $foreign->id)
            ->assertDontSee($foreign->order_number)
            ->call('previewTransition', OrderStatus::Traitement->value)
            ->assertNotFound();

        $this->assertSame(OrderStatus::Recue, $foreign->fresh()->status);
    }

    public function test_clients_can_be_sorted_by_number_of_orders(): void
    {
        $loyal = Customer::factory()->create(['last_name' => 'Kouassi']);
        $newcomer = Customer::factory()->create(['last_name' => 'Bamba']);
        $this->order([], $loyal);
        $this->order([], $loyal);
        $this->order([], $newcomer);

        Livewire::test(ClientsIndex::class)
            ->call('sortBy', 'commandes')
            ->assertSeeInOrder(['Kouassi', 'Bamba'])
            ->call('sortBy', 'commandes')
            ->assertSeeInOrder(['Bamba', 'Kouassi']);
    }
}
