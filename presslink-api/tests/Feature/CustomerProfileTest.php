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

    public function test_customer_can_change_their_phone_number_and_sign_in_with_it(): void
    {
        $customer = Customer::factory()->create(['phone' => '+2250700000001', 'password' => Hash::make('secret1')]);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/customer/phone', [
                'phone' => '+2250708124400',
                'current_password' => 'secret1',
            ])
            ->assertOk()
            ->assertJsonPath('phone', '+2250708124400');

        $this->assertSame('+2250708124400', $customer->fresh()->phone);

        $this->postJson('/api/v1/auth/customer/login', ['phone' => '+2250708124400', 'password' => 'secret1'])->assertOk();
        $this->postJson('/api/v1/auth/customer/login', ['phone' => '+2250700000001', 'password' => 'secret1'])->assertUnprocessable();
    }

    public function test_changing_the_phone_number_requires_the_current_password(): void
    {
        $customer = Customer::factory()->create(['phone' => '+2250700000001', 'password' => Hash::make('secret1')]);
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/customer/phone', [
                'phone' => '+2250708124400',
                'current_password' => 'mauvais',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Le mot de passe actuel est incorrect.');

        $this->assertSame('+2250700000001', $customer->fresh()->phone);
    }

    public function test_the_new_phone_number_must_be_free_valid_and_different(): void
    {
        Customer::factory()->create(['phone' => '+2250799999999']);
        $customer = Customer::factory()->create(['phone' => '+2250700000001', 'password' => Hash::make('secret1')]);
        $token = $customer->createToken('test')->plainTextToken;

        foreach (['+2250799999999' => 'Un compte existe déjà avec ce numéro.', '070812440' => 'Entrez un numéro ivoirien de 10 chiffres, ex. 07 08 12 44 00.', '+2250700000001' => 'C\'est déjà votre numéro actuel.'] as $phone => $message) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->putJson('/api/v1/customer/phone', ['phone' => $phone, 'current_password' => 'secret1'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.phone.0', $message);
        }

        $this->assertSame('+2250700000001', $customer->fresh()->phone);
    }
}
