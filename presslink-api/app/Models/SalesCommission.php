<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Commission d'un commercial sur un paiement de l'abonnement (formule ou
 * renouvellement, hors options, packs de commandes et frais de paiement)
 * d'un pressing qu'il a apporté.
 */
class SalesCommission extends Model
{
    protected $fillable = ['sales_agent_id', 'pressing_id', 'subscription_request_id', 'pressing_name', 'label', 'base_fcfa', 'rate', 'amount_fcfa', 'earned_at', 'paid_at', 'payout_reference', 'paid_by'];

    protected function casts(): array
    {
        return [
            'base_fcfa' => 'integer',
            'rate' => 'integer',
            'amount_fcfa' => 'integer',
            'earned_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SalesAgent, $this> */
    public function salesAgent(): BelongsTo
    {
        return $this->belongsTo(SalesAgent::class);
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** @param Builder<self> $query */
    public function scopeUnpaid(Builder $query): void
    {
        $query->whereNull('paid_at');
    }

    /**
     * Commission due sur ce paiement, une seule fois : seulement pour un
     * paiement de l'abonnement d'un pressing rattaché à un commercial.
     */
    public static function recordFor(SubscriptionRequest $request): ?self
    {
        $pressing = $request->pressing;
        $agent = $pressing?->salesAgent;
        $base = (int) $request->amount_fcfa;

        if ($agent === null || $base <= 0 || $request->status !== SubscriptionRequest::DONE
            || ! in_array($request->kind, [SubscriptionRequest::KIND_PLAN, SubscriptionRequest::KIND_RENEWAL], true)) {
            return null;
        }

        $rate = $agent->commissionRate();

        return self::firstOrCreate(['subscription_request_id' => $request->id], [
            'sales_agent_id' => $agent->id,
            'pressing_id' => $pressing->id,
            'pressing_name' => $pressing->name,
            'label' => mb_substr($request->label(), 0, 255),
            'base_fcfa' => $base,
            'rate' => $rate,
            'amount_fcfa' => intdiv($base * $rate, 100),
            'earned_at' => $request->paid_online_at ?? $request->handled_at ?? now(),
        ]);
    }

    /**
     * Relevé par mois (le plus récent d'abord) : total gagné, déjà payé, à payer.
     *
     * @param  Collection<int, self>  $commissions
     * @return Collection<string, array{month: Carbon, total: int, paid: int, unpaid: int, count: int}>
     */
    public static function byMonth(Collection $commissions): Collection
    {
        return $commissions->sortByDesc('earned_at')
            ->groupBy(fn (self $commission) => $commission->earned_at->format('Y-m'))
            ->map(fn (Collection $lines, string $month) => [
                'month' => Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth(),
                'total' => $lines->sum('amount_fcfa'),
                'paid' => $lines->whereNotNull('paid_at')->sum('amount_fcfa'),
                'unpaid' => $lines->whereNull('paid_at')->sum('amount_fcfa'),
                'count' => $lines->count(),
            ]);
    }
}
