<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
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
        if ($target === OrderStatus::Annulee) {
            throw new RuntimeException('Pour annuler une commande, ouvrez sa fiche et indiquez le motif.');
        }

        // Accès du rôle : remise et encaissement d'un côté, atelier de l'autre.
        $user = Auth::guard('web')->user();
        if ($user !== null && ! $user->is_super_admin) {
            $needed = $target === OrderStatus::Recuperee ? Permission::PaymentsRecord : Permission::OrdersWorkshop;
            if (! $user->may($needed, $order->pressing)) {
                throw new RuntimeException($target === OrderStatus::Recuperee
                    ? 'Votre rôle ne permet pas de remettre une commande au client.'
                    : 'Votre rôle ne permet pas de changer le statut d\'une commande.');
            }
        }

        // Remise au client : seulement pendant les heures d'ouverture. Le
        // travail d'atelier (en traitement, prête, en attente) reste possible.
        if ($target === OrderStatus::Recuperee) {
            $order->pressing->ensureAcceptsCustomers();
        }

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
