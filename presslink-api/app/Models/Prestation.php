<?php

namespace App\Models;

use App\Support\ServiceIcons;
use Database\Factories\PrestationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Service proposé par un pressing (Repassage, Nettoyage, Retouches…).
 *
 * Vocabulaire : dans l'interface, un « service » est une Prestation ; une
 * « pièce » (Chemise, Robe, Drap…) est le modèle historique `Service`, dont
 * le prix dépend du service choisi (cf. ServicePrice).
 */
class Prestation extends Model
{
    /** @use HasFactory<PrestationFactory> */
    use HasFactory;

    /** Services créés d'office pour chaque pressing : [nom, icône]. */
    public const DEFAULTS = [
        ['Repassage', 'repassage'],
        ['Nettoyage', 'nettoyage'],
    ];

    protected $fillable = ['pressing_id', 'name', 'icon', 'position', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public static function createDefaultsFor(Pressing $pressing): void
    {
        foreach (self::DEFAULTS as $i => [$name, $icon]) {
            $pressing->prestations()->create(['name' => $name, 'icon' => $icon, 'position' => $i + 1, 'is_active' => true]);
        }
    }

    /** @param  Builder<Prestation>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /** Clé d'icône valide (celle choisie, sinon déduite du nom). */
    public function iconKey(): string
    {
        return ServiceIcons::has('service', $this->icon) ? $this->icon : ServiceIcons::guessService($this->name);
    }

    /** Déjà utilisé dans au moins une commande : on le désactive au lieu de le supprimer. */
    public function isUsed(): bool
    {
        return $this->orderItems()->exists();
    }

    /** @return BelongsTo<Pressing, $this> */
    public function pressing(): BelongsTo
    {
        return $this->belongsTo(Pressing::class);
    }

    /** @return HasMany<ServicePrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
