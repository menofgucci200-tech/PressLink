<?php

namespace Tests\Feature;

use App\Enums\PressingRole;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Orders\Create as OrdersCreate;
use App\Models\Customer;
use App\Models\Pressing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un client créé directement par le staff (walk-in, sans passer par
 * l'inscription dans l'app) reçoit un mot de passe par défaut connu, pour
 * pouvoir se connecter puis le changer lui-même dans son profil.
 */
class CustomerDefaultPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function makeStaff(Pressing $pressing, PressingRole $role): User
    {
        $user = User::factory()->create();
        $pressing->staff()->attach($user, ['role' => $role->value, 'is_active' => true]);

        return $user;
    }

    public function test_a_client_created_from_the_clients_page_gets_the_default_password(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(ClientsIndex::class)
            ->set('firstName', 'Aya')
            ->set('lastName', 'Bamba')
            ->set('phone', '+2250701020304')
            ->call('createClient');

        $customer = Customer::where('phone', '+2250701020304')->firstOrFail();
        $this->assertTrue(Hash::check(Customer::DEFAULT_WALK_IN_PASSWORD, $customer->password));

        $this->postJson('/api/v1/auth/customer/login', [
            'phone' => '+2250701020304',
            'password' => Customer::DEFAULT_WALK_IN_PASSWORD,
        ])->assertOk();
    }

    public function test_a_client_created_from_the_order_wizard_gets_the_default_password(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);

        $this->actingAs($admin);

        Livewire::test(OrdersCreate::class)
            ->set('newFirstName', 'Aya')
            ->set('newLastName', 'Bamba')
            ->set('newPhone', '+2250701020305')
            ->call('createAndPickCustomer');

        $customer = Customer::where('phone', '+2250701020305')->firstOrFail();
        $this->assertTrue(Hash::check(Customer::DEFAULT_WALK_IN_PASSWORD, $customer->password));
    }

    public function test_adding_an_existing_client_does_not_overwrite_their_real_password(): void
    {
        $pressing = Pressing::factory()->create();
        $admin = $this->makeStaff($pressing, PressingRole::Admin);
        $customer = Customer::factory()->create([
            'phone' => '+2250701020306',
            'password' => 'a-real-chosen-password',
        ]);

        $this->actingAs($admin);

        Livewire::test(ClientsIndex::class)
            ->set('firstName', $customer->first_name)
            ->set('lastName', $customer->last_name)
            ->set('phone', '+2250701020306')
            ->call('createClient');

        $this->assertTrue(Hash::check('a-real-chosen-password', $customer->fresh()->password));
    }
}
