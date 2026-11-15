<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** « Pas pour nous » : un pressing masque chez lui une demande ouverte à sa zone. */
class PickupRequestDismissal extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['pickup_request_id', 'pressing_id'];

    /** @return BelongsTo<PickupRequest, $this> */
    public function pickupRequest(): BelongsTo
    {
        return $this->belongsTo(PickupRequest::class);
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }
}
