<?php

namespace Database\Factories;

use App\Enums\OrderDeliveryStatus;
use App\Models\Order;
use App\Models\OrderDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDelivery>
 */
class OrderDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'pressing_id' => fn (array $attributes) => Order::find($attributes['order_id'])->pressing_id,
            'customer_id' => fn (array $attributes) => Order::find($attributes['order_id'])->customer_id,
            'status' => OrderDeliveryStatus::Scheduled,
            'address_label' => 'Maison',
            'commune' => 'Cocody',
            'neighborhood' => 'Riviera 3',
            'landmark' => 'Portail vert face au maquis',
            'delivery_date' => today()->addDay()->toDateString(),
            'slot_from' => '14:00',
            'slot_to' => '18:00',
            'delivery_fee_fcfa' => 1000,
        ];
    }
}
