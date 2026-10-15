<?php

namespace App\Actions\Group;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Pressing;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Recopie le catalogue (services actifs et leurs variantes) d'un pressing
 * vers un autre : ajoute ce qui manque et aligne les prix des services
 * portant le même nom. Rien n'est supprimé chez la cible : ses services en
 * plus restent, et un prix peut toujours être ajusté localement ensuite.
 */
class SyncCatalog
{
    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function handle(Pressing $source, Pressing $target, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $services = $source->services()->where('is_active', true)->with(['variants' => fn ($q) => $q->where('is_active', true)])->get();
        $existing = $target->services()->with('variants')->get()->keyBy(fn (Service $s) => mb_strtolower(trim($s->name)));
        $subscription = $target->subscription;

        $run = function () use ($services, $existing, $target, $subscription, $dryRun, &$stats) {
            foreach ($services as $service) {
                $match = $existing->get(mb_strtolower(trim($service->name)));

                if ($match === null) {
                    if ($subscription !== null && ! $subscription->allowsNewService()) {
                        $stats['skipped']++;

                        continue;
                    }
                    $stats['created']++;
                    if (! $dryRun) {
                        $copy = $target->services()->create(['name' => $service->name, 'price_fcfa' => $service->price_fcfa, 'is_active' => true]);
                        foreach ($service->variants as $variant) {
                            $copy->variants()->create(['name' => $variant->name, 'price_fcfa' => $variant->price_fcfa, 'is_active' => true]);
                        }
                    }

                    continue;
                }

                $changed = $match->price_fcfa !== $service->price_fcfa || ! $match->is_active;
                $variants = $match->variants->keyBy(fn ($v) => mb_strtolower(trim($v->name)));
                foreach ($service->variants as $variant) {
                    $v = $variants->get(mb_strtolower(trim($variant->name)));
                    if ($v === null || $v->price_fcfa !== $variant->price_fcfa || ! $v->is_active) {
                        $changed = true;
                        if (! $dryRun) {
                            $v
                                ? $v->update(['price_fcfa' => $variant->price_fcfa, 'is_active' => true])
                                : $match->variants()->create(['name' => $variant->name, 'price_fcfa' => $variant->price_fcfa, 'is_active' => true]);
                        }
                    }
                }

                if ($changed) {
                    $stats['updated']++;
                    if (! $dryRun) {
                        $match->update(['price_fcfa' => $service->price_fcfa, 'is_active' => true]);
                    }
                }
            }
        };

        $dryRun ? $run() : DB::transaction($run);

        if (! $dryRun && ($stats['created'] + $stats['updated']) > 0) {
            ActivityLog::record($target->id, ActivityAction::CatalogSynced,
                "Tarifs repris de {$source->name} : {$stats['created']} service(s) ajouté(s), {$stats['updated']} mis à jour",
                null, $stats + ['source_pressing_id' => $source->id]);
        }

        return $stats;
    }
}
