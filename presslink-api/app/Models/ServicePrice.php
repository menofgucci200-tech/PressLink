<?php

namespace App\Models;

use App\Models\Concerns\LogsCatalogChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prix d'une pièce (ou d'une de ses variantes) pour un service donné — ex.
 * Chemise : 500 F en Repassage, 1 000 F en Nettoyage. Pas de ligne = la pièce
 * n'est pas proposée pour ce service.
 */
class ServicePrice extends Model
{
    use LogsCatalogChanges;

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

    public function catalogPressingId(): ?int
    {
        return $this->piece?->pressing_id;
    }

    public function catalogDescription(string $event): ?string
    {
        $label = collect([$this->piece?->name, $this->variant?->name])->filter()->implode(' · ').' ('.($this->prestation?->name ?? 'service').')';
        $fcfa = fn (?int $amount) => number_format((int) $amount, 0, ',', ' ').' F';

        return match (true) {
            $event === 'created' => "Prix de {$label} : {$fcfa($this->price_fcfa)}",
            $event === 'deleted' => "Prix de {$label} retiré (était {$fcfa($this->price_fcfa)})",
            $this->wasChanged('price_fcfa') => "Prix de {$label} : {$fcfa($this->getOriginal('price_fcfa'))} → {$fcfa($this->price_fcfa)}",
            default => null,
        };
    }
}
