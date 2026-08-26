<?php

namespace Database\Factories;

use App\Enums\PressingStatus;
use App\Models\Pressing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pressing>
 */
class PressingFactory extends Factory
{
    public function definition(): array
    {
        $city = fake()->randomElement(['Cocody', 'Yopougon', 'Marcory', 'Plateau', 'Angré']);

        return [
            'name' => 'Pressing '.fake()->company(),
            'code' => Pressing::generateUniqueCode(),
            'phone' => '+225'.fake()->numerify('0# ## ## ## ##'),
            'email' => fake()->companyEmail(),
            'address' => fake()->streetAddress(),
            'city' => $city,
            'description' => fake()->sentence(),
            'status' => PressingStatus::Active,
        ];
    }
}
