<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderPhoto>
 */
class OrderPhotoFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::random(40);

        return [
            'order_id' => Order::factory(),
            'path' => "order-photos/test/{$name}.webp",
            'thumb_path' => "order-photos/test/{$name}-mini.webp",
            'size_bytes' => 120_000,
        ];
    }
}
