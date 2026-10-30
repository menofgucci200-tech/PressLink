<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Espace pressing sur téléphone et tablette : barre de navigation du bas,
 * nouvelle commande lancée depuis la fiche d'un client (client déjà choisi),
 * gestes rapides sur la fiche client.
 */
class MobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Pressing $pressing;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pressing = Pressing::factory()->create();
        $this->owner = User::factory()->create();
        $this->pressing->staff()->attach($this->owner, ['role' => PressingRole::Admin->value, 'is_active' => true]);
        $this->actingAs($this->owner);
    }

    public function test_the_bottom_bar_gives_one_tap_access_to_the_daily_screens(): void
    {
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('aria-label="Navigation rapide"', false)
            ->assertSee(route('orders.index'), false)
            ->assertSee(route('orders.create'), false)
            ->assertSee(route('clients.index'), false);

        // L'écran de commande a sa propre barre (ticket et bouton Valider).
        $this->get(route('orders.create'))->assertOk()->assertDontSee('aria-label="Navigation rapide"', false);
    }

    public function test_a_new_order_from_a_client_page_starts_with_the_client_chosen(): void
    {
        $customer = Customer::factory()->create();
        $this->pressing->customers()->attach($customer, ['joined_at' => now()]);
        $foreign = Customer::factory()->create();

        $this->get(route('clients.show', $customer))->assertOk()
            ->assertSee(route('orders.create', ['client' => $customer->id]), false)
            ->assertSee('tel:'.$customer->phone, false)
            ->assertSee('https://wa.me/'.ltrim($customer->phone, '+'), false);

        Livewire::withQueryParams(['client' => $customer->id])->test(OrdersCreate::class)
            ->assertSet('selectedCustomerId', $customer->id);

        // Un client d'un autre pressing n'est jamais présélectionné.
        Livewire::withQueryParams(['client' => $foreign->id])->test(OrdersCreate::class)
            ->assertSet('selectedCustomerId', null);
    }
}
