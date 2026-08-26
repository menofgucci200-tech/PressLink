<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RB-03 : une commande appartient obligatoirement à 1 pressing + 1 client.
 * RB-10 : soft delete uniquement — jamais de suppression physique.
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'pressing_id',
        'customer_id',
        'status',
        'total_fcfa',
        'notes',
        'dropped_off_at',
        'expected_at',
        'recovered_at',
        'created_by',
        'recovered_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'dropped_off_at' => 'datetime',
            'expected_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->order_number ??= self::generateUniqueNumber();
            $order->dropped_off_at ??= now();
        });
    }

    public static function generateUniqueNumber(): string
    {
        do {
            $number = 'PL-'.str_pad((string) random_int(1, 999_999), 6, '0', STR_PAD_LEFT);
        } while (self::withTrashed()->where('order_number', $number)->exists());

        return $number;
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function recoveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recovered_by');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    /** @return HasMany<OrderIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(OrderIssue::class)->latest();
    }

    public function recalculateTotal(): void
    {
        $this->total_fcfa = $this->items()->sum('subtotal_fcfa');
        $this->save();
    }
}
