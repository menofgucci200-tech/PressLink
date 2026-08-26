<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $unitPrice = fake()->randomElement([1000, 1500, 2000, 3000, 4000]);
        $quantity = fake()->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'service_id' => null,
            'name' => fake()->randomElement(['Chemise', 'Pantalon', 'Costume', 'Robe']),
            'unit_price_fcfa' => $unitPrice,
            'quantity' => $quantity,
            'subtotal_fcfa' => $unitPrice * $quantity,
        ];
    }
}
