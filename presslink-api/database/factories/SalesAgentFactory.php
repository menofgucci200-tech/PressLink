<?php

namespace Database\Factories;

use App\Models\SalesAgent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesAgent>
 */
class SalesAgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code' => 'COM-'.fake()->unique()->numberBetween(1000, 9999),
        ];
    }

    public function suspended(): static
    {
        return $this->state(['suspended_at' => now()]);
    }
}
