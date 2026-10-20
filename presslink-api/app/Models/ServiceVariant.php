<?php

namespace App\Models;

use App\Models\Concerns\LogsCatalogChanges;
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
    use HasFactory, LogsCatalogChanges;

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

    public function catalogPressingId(): ?int
    {
        return $this->service?->pressing_id;
    }

    public function catalogDescription(string $event): ?string
    {
        $piece = $this->service?->name ?? 'pièce';

        return match (true) {
            $event === 'created' => "Variante « {$this->name} » ajoutée à {$piece}",
            $event === 'deleted' => "Variante « {$this->name} » de {$piece} supprimée",
            $this->wasChanged('name') => "Variante « {$this->getOriginal('name')} » de {$piece} renommée en « {$this->name} »",
            $this->activationChange() !== null => "Variante « {$this->name} » de {$piece} ".str_replace(['activé'], ['activée'], $this->activationChange()),
            default => null,
        };
    }
}
