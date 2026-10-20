<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Enums\PaymentMethod;
use Database\Factories\OrderPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Un règlement encaissé sur une commande (paiement complet, acompte ou
 * solde au retrait), ou un remboursement (montant négatif) quand une
 * commande payée est annulée. La somme est reportée sur `orders.amount_paid_fcfa`.
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

        // Un paiement encaissé ne se supprime pas : la caisse et les reçus
        // remis au client doivent rester vrais. (Un remboursement s'enregistre
        // à part, comme un montant négatif.)
        static::deleting(function (): void {
            throw new RuntimeException('Un paiement enregistré ne peut pas être supprimé.');
        });

        static::saved(fn (self $payment) => $payment->order->refreshAmountPaid());

        // Journal d'activité : chaque encaissement (et remboursement).
        static::created(fn (self $payment) => ActivityLog::record(
            $payment->order->pressing_id, ActivityAction::PaymentRecorded,
            ($payment->isRefund() ? 'Remboursement de ' : 'Paiement de ').number_format(abs($payment->amount_fcfa), 0, ',', ' ').' F ('.$payment->method->label().') sur '.$payment->order->order_number,
            $payment->order, ['amount_fcfa' => $payment->amount_fcfa, 'method' => $payment->method->value],
        ));
    }

    /** Remboursement (montant négatif), enregistré à l'annulation d'une commande déjà payée. */
    public function isRefund(): bool
    {
        return $this->amount_fcfa < 0;
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
