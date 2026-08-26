<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '+225'.fake()->unique()->numerify('0#########'),
            'password' => Hash::make('password'),
            'gender' => fake()->randomElement(Gender::cases()),
            'phone_verified_at' => now(),
            'last_login_at' => fake()->dateTimeBetween('-1 month'),
            'is_active' => true,
        ];
    }
}
