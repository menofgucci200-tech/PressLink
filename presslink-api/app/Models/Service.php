<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tarif d'un pressing pour un type d'article — Cahier §17. Peut être décliné
 * en variantes à prix différents (cf. ServiceVariant) — ex. "Chemise"
 * manche courte vs manche longue.
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = ['pressing_id', 'name', 'price_fcfa', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** @return HasMany<ServiceVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ServiceVariant::class);
    }
}
