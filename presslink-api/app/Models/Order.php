<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\Carbon;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /** Prête depuis ce nombre de jours : à relancer (orange). */
    public const READY_REMINDER_DAYS = 5;

    /** Prête depuis ce nombre de jours : oubliée (rouge). */
    public const READY_FORGOTTEN_DAYS = 14;

    /**
     * Ajoute `ready_since` : date du passage au statut « Prête », pour les
     * alertes de retard des listes (une seule sous-requête, pas de N+1).
     *
     * @param  Builder<Order>  $query
     */
    public function scopeWithReadySince(Builder $query): void
    {
        $query->withMax(
            ['statusHistory as ready_since' => fn ($q) => $q->where('status', OrderStatus::Prete->value)->reorder()],
            'created_at',
        );
    }

    /**
     * Alerte affichée dans les listes quand une commande traîne : prête et
     * pas encore retirée, ou pas prête à la date promise au client.
     *
     * @return array{label: string, tone: 'warning'|'danger'}|null
     */
    public function delayAlert(): ?array
    {
        if ($this->status === OrderStatus::Prete) {
            $readySince = $this->ready_since ? Carbon::parse($this->ready_since) : $this->updated_at;
            $days = (int) $readySince->copy()->startOfDay()->diffInDays(today());

            if ($days >= self::READY_REMINDER_DAYS) {
                return [
                    'label' => "Prête depuis {$days} j",
                    'tone' => $days >= self::READY_FORGOTTEN_DAYS ? 'danger' : 'warning',
                ];
            }

            return null;
        }

        $inProgress = in_array($this->status, [OrderStatus::Recue, OrderStatus::Traitement, OrderStatus::Attente], true);
        if ($inProgress && $this->expected_at && $this->expected_at->lt(today())) {
            $days = max(1, (int) $this->expected_at->copy()->startOfDay()->diffInDays(today()));

            return ['label' => "En retard de {$days} j", 'tone' => 'danger'];
        }

        return null;
    }

    public function recalculateTotal(): void
    {
        $this->total_fcfa = $this->items()->sum('subtotal_fcfa');
        $this->save();
    }
}
