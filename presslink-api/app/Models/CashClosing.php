<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Clôture de caisse d'un pressing pour une journée : espèces encaissées,
 * dépenses de la journée, espèces attendues (encaissées − dépenses), espèces
 * comptées et écart ; plus les totaux par moyen de paiement.
 */
class CashClosing extends Model
{
    protected $fillable = [
        'pressing_id', 'business_date', 'totals_by_method', 'total_fcfa', 'cash_received_fcfa', 'expenses_fcfa', 'expected_cash_fcfa',
        'declared_cash_fcfa', 'difference_fcfa', 'payments_count', 'note', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'totals_by_method' => 'array',
            'total_fcfa' => 'integer',
            'cash_received_fcfa' => 'integer',
            'expenses_fcfa' => 'integer',
            'expected_cash_fcfa' => 'integer',
            'declared_cash_fcfa' => 'integer',
            'difference_fcfa' => 'integer',
            'payments_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
