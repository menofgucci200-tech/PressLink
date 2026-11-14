<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commune desservie par un pressing pour le ramassage et la livraison
 * (liste fermée : App\Support\Communes).
 */
class PressingServiceArea extends Model
{
    public $timestamps = false;

    protected $fillable = ['pressing_id', 'commune'];

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }
}
