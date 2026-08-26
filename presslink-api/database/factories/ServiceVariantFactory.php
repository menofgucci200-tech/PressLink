<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceVariant>
 */
class ServiceVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'name' => fake()->randomElement(['Manche courte', 'Manche longue', 'Sans manche']),
            'price_fcfa' => fake()->randomElement([1000, 1500, 2000]),
            'is_active' => true,
        ];
    }
}
