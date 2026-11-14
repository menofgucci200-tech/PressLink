<?php

namespace App\Models;

use App\Enums\OrderDeliveryStatus;
use Database\Factories\OrderDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Livraison à domicile d'une commande prête : le client choisit l'adresse
 * et le créneau dans l'appli, le coursier remet la commande et encaisse le
 * reste à payer (frais de livraison compris) à la porte. L'adresse est
 * recopiée, comme pour un ramassage.
 */
class OrderDelivery extends Model
{
    /** @use HasFactory<OrderDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'pressing_id',
        'order_id',
        'customer_id',
        'status',
        'address_label',
        'commune',
        'neighborhood',
        'landmark',
        'delivery_date',
        'slot_from',
        'slot_to',
        'rescheduled',
        'note',
        'delivery_fee_fcfa',
        'cancel_reason',
        'handled_by',
        'delivered_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderDeliveryStatus::class,
            'delivery_date' => 'date',
            'rescheduled' => 'boolean',
            'delivered_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isScheduled(): bool
    {
        return $this->status === OrderDeliveryStatus::Scheduled;
    }

    /** Fin du créneau prévu. */
    public function slotEndsAt(): Carbon
    {
        return $this->delivery_date->copy()->setTimeFromTimeString($this->slot_to);
    }

    /** « demain, 14:00 – 18:00 » */
    public function scheduleLabel(): string
    {
        $date = $this->delivery_date->copy()->locale('fr');
        $day = match (true) {
            $date->isToday() => 'aujourd\'hui',
            $date->isTomorrow() => 'demain',
            default => $date->isoFormat('dddd D MMMM'),
        };

        return "{$day}, {$this->slot_from} – {$this->slot_to}";
    }

    /** « Cocody, Riviera 3 » */
    public function place(): string
    {
        return implode(', ', array_filter([$this->commune, $this->neighborhood]));
    }

    /** À encaisser à la porte : le reste à payer de la commande et les frais de livraison. */
    public function amountToCollect(): int
    {
        return $this->order->balance_due_fcfa + ($this->isScheduled() ? $this->delivery_fee_fcfa : 0);
    }

    /**
     * Pour l'application client.
     *
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        $this->loadMissing('order');

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'address' => [
                'label' => $this->address_label,
                'commune' => $this->commune,
                'neighborhood' => $this->neighborhood,
                'landmark' => $this->landmark,
            ],
            'delivery_date' => $this->delivery_date->toDateString(),
            'slot_from' => $this->slot_from,
            'slot_to' => $this->slot_to,
            'schedule_label' => $this->scheduleLabel(),
            'rescheduled' => $this->rescheduled,
            'note' => $this->note,
            'delivery_fee_fcfa' => $this->delivery_fee_fcfa,
            'amount_to_collect_fcfa' => $this->isScheduled() ? $this->amountToCollect() : 0,
            'cancel_reason' => $this->cancel_reason,
            'can_cancel' => $this->isScheduled(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
        ];
    }
}
