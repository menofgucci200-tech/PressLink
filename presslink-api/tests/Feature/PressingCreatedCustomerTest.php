<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Clients\Show as ClientsShow;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un client enregistré par un pressing (au comptoir) n'a pas encore de mot
 * de passe : son compte l'attend. En s'inscrivant dans l'appli avec son
 * numéro, il confirme les informations du pressing (ou les corrige),
 * choisit son mot de passe et retrouve toutes ses commandes.
 */
class PressingCreatedCustomerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Pressing $pressing): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => PressingRole::Admin->value, 'is_active' => true]);

        return $user;
    }

    /** @return array<string, string> */
    private function signUp(array $overrides = []): array
    {
        return [
            'phone' => '0701020304',
            'first_name' => 'Awa',
            'last_name' => 'Konan',
            'gender' => 'femme',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
            ...$overrides,
        ];
    }

    public function test_a_client_created_by_a_pressing_has_no_password_yet(): void
    {
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->admin($pressing));

        Livewire::test(ClientsIndex::class)
            ->set('firstName', 'Aya')
            ->set('lastName', 'Bamba')
            ->set('phone', '07 01 02 03 04')
            ->call('createClient')
            ->assertDontSee('123456');

        Livewire::test(OrdersCreate::class)
            ->set('newFirstName', 'Yao')
            ->set('newLastName', 'Kouamé')
            ->set('newPhone', '07 01 02 03 05')
            ->call('createAndPickCustomer');

        foreach (['+2250701020304', '+2250701020305'] as $phone) {
            $customer = Customer::where('phone', $phone)->firstOrFail();
            $this->assertNull($customer->password);
            $this->assertFalse($customer->hasAppAccount());
        }
    }

    public function test_the_app_offers_to_confirm_the_details_the_pressing_entered(): void
    {
        $customer = Customer::factory()->create(['phone' => '+2250701020304', 'first_name' => 'Mme', 'last_name' => 'Konan', 'gender' => null, 'password' => null]);
        Order::factory()->count(2)->for($customer)->create();

        $this->postJson('/api/v1/auth/customer/check-phone', ['phone' => '07 01 02 03 04'])
            ->assertOk()
            ->assertJson([
                'exists' => false,
                'pending' => true,
                'prefill' => ['first_name' => 'Mme', 'last_name' => 'Konan', 'gender' => null],
                'orders_count' => 2,
            ]);
    }

    public function test_signing_up_claims_the_account_with_the_details_the_client_confirms(): void
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create(['phone' => '+2250701020304', 'first_name' => 'Mme', 'last_name' => 'Konan', 'password' => null]);
        $pressing->customers()->attach($customer, ['joined_at' => now()]);
        $order = Order::factory()->for($customer)->for($pressing)->create();

        $token = $this->postJson('/api/v1/auth/customer/register', $this->signUp())
            ->assertCreated()
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('customer.first_name', 'Awa')
            ->json('token');

        $customer->refresh();
        $this->assertSame('Awa', $customer->first_name);
        $this->assertTrue(Hash::check('secret1', $customer->password));
        $this->assertSame(1, Customer::count());

        $this->getJson('/api/v1/orders', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.0.order_number', $order->order_number);

        // Le compte réclamé se comporte ensuite comme n'importe quel compte.
        $this->postJson('/api/v1/auth/customer/check-phone', ['phone' => '0701020304'])->assertJson(['exists' => true, 'pending' => false]);
        $this->postJson('/api/v1/auth/customer/register', $this->signUp())->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_adding_a_client_who_already_uses_the_app_keeps_their_password(): void
    {
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->admin($pressing));
        $customer = Customer::factory()->create(['phone' => '+2250701020306', 'password' => 'a-real-chosen-password']);

        Livewire::test(ClientsIndex::class)
            ->set('firstName', $customer->first_name)
            ->set('lastName', $customer->last_name)
            ->set('phone', '+2250701020306')
            ->call('createClient')
            ->call('confirmKnownCustomer');

        $this->assertTrue(Hash::check('a-real-chosen-password', $customer->fresh()->password));
    }

    public function test_a_number_already_known_shows_its_name_and_asks_for_confirmation(): void
    {
        $other = Pressing::factory()->create();
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->admin($pressing));
        $known = Customer::factory()->create(['phone' => '+2250701020307', 'first_name' => 'Mme', 'last_name' => 'Koné', 'password' => null]);
        $other->customers()->attach($known, ['joined_at' => now()]);

        // Wizard de commande : le nom connu est montré, rien n'est rattaché avant confirmation.
        Livewire::test(OrdersCreate::class)
            ->set('showNewClientForm', true)
            ->set('newFirstName', 'Awa')
            ->set('newLastName', 'Koné')
            ->set('newPhone', '07 01 02 03 07')
            ->call('createAndPickCustomer')
            ->assertSet('knownCustomerId', $known->id)
            ->assertSee('Ce numéro est déjà enregistré au nom de Mme Koné.')
            ->assertSet('selectedCustomerId', null)
            ->call('dismissKnownCustomer')
            ->assertSet('knownCustomerId', null)
            ->call('createAndPickCustomer')
            ->call('confirmKnownCustomer')
            ->assertSet('selectedCustomerId', $known->id);

        // Le nom saisi par le second pressing ne remplace pas celui déjà connu.
        $known->refresh();
        $this->assertSame('Mme', $known->first_name);
        $this->assertTrue($pressing->customers()->whereKey($known->id)->exists());
        $this->assertSame(1, Customer::where('phone', '+2250701020307')->count());
    }

    public function test_the_clients_page_also_asks_before_adding_a_known_number(): void
    {
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->admin($pressing));
        $known = Customer::factory()->create(['phone' => '+2250701020308', 'first_name' => 'Mme', 'last_name' => 'Koné']);

        $component = Livewire::test(ClientsIndex::class)
            ->set('showCreateForm', true)
            ->set('phone', '0701020308')
            ->call('createClient')
            ->assertHasNoErrors()
            ->assertSee('Ce numéro est déjà enregistré au nom de Mme Koné.');
        $this->assertFalse($pressing->customers()->whereKey($known->id)->exists());

        $component->call('confirmKnownCustomer')->assertDispatched('toast');
        $this->assertTrue($pressing->customers()->whereKey($known->id)->exists());

        // Déjà dans le fichier : on le dit, sans doublon.
        Livewire::test(ClientsIndex::class)
            ->set('phone', '0701020308')
            ->call('createClient')
            ->assertSet('errorMessage', 'Mme Koné fait déjà partie de vos clients.');
    }

    public function test_the_order_wizard_picks_a_number_already_in_the_pressing_file(): void
    {
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->admin($pressing));
        $known = Customer::factory()->create(['phone' => '+2250701020309']);
        $pressing->customers()->attach($known, ['joined_at' => now()]);

        Livewire::test(OrdersCreate::class)
            ->set('newPhone', '0701020309')
            ->call('createAndPickCustomer')
            ->assertSet('knownCustomerId', null)
            ->assertSet('selectedCustomerId', $known->id);
    }

    public function test_the_client_page_tells_whether_the_client_uses_the_app(): void
    {
        $pressing = Pressing::factory()->create();
        $this->actingAs($this->admin($pressing));
        $waiting = Customer::factory()->create(['password' => null]);
        $pressing->customers()->attach($waiting, ['joined_at' => now()]);

        Livewire::test(ClientsShow::class, ['customer' => $waiting])->assertSee('Pas encore sur l\'application', false);
    }
}
