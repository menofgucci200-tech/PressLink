<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * « Ce pressing vient de moi » : un commercial signale un pressing inscrit
 * sans son code ; le Super Admin le rattache (ou refuse) depuis la console.
 */
class SalesAgentClaim extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    protected $fillable = ['sales_agent_id', 'pressing_id', 'pressing_name', 'phone', 'note', 'status', 'handled_by', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
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
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::APPROVED => 'Rattaché',
            self::DECLINED => 'Refusé',
            default => 'En attente',
        };
    }
}
