<?php

namespace App\Models;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
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
        'amount_paid_fcfa',
        'notes',
        'cancellation_reason',
        'cancellation_note',
        'dropped_off_at',
        'expected_at',
        'recovered_at',
        'created_by',
        'recovered_by',
    ];

    /**
     * Exposés à l'app client (API) : reste à payer et état du règlement.
     *
     * @var list<string>
     */
    protected $appends = ['balance_due_fcfa', 'payment_status', 'payment_status_label', 'cancellation_reason_label'];

    /** La note d'annulation est interne au pressing : elle ne part pas vers l'appli du client. */
    protected $hidden = ['cancellation_note'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'cancellation_reason' => CancellationReason::class,
            'total_fcfa' => 'integer',
            'amount_paid_fcfa' => 'integer',
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
    /**
     * Filtres des listes de commandes (pressing ou groupe) et des exports :
     * statut, recherche, dates de dépôt et état du paiement.
     *
     * @param  array{status?: ?string, search?: ?string, date_from?: ?string, date_to?: ?string, payment?: ?string}  $filters
     */
    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        // État du règlement : payée, acompte versé, rien encore payé, ou
        // « non soldée » (acompte ou rien payé : il reste de l'argent dû).
        if (($filters['payment'] ?? null) === Pressing::UNSETTLED_FILTER) {
            $query->whereColumn('amount_paid_fcfa', '<', 'total_fcfa')
                ->where('status', '!=', OrderStatus::Annulee->value);
        }

        match (PaymentStatus::tryFrom((string) ($filters['payment'] ?? ''))) {
            PaymentStatus::Paid => $query->whereColumn('amount_paid_fcfa', '>=', 'total_fcfa'),
            PaymentStatus::Partial => $query->where('amount_paid_fcfa', '>', 0)->whereColumn('amount_paid_fcfa', '<', 'total_fcfa'),
            PaymentStatus::Unpaid => $query->where('amount_paid_fcfa', 0)->where('total_fcfa', '>', 0),
            null => null,
        };

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            // MySQL (prod) et SQLite (tests) n'utilisent pas la même syntaxe
            // pour extraire un format de date — DATE_FORMAT vs strftime.
            $isSqlite = $query->getConnection()->getDriverName() === 'sqlite';
            [$dmy, $dm] = $isSqlite
                ? ["strftime('%d/%m/%Y', dropped_off_at)", "strftime('%d/%m', dropped_off_at)"]
                : ["DATE_FORMAT(dropped_off_at, '%d/%m/%Y')", "DATE_FORMAT(dropped_off_at, '%d/%m')"];

            $query->where(function (Builder $q) use ($term, $dmy, $dm) {
                $q->where('order_number', 'like', "%{$term}%")
                    ->orWhere('total_fcfa', 'like', "%{$term}%")
                    // Date de dépôt, dans les deux formats qu'un utilisateur
                    // tape naturellement (avec ou sans l'année).
                    ->orWhereRaw("{$dmy} LIKE ?", ["%{$term}%"])
                    ->orWhereRaw("{$dm} LIKE ?", ["%{$term}%"])
                    ->orWhereHas('customer', function (Builder $c) use ($term) {
                        $c->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    });
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('dropped_off_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('dropped_off_at', '<=', $filters['date_to']);
        }
    }

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

    /** @return HasMany<OrderPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class)->orderBy('paid_at')->orderBy('id');
    }

    /** Nom de la ligne ajoutée à la remise quand un droit de magasinage est dû. */
    public const STORAGE_FEE_LINE = 'Droit de magasinage';

    /** Date à laquelle la commande a été prête (dernier passage « Prête »). */
    public function readyAt(): ?Carbon
    {
        $readyAt = $this->statusHistory()->where('status', OrderStatus::Prete->value)->latest('created_at')->value('created_at');

        return $readyAt !== null ? Carbon::parse($readyAt) : null;
    }

    /**
     * Droit de magasinage dû à ce jour pour une commande prête non retirée
     * (voir Pressing::storageFeePolicy). Null s'il n'y a rien à facturer.
     *
     * @return array{days: int, amount: int, since: Carbon}|null
     */
    public function storageFee(?Carbon $at = null): ?array
    {
        $policy = $this->pressing?->storageFeePolicy();
        if ($policy === null || $this->status !== OrderStatus::Prete || $this->hasStorageFeeLine()) {
            return null;
        }

        // Le client n'est pas en retard tant que la commande n'est pas prête.
        $base = collect([$this->expected_at, $this->readyAt()])->filter()->max();
        if ($base === null) {
            return null;
        }

        $since = Carbon::parse($base)->startOfDay()->addDays($policy['grace_days'] + 1);
        $today = ($at ?? now())->copy()->startOfDay();
        if ($today->lt($since)) {
            return null;
        }

        $days = (int) $since->diffInDays($today) + 1;
        $periods = (int) ceil($days / Pressing::STORAGE_FEE_PERIODS[$policy['period']]);

        // Quantités par pièce du catalogue (0 = pièce hors catalogue), hors frais déjà facturés.
        $piecesById = [];
        foreach ($this->items()->where('name', 'not like', self::STORAGE_FEE_LINE.'%')->get(['service_id', 'quantity']) as $item) {
            $piecesById[(int) $item->service_id] = ($piecesById[(int) $item->service_id] ?? 0) + (int) $item->quantity;
        }

        $amount = 0;
        for ($period = 1; $period <= $periods; $period++) {
            $amount += Pressing::storageFeeForPeriod($policy, $period, $piecesById);
        }

        if ($policy['cap'] !== null) {
            $amount = min($amount, $policy['cap']);
        }

        return ['days' => $days, 'amount' => $amount, 'since' => $since];
    }

    public function hasStorageFeeLine(): bool
    {
        return $this->items()->where('name', 'like', self::STORAGE_FEE_LINE.'%')->exists();
    }

    /** Reste à payer (jamais négatif, même si le total a baissé après un paiement ; rien sur une commande annulée). */
    public function getBalanceDueFcfaAttribute(): int
    {
        if ($this->status === OrderStatus::Annulee) {
            return 0;
        }

        return max(0, (int) $this->total_fcfa - (int) $this->amount_paid_fcfa);
    }

    public function getCancellationReasonLabelAttribute(): ?string
    {
        return $this->cancellation_reason?->label();
    }

    public function paymentStatus(): PaymentStatus
    {
        return PaymentStatus::for((int) $this->total_fcfa, (int) $this->amount_paid_fcfa);
    }

    public function getPaymentStatusAttribute(): string
    {
        return $this->paymentStatus()->value;
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return $this->paymentStatus()->label();
    }

    /**
     * Enregistre un règlement (acompte, solde ou paiement complet). Le
     * montant est plafonné au reste à payer par l'appelant.
     */
    public function recordPayment(int $amountFcfa, PaymentMethod $method, ?int $recordedBy = null, ?string $note = null, ?\DateTimeInterface $paidAt = null): OrderPayment
    {
        return $this->payments()->create([
            'amount_fcfa' => $amountFcfa,
            'method' => $method,
            'recorded_by' => $recordedBy,
            'note' => $note,
            'paid_at' => $paidAt,
        ]);
    }

    public function refreshAmountPaid(): void
    {
        $this->amount_paid_fcfa = (int) $this->payments()->sum('amount_fcfa');
        $this->saveQuietly();
    }

    public function recalculateTotal(): void
    {
        $this->total_fcfa = $this->items()->sum('subtotal_fcfa');
        $this->save();
    }
}
