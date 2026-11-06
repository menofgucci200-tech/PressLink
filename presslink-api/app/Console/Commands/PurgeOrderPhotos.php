<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\OrderPhoto;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Supprime les photos des commandes remises (ou annulées) depuis plus de
 * {@see OrderPhoto::RETENTION_DAYS} jours : le client a eu le temps de
 * vérifier, et l'espace disque de l'hébergement reste maîtrisé.
 */
class PurgeOrderPhotos extends Command
{
    protected $signature = 'presslink:purge-order-photos';

    protected $description = 'Supprime les photos des commandes terminées depuis plus de '.OrderPhoto::RETENTION_DAYS.' jours.';

    public function handle(): int
    {
        $limit = now()->subDays(OrderPhoto::RETENTION_DAYS);
        $count = 0;

        OrderPhoto::whereHas('order', fn (Builder $order) => $order
            ->where(fn (Builder $q) => $q->where('status', OrderStatus::Recuperee->value)->where('recovered_at', '<=', $limit))
            ->orWhere(fn (Builder $q) => $q->where('status', OrderStatus::Annulee->value)->where('updated_at', '<=', $limit)))
            ->chunkById(200, function ($photos) use (&$count) {
                $photos->each->delete();
                $count += $photos->count();
            });

        $this->info("{$count} photo(s) supprimée(s).");

        return self::SUCCESS;
    }
}
