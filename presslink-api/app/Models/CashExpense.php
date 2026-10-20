<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dépense payée avec les espèces de la caisse pendant la journée (produits,
 * transport, petite monnaie…). Elle diminue les espèces attendues à la clôture.
 */
class CashExpense extends Model
{
    protected $fillable = ['pressing_id', 'business_date', 'amount_fcfa', 'label', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'amount_fcfa' => 'integer',
        ];
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
