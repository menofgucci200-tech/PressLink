<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adresses de ramassage et de livraison du client (Profil > Mes adresses).
 */
class CustomerAddressApiTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->withHeader('Authorization', 'Bearer '.$this->customer->createToken('test')->plainTextToken);
    }

    /** @return array<string, mixed> */
    private function address(array $overrides = []): array
    {
        return ['label' => 'Maison', 'commune' => 'Cocody', 'neighborhood' => 'Riviera 3', 'landmark' => 'Immeuble bleu derrière la pharmacie', ...$overrides];
    }

    public function test_the_communes_are_listed_by_group(): void
    {
        $this->getJson('/api/v1/communes')
            ->assertOk()
            ->assertJsonPath('0.group', 'Abidjan')
            ->assertJsonFragment(['group' => 'Autres villes'])
            ->assertJsonPath('0.communes.5', 'Cocody');
    }

    public function test_the_first_address_becomes_the_default_one(): void
    {
        $this->postJson('/api/v1/customer/addresses', $this->address())
            ->assertCreated()
            ->assertJsonCount(1)
            ->assertJsonPath('0.is_default', true)
            ->assertJsonPath('0.commune', 'Cocody')
            ->assertJsonMissingPath('0.customer_id');

        $this->postJson('/api/v1/customer/addresses', $this->address(['label' => 'Bureau', 'commune' => 'Plateau']))
            ->assertCreated()
            ->assertJsonPath('0.label', 'Maison')
            ->assertJsonPath('1.is_default', false);
    }

    public function test_choosing_another_default_address_moves_the_flag(): void
    {
        $home = CustomerAddress::factory()->for($this->customer)->create(['is_default' => true]);
        $office = CustomerAddress::factory()->for($this->customer)->create(['label' => 'Bureau']);

        $this->putJson("/api/v1/customer/addresses/{$office->id}", $this->address(['label' => 'Bureau', 'is_default' => true]))
            ->assertOk()
            ->assertJsonPath('0.id', $office->id)
            ->assertJsonPath('0.is_default', true);

        $this->assertFalse($home->fresh()->is_default);
    }

    public function test_deleting_the_default_address_hands_the_flag_to_the_next_one(): void
    {
        $home = CustomerAddress::factory()->for($this->customer)->create(['is_default' => true]);
        $office = CustomerAddress::factory()->for($this->customer)->create();

        $this->deleteJson("/api/v1/customer/addresses/{$home->id}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $office->id)
            ->assertJsonPath('0.is_default', true);
    }

    public function test_an_address_needs_a_listed_commune_and_a_landmark(): void
    {
        $this->postJson('/api/v1/customer/addresses', $this->address(['commune' => 'Atlantis', 'landmark' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['commune', 'landmark']);

        $this->postJson('/api/v1/customer/addresses', $this->address(['latitude' => 5.35]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_a_customer_keeps_at_most_five_addresses(): void
    {
        CustomerAddress::factory()->for($this->customer)->count(CustomerAddress::MAX_PER_CUSTOMER)->create();

        $this->postJson('/api/v1/customer/addresses', $this->address())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Vous avez déjà 5 adresses. Supprimez-en une pour en ajouter une autre.');
    }

    public function test_a_customer_cannot_touch_someone_elses_address(): void
    {
        $other = CustomerAddress::factory()->create();

        $this->putJson("/api/v1/customer/addresses/{$other->id}", $this->address())->assertNotFound();
        $this->deleteJson("/api/v1/customer/addresses/{$other->id}")->assertNotFound();
        $this->getJson('/api/v1/customer/addresses')->assertOk()->assertJsonCount(0);
        $this->assertNotNull($other->fresh());
    }
}
