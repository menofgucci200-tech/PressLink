<?php

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use App\Models\SubscriptionRequest;
use Illuminate\Console\Command;

/**
 * Tentatives de paiement en ligne restées sans réponse au-delà de la durée
 * de vie d'une page de paiement : notées « Expiré » dans l'historique (elles
 * comptent comme des échecs dans le taux de réussite).
 */
class ExpireStalePayments extends Command
{
    protected $signature = 'presslink:expire-payments';

    protected $description = 'Marque « Expiré » les paiements en ligne restés en cours trop longtemps.';

    public function handle(): int
    {
        $count = 0;
        PaymentAttempt::where('status', PaymentAttempt::PENDING)
            ->where('started_at', '<=', now()->subHours(SubscriptionRequest::ONLINE_PAYMENT_HOURS))
            ->each(function (PaymentAttempt $attempt) use (&$count) {
                $attempt->markFailed(PaymentAttempt::EXPIRED, 'Page de paiement expirée sans paiement');
                $count++;
            });

        $this->info("{$count} paiement(s) marqué(s) expiré(s).");

        return self::SUCCESS;
    }
}
