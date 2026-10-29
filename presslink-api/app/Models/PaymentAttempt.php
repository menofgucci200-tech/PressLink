<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Un paiement reçu (ou tenté) par PressLink, avec sa référence PressLink
 * (PL-AAMMJJ-XXXXX) : chaque tentative de paiement en ligne GeniusPay, qu'elle
 * réussisse, échoue, soit abandonnée ou expire, et chaque paiement enregistré
 * à la main par l'équipe. L'historique reste même si la demande ou le pressing
 * est supprimé (nom du pressing et objet du paiement gardés).
 */
class PaymentAttempt extends Model
{
    public const ONLINE = 'online';

    public const MANUAL = 'manual';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    /** Statuts comptés comme échecs dans le taux de réussite. */
    public const FAILURES = [self::FAILED, self::CANCELLED, self::EXPIRED];

    /** @var array<string, string> statut => libellé */
    public const STATUS_LABELS = [
        self::PAID => 'Payé',
        self::FAILED => 'Échoué',
        self::CANCELLED => 'Abandonné',
        self::EXPIRED => 'Expiré',
        self::PENDING => 'En cours',
    ];

    protected $fillable = [
        'reference',
        'subscription_request_id',
        'pressing_id',
        'pressing_name',
        'label',
        'channel',
        'payment_method',
        'status',
        'amount_fcfa',
        'fee_fcfa',
        'total_fcfa',
        'paid_amount_fcfa',
        'gateway_fees_fcfa',
        'net_received_fcfa',
        'gateway_reference',
        'environment',
        'failure_reason',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SubscriptionRequest, $this> */
    public function subscriptionRequest(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRequest::class);
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** Référence PressLink d'un nouveau paiement : PL-AAMMJJ-XXXXX (sans 0/O ni 1/I). */
    public static function newReference(): string
    {
        do {
            $suffix = collect(range(1, 5))->map(fn () => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)])->implode('');
            $reference = 'PL-'.now()->format('ymd').'-'.$suffix;
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    /** Tentative de paiement en ligne qui démarre (avant l'appel à GeniusPay). */
    public static function startOnline(SubscriptionRequest $request, ?string $method, int $fee): self
    {
        return self::create([
            'reference' => self::newReference(),
            'subscription_request_id' => $request->id,
            'pressing_id' => $request->pressing_id,
            'pressing_name' => $request->pressing?->name ?? '—',
            'label' => Str::limit($request->label(), 250, ''),
            'channel' => self::ONLINE,
            'payment_method' => $method,
            'status' => self::PENDING,
            'amount_fcfa' => (int) $request->amount_fcfa,
            'fee_fcfa' => $fee,
            'total_fcfa' => (int) $request->amount_fcfa + $fee,
            'started_at' => now(),
        ]);
    }

    /** Paiement validé à la main par l'équipe (virement, espèces, lien Wave…). */
    public static function recordManual(SubscriptionRequest $request): self
    {
        return self::create([
            'reference' => self::newReference(),
            'subscription_request_id' => $request->id,
            'pressing_id' => $request->pressing_id,
            'pressing_name' => $request->pressing?->name ?? '—',
            'label' => Str::limit($request->label(), 250, ''),
            'channel' => self::MANUAL,
            'status' => self::PAID,
            'amount_fcfa' => (int) $request->amount_fcfa,
            'total_fcfa' => (int) $request->amount_fcfa,
            'paid_amount_fcfa' => (int) $request->amount_fcfa,
            'net_received_fcfa' => (int) $request->amount_fcfa,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /** Échec, abandon ou expiration d'une tentative encore en cours. */
    public function markFailed(string $status, ?string $reason = null): void
    {
        if ($this->status !== self::PENDING) {
            return;
        }

        $this->update([
            'status' => in_array($status, self::FAILURES, true) ? $status : self::FAILED,
            'failure_reason' => $reason !== null ? Str::limit($reason, 250, '') : null,
            'completed_at' => now(),
        ]);
    }

    public function isFailure(): bool
    {
        return in_array($this->status, self::FAILURES, true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** « Wave », « Orange Money »… ou « Enregistré par l'équipe ». */
    public function methodLabel(): string
    {
        return $this->channel === self::MANUAL
            ? 'Enregistré par l\'équipe'
            : (SubscriptionRequest::ONLINE_METHODS[$this->payment_method] ?? ($this->payment_method ? ucfirst(str_replace('_', ' ', $this->payment_method)) : 'En ligne'));
    }

    /** Montant réellement encaissé (sinon le montant demandé). */
    public function collectedFcfa(): int
    {
        return (int) ($this->paid_amount_fcfa ?? $this->total_fcfa);
    }

    /** Net reçu par PressLink : connu, sinon encaissé moins frais connus. */
    public function netFcfa(): int
    {
        return (int) ($this->net_received_fcfa ?? $this->collectedFcfa() - (int) $this->gateway_fees_fcfa);
    }

    /** @param  Builder<PaymentAttempt>  $query */
    public function scopeOnline(Builder $query): void
    {
        $query->where('channel', self::ONLINE);
    }

    /** @param  Builder<PaymentAttempt>  $query */
    public function scopePaid(Builder $query): void
    {
        $query->where('status', self::PAID);
    }

    /** @param  Builder<PaymentAttempt>  $query */
    public function scopeFailures(Builder $query): void
    {
        $query->whereIn('status', self::FAILURES);
    }
}
