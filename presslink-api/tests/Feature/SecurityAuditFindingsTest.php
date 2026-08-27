<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrderAction;
use App\Enums\PressingRole;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Livewire\Orders\Show as OrdersShow;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Audit ciblé demandé : "qu'est-ce qui pourrait casser, fuir des données,
 * ou empêcher un vrai pressing d'utiliser PressLink ?"
 *
 * Deux bugs réels trouvés et corrigés ici :
 * 1. IDOR sur la création de commande — selectedCustomerId (propriété
 *    Livewire publique, donc manipulable côté client) n'était jamais
 *    vérifié comme appartenant au pressing courant.
 * 2. Crash 500 sur une transition de statut invalide — OrderStatus::from()
 *    lève un \ValueError, non intercepté par un catch(RuntimeException).
 */
class SecurityAuditFindingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_staff_cannot_create_an_order_for_a_customer_from_another_pressing_via_tampered_property(): void
    {
        $pressing = Pressing::factory()->create();
        $otherPressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $service = Service::factory()->for($pressing)->create(['price_fcfa' => 1000]);

        // Un client qui n'a JAMAIS rejoint $pressing — jamais suggéré par
        // la recherche de l'étape 1, mais on force la propriété comme le
        // ferait une requête Livewire forgée.
        $foreignCustomer = Customer::factory()->create();
        $otherPressing->customers()->attach($foreignCustomer, ['joined_at' => now()]);

        Livewire::actingAs($admin)
            ->test(OrdersCreate::class)
            ->set('selectedCustomerId', $foreignCustomer->id)
            ->set('pickerService', (string) $service->id)
            ->set('pickerQuantity', 1)
            ->call('addPickedItem')
            ->call('create')
            ->assertForbidden();

        $this->assertSame(0, $pressing->orders()->count());
    }

    public function test_staff_can_still_create_an_order_for_a_client_of_their_own_pressing(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $service = Service::factory()->for($pressing)->create(['price_fcfa' => 1000]);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        Livewire::actingAs($admin)
            ->test(OrdersCreate::class)
            ->set('selectedCustomerId', $customer->id)
            ->set('pickerService', (string) $service->id)
            ->set('pickerQuantity', 1)
            ->call('addPickedItem')
            ->call('create');

        $this->assertSame(1, $pressing->orders()->count());
    }

    public function test_transitioning_to_an_invalid_status_shows_an_error_instead_of_crashing(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create();
        $pressing->customers()->attach($customer, ['joined_at' => now()]);

        $order = (new CreateOrderAction)->handle($pressing, $customer, [
            ['service_id' => null, 'name' => 'Chemise', 'unit_price_fcfa' => 1000, 'quantity' => 1],
        ]);

        Livewire::actingAs($admin)
            ->test(OrdersShow::class, ['order' => $order])
            ->call('transitionTo', 'ce-statut-nexiste-pas')
            ->assertSet('errorMessage', 'Statut inconnu.')
            ->assertStatus(200);

        $this->assertSame('recue', $order->fresh()->status->value);
    }
}
