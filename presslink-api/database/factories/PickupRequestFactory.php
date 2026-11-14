<?php

namespace Database\Factories;

use App\Enums\PickupRequestStatus;
use App\Models\Customer;
use App\Models\PickupRequest;
use App\Models\Pressing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PickupRequest>
 */
class PickupRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pressing_id' => Pressing::factory(),
            'customer_id' => Customer::factory(),
            'status' => PickupRequestStatus::Pending,
            'address_label' => 'Maison',
            'commune' => 'Cocody',
            'neighborhood' => 'Riviera 3',
            'landmark' => 'Immeuble bleu derrière la pharmacie',
            'pickup_date' => now()->addDay()->toDateString(),
            'slot_from' => '08:00',
            'slot_to' => '12:00',
            'items_estimate' => 6,
            'pickup_fee_fcfa' => 1000,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => PickupRequestStatus::Accepted, 'responded_at' => now()]);
    }
}
