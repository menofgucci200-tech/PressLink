<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prix d'une pièce (ou d'une de ses variantes) pour un service donné — ex.
 * Chemise : 500 F en Repassage, 1 000 F en Nettoyage. Pas de ligne = la pièce
 * n'est pas proposée pour ce service.
 */
class ServicePrice extends Model
{
    protected $fillable = ['service_id', 'service_variant_id', 'prestation_id', 'price_fcfa'];

    protected function casts(): array
    {
        return ['price_fcfa' => 'integer'];
    }

    /** @return BelongsTo<Service, $this> */
    public function piece(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /** @return BelongsTo<ServiceVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ServiceVariant::class, 'service_variant_id');
    }

    /** @return BelongsTo<Prestation, $this> */
    public function prestation(): BelongsTo
    {
        return $this->belongsTo(Prestation::class);
    }
}
