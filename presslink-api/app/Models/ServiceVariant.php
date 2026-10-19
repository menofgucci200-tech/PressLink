<?php

namespace App\Models;

use Database\Factories\ServiceVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Déclinaison d'un service avec son propre tarif — ex. "Chemise" peut avoir
 * les variantes "Manche courte" et "Manche longue", chacune à son prix.
 */
class ServiceVariant extends Model
{
    /** @use HasFactory<ServiceVariantFactory> */
    use HasFactory;

    protected $fillable = ['service_id', 'name', 'price_fcfa', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ServicePrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
