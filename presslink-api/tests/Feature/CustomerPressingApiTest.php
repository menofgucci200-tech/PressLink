<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pressing;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPressingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_join_a_pressing_by_code(): void
    {
        $pressing = Pressing::factory()->create(['code' => 'PE-4821']);
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/pressings/join', ['code' => 'pe-4821'])
            ->assertOk()
            ->assertJson(['id' => $pressing->id]);

        $this->assertTrue($customer->pressings()->where('pressings.id', $pressing->id)->exists());
    }

    public function test_the_code_is_found_without_hyphen_spaces_or_capitals(): void
    {
        $pressing = Pressing::factory()->create(['code' => 'ZN-8533']);
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        foreach (['ZN8533', 'zn 8533', ' Zn-8533 '] as $saisie) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson('/api/v1/pressings/join', ['code' => $saisie])
                ->assertOk()
                ->assertJson(['id' => $pressing->id]);
        }

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/pressings/join', ['code' => 'ZN8534'])
            ->assertNotFound();
    }

    public function test_joining_with_an_unknown_code_returns_not_found(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/pressings/join', ['code' => 'XX-0000'])
            ->assertNotFound();
    }

    public function test_customer_can_list_their_joined_pressings(): void
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $customer->pressings()->attach($pressing, ['joined_at' => now()]);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/pressings/mine')
            ->assertOk()
            ->assertJsonFragment(['id' => $pressing->id]);
    }

    public function test_pricing_grid_is_hidden_when_pressing_disables_it(): void
    {
        $pressing = Pressing::factory()->create(['show_pricing_to_customers' => false]);
        Service::factory()->create(['pressing_id' => $pressing->id, 'is_active' => true]);
        $customer = Customer::factory()->create();
        $customer->pressings()->attach($pressing, ['joined_at' => now()]);
        $token = $customer->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/pressings/mine')
            ->assertOk();

        $this->assertArrayNotHasKey('prestations', $response->json()[0]);
        $this->assertArrayNotHasKey('services', $response->json()[0]);
    }

    public function test_pricing_grid_is_visible_when_pressing_enables_it(): void
    {
        $pressing = Pressing::factory()->create(['show_pricing_to_customers' => true]);
        $service = Service::factory()->create(['pressing_id' => $pressing->id, 'is_active' => true, 'name' => 'Chemise']);
        $customer = Customer::factory()->create();
        $customer->pressings()->attach($pressing, ['joined_at' => now()]);
        $token = $customer->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/pressings/mine')
            ->assertOk();

        $prestations = $response->json()[0]['prestations'];
        $this->assertSame(['Nettoyage'], array_column($prestations, 'name'), 'Seuls les services où une pièce a un prix sont listés.');
        $this->assertSame($service->id, $prestations[0]['pieces'][0]['id']);
        $this->assertSame('chemise', $prestations[0]['pieces'][0]['icon']);
        $this->assertSame($service->price_fcfa, $prestations[0]['pieces'][0]['price_fcfa']);
    }

    public function test_pricing_grid_lists_each_service_with_its_own_prices(): void
    {
        $pressing = Pressing::factory()->create(['show_pricing_to_customers' => true]);
        $shirt = Service::factory()->create(['pressing_id' => $pressing->id, 'name' => 'Chemise', 'price_fcfa' => 1000]);
        $shirt->setPrice($pressing->prestations()->where('name', 'Repassage')->first(), 500);
        $long = $shirt->variants()->create(['name' => 'Manche longue', 'price_fcfa' => 1200, 'is_active' => true]);
        $shirt->setPrice($pressing->prestations()->where('name', 'Repassage')->first(), 700, $long);
        $customer = Customer::factory()->create();
        $customer->pressings()->attach($pressing, ['joined_at' => now()]);
        $token = $customer->createToken('test')->plainTextToken;

        $prestations = collect($this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/pressings/mine')->assertOk()->json()[0]['prestations'])->keyBy('name');

        $this->assertSame(['Repassage', 'Nettoyage'], $prestations->keys()->all());
        $iron = $prestations['Repassage']['pieces'][0];
        $this->assertNull($iron['price_fcfa'], 'Avec des variantes, le prix est celui de chaque variante.');
        $this->assertSame(700, $iron['variants'][0]['price_fcfa']);
        $this->assertSame(1000, $prestations['Nettoyage']['pieces'][0]['price_fcfa']);
    }

    public function test_customer_can_leave_a_pressing(): void
    {
        $pressing = Pressing::factory()->create();
        $customer = Customer::factory()->create();
        $customer->pressings()->attach($pressing, ['joined_at' => now()]);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/pressings/{$pressing->id}/leave")
            ->assertOk();

        $this->assertFalse($customer->pressings()->where('pressings.id', $pressing->id)->exists());
    }
}
