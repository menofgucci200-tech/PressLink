<?php

namespace App\Models;

use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adresse de ramassage et de livraison d'un client : commune (qui décide
 * des pressings disponibles), quartier, repère, et position si le client
 * la partage.
 */
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    public const MAX_PER_CUSTOMER = 5;

    protected $fillable = [
        'label',
        'commune',
        'neighborhood',
        'landmark',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected $hidden = ['customer_id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
