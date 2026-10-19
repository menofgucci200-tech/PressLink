<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+2250712345678';

    public function test_check_phone_reports_whether_an_account_exists(): void
    {
        $this->postJson('/api/v1/auth/customer/check-phone', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJson(['exists' => false]);

        Customer::factory()->create(['phone' => self::PHONE]);

        $this->postJson('/api/v1/auth/customer/check-phone', ['phone' => self::PHONE])
            ->assertOk()
            ->assertJson(['exists' => true]);
    }

    public function test_an_invalid_phone_format_is_rejected(): void
    {
        // Moins de 10 chiffres, plus de 10, un autre pays, un préfixe qui n'est pas un mobile.
        foreach (['07123456', '071234567890', '+255712345678', '0312345678'] as $phone) {
            $this->postJson('/api/v1/auth/customer/check-phone', ['phone' => $phone])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('phone');
        }
    }

    public function test_the_phone_can_be_sent_as_typed_and_is_stored_in_the_single_format(): void
    {
        $this->postJson('/api/v1/auth/customer/register', [
            'phone' => '07 12 34 56 78',
            'first_name' => 'Awa',
            'last_name' => 'Konan',
            'gender' => 'femme',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ])->assertCreated()->assertJsonPath('customer.phone', self::PHONE);

        $this->postJson('/api/v1/auth/customer/check-phone', ['phone' => '0712345678'])
            ->assertOk()
            ->assertJson(['exists' => true]);
    }

    public function test_a_new_customer_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/customer/register', [
            'phone' => self::PHONE,
            'first_name' => 'Stéphane',
            'last_name' => 'SAY',
            'gender' => 'homme',
            'password' => 'pass',
            'password_confirmation' => 'pass',
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('customers', ['phone' => self::PHONE, 'first_name' => 'Stéphane', 'gender' => 'homme']);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/customer/register', [
            'phone' => self::PHONE,
            'first_name' => 'Stéphane',
            'last_name' => 'SAY',
            'gender' => 'homme',
            'password' => 'pass',
            'password_confirmation' => 'other',
        ])->assertUnprocessable();
    }

    public function test_registration_rejects_a_password_shorter_than_four_characters(): void
    {
        $this->postJson('/api/v1/auth/customer/register', [
            'phone' => self::PHONE,
            'first_name' => 'Stéphane',
            'last_name' => 'SAY',
            'gender' => 'homme',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])->assertUnprocessable();
    }

    public function test_cannot_register_twice_with_the_same_phone(): void
    {
        Customer::factory()->create(['phone' => self::PHONE]);

        $this->postJson('/api/v1/auth/customer/register', [
            'phone' => self::PHONE,
            'first_name' => 'Stéphane',
            'last_name' => 'SAY',
            'gender' => 'homme',
            'password' => 'pass',
            'password_confirmation' => 'pass',
        ])->assertUnprocessable();
    }

    public function test_a_registered_customer_can_log_in(): void
    {
        Customer::factory()->create([
            'phone' => self::PHONE,
            'password' => Hash::make('pass'),
        ]);

        $response = $this->postJson('/api/v1/auth/customer/login', [
            'phone' => self::PHONE,
            'password' => 'pass',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Customer::factory()->create([
            'phone' => self::PHONE,
            'password' => Hash::make('pass'),
        ]);

        $this->postJson('/api/v1/auth/customer/login', [
            'phone' => self::PHONE,
            'password' => 'wrong',
        ])->assertUnprocessable();
    }

    public function test_login_fails_for_unknown_phone(): void
    {
        $this->postJson('/api/v1/auth/customer/login', [
            'phone' => self::PHONE,
            'password' => 'pass',
        ])->assertUnprocessable();
    }

    public function test_authenticated_customer_can_fetch_their_profile(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/customer/me')
            ->assertOk()
            ->assertJson(['id' => $customer->id]);
    }

    public function test_customer_can_log_out(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->postJson('/api/v1/auth/customer/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
