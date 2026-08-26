<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_number' => Order::generateUniqueNumber(),
            'pressing_id' => Pressing::factory(),
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::Recue,
            'dropped_off_at' => fake()->dateTimeBetween('-1 week'),
            'expected_at' => fake()->dateTimeBetween('now', '+3 days'),
        ];
    }

    public function withStatus(OrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
