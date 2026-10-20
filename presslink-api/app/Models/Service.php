<?php

namespace App\Models;

use App\Models\Concerns\LogsCatalogChanges;
use App\Support\ServiceIcons;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * « Pièce » du catalogue d'un pressing (Chemise, Robe, Drap…) — Cahier §17.
 * Le nom de classe est historique : dans l'interface c'est une pièce, et un
 * « service » est une Prestation (Repassage, Nettoyage…). Son prix dépend du
 * service choisi (table service_prices) ; `price_fcfa` n'est plus que la
 * valeur d'origine, conservée pour mémoire. Peut être déclinée en variantes
 * (cf. ServiceVariant) — ex. "Chemise" manche courte vs manche longue.
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, LogsCatalogChanges;

    protected $fillable = ['pressing_id', 'name', 'icon', 'price_fcfa', 'is_active'];

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

    /** @return HasMany<ServicePrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    /** Clé d'icône valide (celle choisie, sinon déduite du nom). */
    public function iconKey(): string
    {
        return ServiceIcons::has('piece', $this->icon) ? $this->icon : ServiceIcons::guessPiece($this->name);
    }

    /** Prix pour un service (et une variante) ; null si la pièce n'y est pas proposée. */
    public function priceFor(Prestation|int $prestation, ServiceVariant|int|null $variant = null): ?int
    {
        $price = $this->prices()
            ->where('prestation_id', $prestation instanceof Prestation ? $prestation->id : $prestation)
            ->where('service_variant_id', $variant instanceof ServiceVariant ? $variant->id : $variant)
            ->value('price_fcfa');

        return $price === null ? null : (int) $price;
    }

    /** Fixe (ou, avec null, retire) le prix de la pièce — ou d'une variante — pour un service. */
    public function setPrice(Prestation|int $prestation, ?int $amount, ServiceVariant|int|null $variant = null): void
    {
        $keys = [
            'service_id' => $this->id,
            'service_variant_id' => $variant instanceof ServiceVariant ? $variant->id : $variant,
            'prestation_id' => $prestation instanceof Prestation ? $prestation->id : $prestation,
        ];

        if ($amount === null) {
            ServicePrice::where($keys)->get()->each->delete();

            return;
        }

        ServicePrice::updateOrCreate($keys, ['price_fcfa' => max(0, $amount)]);
    }

    public function catalogPressingId(): ?int
    {
        return $this->pressing_id;
    }

    public function catalogDescription(string $event): ?string
    {
        return match (true) {
            $event === 'created' => "Pièce « {$this->name} » ajoutée",
            $event === 'deleted' => "Pièce « {$this->name} » supprimée",
            $this->wasChanged('name') => "Pièce « {$this->getOriginal('name')} » renommée en « {$this->name} »",
            $this->activationChange() !== null => "Pièce « {$this->name} » ".str_replace(['activé'], ['activée'], $this->activationChange()),
            $this->wasChanged('icon') => "Icône de la pièce « {$this->name} » changée",
            default => null,
        };
    }
}
