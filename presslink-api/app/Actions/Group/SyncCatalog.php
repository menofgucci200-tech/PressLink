<?php

namespace App\Actions\Group;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Pressing;
use App\Models\Prestation;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Recopie le catalogue d'un pressing vers un autre : ses services (Repassage,
 * Nettoyage…), ses pièces actives avec leurs variantes, et le prix de chaque
 * pièce pour chaque service. Ajoute ce qui manque et aligne les prix des
 * éléments portant le même nom. Rien n'est supprimé chez la cible : ses
 * services et pièces en plus restent, et un prix peut toujours être ajusté
 * localement ensuite.
 */
class SyncCatalog
{
    /**
     * @return array{created: int, updated: int, skipped: int, services: int}
     */
    public function handle(Pressing $source, Pressing $target, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'services' => 0];
        $norm = fn (string $name) => mb_strtolower(trim($name));

        $sourcePrestations = $source->prestations()->where('is_active', true)->get();
        $sourcePieces = $source->services()->where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true), 'prices'])
            ->get();
        $targetPrestations = $target->prestations()->get()->keyBy(fn (Prestation $p) => $norm($p->name));
        $targetPieces = $target->services()->with(['variants'])->get()->keyBy(fn (Service $s) => $norm($s->name));
        $subscription = $target->subscription;

        $run = function () use ($sourcePrestations, $sourcePieces, $targetPrestations, $targetPieces, $target, $subscription, $dryRun, $norm, &$stats) {
            // 1. Services : ceux de la source qui manquent chez la cible.
            $prestationMap = [];
            foreach ($sourcePrestations as $sp) {
                $tp = $targetPrestations->get($norm($sp->name));

                if ($tp === null) {
                    $stats['services']++;
                    if (! $dryRun) {
                        $tp = $target->prestations()->create([
                            'name' => $sp->name,
                            'icon' => $sp->icon,
                            'position' => (int) $target->prestations()->max('position') + 1,
                            'is_active' => true,
                        ]);
                    }
                } elseif (! $tp->is_active) {
                    $stats['services']++;
                    if (! $dryRun) {
                        $tp->update(['is_active' => true]);
                    }
                }

                $prestationMap[$sp->id] = $tp;
            }

            // 2. Pièces, variantes et prix.
            foreach ($sourcePieces as $piece) {
                $match = $targetPieces->get($norm($piece->name));
                $changed = false;

                if ($match === null) {
                    if ($subscription !== null && ! $subscription->allowsNewService()) {
                        $stats['skipped']++;

                        continue;
                    }
                    $stats['created']++;
                    if ($dryRun) {
                        continue;
                    }
                    $match = $target->services()->create(['name' => $piece->name, 'icon' => $piece->icon, 'price_fcfa' => 0, 'is_active' => true]);
                    $match->setRelation('variants', collect());
                    $isNew = true;
                } else {
                    $isNew = false;
                    if (! $match->is_active) {
                        $changed = true;
                        $dryRun || $match->update(['is_active' => true]);
                    }
                }

                $targetVariants = $match->variants->keyBy(fn ($v) => $norm($v->name));

                foreach ($piece->prices as $price) {
                    if (! array_key_exists($price->prestation_id, $prestationMap)) {
                        continue; // service source désactivé : non repris
                    }
                    $tp = $prestationMap[$price->prestation_id]; // null en simulation si le service est à créer

                    $variant = null;
                    if ($price->service_variant_id !== null) {
                        $sourceVariant = $piece->variants->firstWhere('id', $price->service_variant_id);
                        if ($sourceVariant === null) {
                            continue; // variante désactivée
                        }
                        $variant = $targetVariants->get($norm($sourceVariant->name));
                        if ($variant === null) {
                            $changed = true;
                            if ($dryRun) {
                                continue;
                            }
                            $variant = $match->variants()->create(['name' => $sourceVariant->name, 'price_fcfa' => 0, 'is_active' => true]);
                            $targetVariants->put($norm($variant->name), $variant);
                        } elseif (! $variant->is_active) {
                            $changed = true;
                            $dryRun || $variant->update(['is_active' => true]);
                        }
                    }

                    $current = $tp !== null && ! $isNew ? $match->priceFor($tp, $variant) : null;
                    if ($current !== $price->price_fcfa) {
                        $changed = true;
                        if (! $dryRun && $tp !== null) {
                            $match->setPrice($tp, $price->price_fcfa, $variant);
                        }
                    }
                }

                if ($changed && ! $isNew) {
                    $stats['updated']++;
                }
            }
        };

        $dryRun ? $run() : DB::transaction($run);

        if (! $dryRun && ($stats['created'] + $stats['updated'] + $stats['services']) > 0) {
            ActivityLog::record($target->id, ActivityAction::CatalogSynced,
                "Services et tarifs repris de {$source->name} : {$stats['services']} service(s) et {$stats['created']} pièce(s) ajoutés, {$stats['updated']} pièce(s) mises à jour",
                null, $stats + ['source_pressing_id' => $source->id]);
        }

        return $stats;
    }
}
