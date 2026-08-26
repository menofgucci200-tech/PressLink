<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_update_their_profile(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/customer/profile', [
                'first_name' => 'Nouveau',
                'last_name' => 'Nom',
                'gender' => 'femme',
                'email' => 'nouveau@example.com',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'first_name' => 'Nouveau',
            'email' => 'nouveau@example.com',
            'gender' => 'femme',
        ]);
    }

    public function test_email_must_be_unique_across_customers(): void
    {
        Customer::factory()->create(['email' => 'taken@example.com']);
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/customer/profile', [
                'first_name' => 'X',
                'last_name' => 'Y',
                'gender' => 'homme',
                'email' => 'taken@example.com',
            ])->assertUnprocessable();
    }

    public function test_customer_can_change_their_password(): void
    {
        $customer = Customer::factory()->create(['password' => Hash::make('oldpass')]);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/customer/password', [
                'current_password' => 'oldpass',
                'password' => 'newpass',
                'password_confirmation' => 'newpass',
            ])->assertOk();

        $this->assertTrue(Hash::check('newpass', $customer->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $customer = Customer::factory()->create(['password' => Hash::make('oldpass')]);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/customer/password', [
                'current_password' => 'wrong',
                'password' => 'newpass',
                'password_confirmation' => 'newpass',
            ])->assertUnprocessable();
    }
}
