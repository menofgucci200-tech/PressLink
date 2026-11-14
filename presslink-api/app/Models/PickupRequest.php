<?php

namespace App\Models;

use App\Enums\PickupRequestStatus;
use Database\Factories\PickupRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Demande de ramassage : le client envoie sa commande depuis l'appli, son
 * pressing passe chercher le linge sur le créneau convenu.
 */
class PickupRequest extends Model
{
    /** @use HasFactory<PickupRequestFactory> */
    use HasFactory;

    /** Demandes en cours en même temps, tous pressings confondus. */
    public const MAX_OPEN_PER_CUSTOMER = 3;

    /** Jours à l'avance pour lesquels le client peut demander un ramassage. */
    public const BOOKING_DAYS = 14;

    /** Un créneau du jour reste proposé jusqu'à 2 h avant sa fin. */
    public const MIN_NOTICE_MINUTES = 120;

    /** Jours de conservation des photos après la fin de la demande. */
    public const PHOTO_RETENTION_DAYS = 30;

    /** Motifs de refus proposés au pressing. */
    public const REFUSAL_REASONS = [
        'Pas de passage possible sur ce créneau',
        'Adresse trop éloignée',
        'Pressing fermé ce jour-là',
        'Service momentanément indisponible',
    ];

    protected $fillable = [
        'pressing_id',
        'customer_id',
        'order_id',
        'status',
        'address_label',
        'commune',
        'neighborhood',
        'landmark',
        'pickup_date',
        'slot_from',
        'slot_to',
        'rescheduled',
        'items_estimate',
        'note',
        'pickup_fee_fcfa',
        'refusal_reason',
        'responded_by',
        'responded_at',
        'picked_up_at',
        'picked_up_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PickupRequestStatus::class,
            'pickup_date' => 'date',
            'rescheduled' => 'boolean',
            'responded_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
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
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Commande (dépôt rapide) créée au ramassage.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pickedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_up_by');
    }

    /** @return HasMany<PickupRequestPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(PickupRequestPhoto::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', PickupRequestStatus::openValues());
    }

    /** Fin du créneau prévu. */
    public function slotEndsAt(): Carbon
    {
        return $this->pickup_date->copy()->setTimeFromTimeString($this->slot_to);
    }

    /** « mardi 18 novembre, 08:00 – 12:00 » */
    public function scheduleLabel(): string
    {
        $date = $this->pickup_date->copy()->locale('fr');
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

    /**
     * Pour l'application client.
     *
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        $this->loadMissing(['pressing', 'photos']);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'pressing' => [
                'id' => $this->pressing->id,
                'name' => $this->pressing->name,
                'phone' => $this->pressing->supportContact()['phone'],
            ],
            'address' => [
                'label' => $this->address_label,
                'commune' => $this->commune,
                'neighborhood' => $this->neighborhood,
                'landmark' => $this->landmark,
            ],
            'pickup_date' => $this->pickup_date->toDateString(),
            'slot_from' => $this->slot_from,
            'slot_to' => $this->slot_to,
            'schedule_label' => $this->scheduleLabel(),
            'rescheduled' => $this->rescheduled,
            'items_estimate' => $this->items_estimate,
            'note' => $this->note,
            'pickup_fee_fcfa' => $this->pickup_fee_fcfa,
            'refusal_reason' => $this->refusal_reason,
            'photos' => $this->photos->map->toClientArray()->values()->all(),
            'can_cancel' => $this->status->isOpen(),
            'can_add_photos' => $this->status === PickupRequestStatus::Pending && $this->photos->count() < PickupRequestPhoto::MAX_PER_REQUEST,
            'created_at' => $this->created_at->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'order_id' => $this->order_id,
            'picked_up_at' => $this->picked_up_at?->toIso8601String(),
        ];
    }
}
