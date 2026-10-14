<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderPayment>
 */
class OrderPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount_fcfa' => fake()->numberBetween(5, 50) * 100,
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'paid_at' => now(),
        ];
    }
}
