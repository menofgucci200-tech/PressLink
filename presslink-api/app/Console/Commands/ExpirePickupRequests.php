<?php

namespace App\Console\Commands;

use App\Actions\Pickups\PickupRequests;
use Illuminate\Console\Command;

/**
 * Demandes de ramassage restées sans réponse après la fin de leur créneau :
 * « Expirée », et le client est prévenu. Supprime aussi les photos des
 * demandes terminées depuis plus de 30 jours.
 */
class ExpirePickupRequests extends Command
{
    protected $signature = 'presslink:expire-pickups';

    protected $description = 'Fait expirer les demandes de ramassage sans réponse et supprime les vieilles photos.';

    public function handle(PickupRequests $pickups): int
    {
        $expired = $pickups->expireOverdue();
        $photos = $pickups->purgeOldPhotos();

        $this->info("{$expired} demande(s) expirée(s), {$photos} photo(s) supprimée(s).");

        return self::SUCCESS;
    }
}
