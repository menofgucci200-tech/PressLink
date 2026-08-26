<?php

namespace Database\Factories;

use App\Models\Pressing;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pressing_id' => Pressing::factory(),
            'name' => fake()->randomElement(['Chemise', 'Pantalon', 'Costume', 'Robe', 'Veste']),
            'price_fcfa' => fake()->randomElement([1000, 1500, 2000, 3000, 4000]),
            'is_active' => true,
        ];
    }
}
