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
    /** Le prix de la variante est rattaché au service « Nettoyage » du pressing. */
    public function configure(): static
    {
        return $this->afterCreating(function (ServiceVariant $variant) {
            $cleaning = $variant->service?->pressing?->cleaningPrestation();

            if ($cleaning !== null && $variant->price_fcfa !== null) {
                $variant->service->setPrice($cleaning, (int) $variant->price_fcfa, $variant);
            }
        });
    }

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
