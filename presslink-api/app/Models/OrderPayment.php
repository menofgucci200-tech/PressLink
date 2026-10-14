<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\OrderPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un règlement encaissé sur une commande (paiement complet, acompte ou
 * solde au retrait). La somme est reportée sur `orders.amount_paid_fcfa`.
 */
class OrderPayment extends Model
{
    /** @use HasFactory<OrderPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'amount_fcfa',
        'method',
        'note',
        'recorded_by',
        'paid_at',
    ];

    /** @var list<string> */
    protected $appends = ['method_label'];

    protected function casts(): array
    {
        return [
            'amount_fcfa' => 'integer',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            $payment->paid_at ??= now();
        });

        static::saved(fn (self $payment) => $payment->order->refreshAmountPaid());
        static::deleted(fn (self $payment) => $payment->order->refreshAmountPaid());
    }

    public function getMethodLabelAttribute(): string
    {
        return $this->method->label();
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
