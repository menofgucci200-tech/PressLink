<?php

namespace Database\Factories;

use App\Models\Pressing;
use App\Models\Prestation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prestation>
 */
class PrestationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pressing_id' => Pressing::factory(),
            'name' => fake()->unique()->randomElement(['Retouches', 'Détachage', 'Teinture', 'Express', 'Blanchisserie']),
            'icon' => 'autre',
            'position' => 10,
            'is_active' => true,
        ];
    }
}
