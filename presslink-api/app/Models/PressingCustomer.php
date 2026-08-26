<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PressingCustomer extends Pivot
{
    protected $table = 'pressing_customers';

    protected $fillable = ['pressing_id', 'customer_id', 'joined_at'];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }
}
