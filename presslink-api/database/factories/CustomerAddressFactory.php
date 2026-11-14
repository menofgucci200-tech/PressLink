<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Support\Communes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'label' => fake()->randomElement(['Maison', 'Bureau']),
            'commune' => fake()->randomElement(Communes::ABIDJAN),
            'neighborhood' => fake()->randomElement(['Riviera 3', 'Angré', 'Zone 4', 'II Plateaux']),
            'landmark' => 'Immeuble bleu derrière la pharmacie',
            'is_default' => false,
        ];
    }
}
