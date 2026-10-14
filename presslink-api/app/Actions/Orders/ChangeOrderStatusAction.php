<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Changement de statut d'une commande, depuis la fiche ou le panneau
 * d'aperçu de la liste.
 *
 * Verrou pessimiste (SELECT ... FOR UPDATE) : deux changements de statut
 * concurrents sur la même commande sérialisent sur ce verrou au lieu de
 * valider chacun contre son propre statut de départ en mémoire, ce qui
 * évitait un "lost update" (les deux transitions acceptées, l'historique
 * incohérent avec le statut final — voir load-testing/RAPPORT.md, finding D).
 *
 * Avec `$settleBalanceWith`, le reste à payer est encaissé dans la même
 * transaction (remise au client avec paiement du solde) : le paiement et le
 * changement de statut réussissent ou échouent ensemble.
 */
class ChangeOrderStatusAction
{
    /**
     * @throws RuntimeException transition interdite (voir OrderObserver)
     */
    public function handle(Order $order, OrderStatus $target, ?PaymentMethod $settleBalanceWith = null): Order
    {
        return DB::transaction(function () use ($order, $target, $settleBalanceWith) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($settleBalanceWith !== null && $locked->balance_due_fcfa > 0) {
                $locked->recordPayment($locked->balance_due_fcfa, $settleBalanceWith, Auth::guard('web')->id(), 'Solde au retrait');
                $locked->refresh();
            }

            $locked->update(['status' => $target]);

            return $locked;
        });
    }

    /** Message de confirmation affiché après le changement de statut. */
    public static function successMessage(OrderStatus $target): string
    {
        return match ($target) {
            OrderStatus::Prete => 'Commande prête : le client a été prévenu dans l\'application.',
            OrderStatus::Recuperee => 'Commande récupérée. Merci, l\'historique est à jour.',
            OrderStatus::Annulee => 'Commande annulée.',
            default => 'Commande passée au statut « '.$target->label().' ».',
        };
    }
}
