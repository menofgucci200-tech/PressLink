<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pressing;
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
