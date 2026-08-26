<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'service_id',
        'service_variant_id',
        'name',
        'color',
        'unit_price_fcfa',
        'quantity',
        'subtotal_fcfa',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->subtotal_fcfa = $item->unit_price_fcfa * $item->quantity;
        });

        static::saved(fn (self $item) => $item->order->recalculateTotal());
        static::deleted(fn (self $item) => $item->order->recalculateTotal());
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<ServiceVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ServiceVariant::class, 'service_variant_id');
    }
}
