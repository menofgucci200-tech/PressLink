<?php

namespace App\Actions\Orders;

use App\Enums\ActivityAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
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
    public function handle(Order $order, OrderStatus $target, ?PaymentMethod $settleBalanceWith = null, bool $waiveStorageFee = false, bool $offline = false, ?CarbonInterface $performedAt = null): Order
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
        // (Comptoir hors connexion : la remise a déjà eu lieu.)
        if ($target === OrderStatus::Recuperee && ! $offline) {
            $order->pressing->ensureAcceptsCustomers();
        }

        if ($waiveStorageFee && $user !== null && ! $user->may(Permission::StorageFeeWaive, $order->pressing)) {
            throw new RuntimeException('Votre rôle ne permet pas d\'exonérer du droit de magasinage.');
        }

        return DB::transaction(function () use ($order, $target, $settleBalanceWith, $waiveStorageFee, $performedAt) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Remise d'une commande restée trop longtemps : le droit de
            // magasinage s'ajoute à la commande (sauf exonération, tracée).
            if ($target === OrderStatus::Recuperee && ($fee = $locked->storageFee($performedAt ? Carbon::instance($performedAt) : null)) !== null) {
                $daysLabel = $fee['days'].' jour'.($fee['days'] > 1 ? 's' : '').' de retard';
                $amount = number_format($fee['amount'], 0, ',', ' ').' F';

                if ($waiveStorageFee) {
                    ActivityLog::record($locked->pressing_id, ActivityAction::StorageFeeWaived,
                        "Droit de magasinage de {$amount} offert sur {$locked->order_number} ({$daysLabel})", $locked, ['amount_fcfa' => $fee['amount']]);
                } else {
                    $locked->items()->create([
                        'service_id' => null,
                        'name' => Order::STORAGE_FEE_LINE." ({$daysLabel})",
                        'unit_price_fcfa' => $fee['amount'],
                        'quantity' => 1,
                    ]);
                    $locked->recalculateTotal();
                    $locked->refresh();
                    ActivityLog::record($locked->pressing_id, ActivityAction::StorageFeeCharged,
                        "Droit de magasinage de {$amount} ajouté à {$locked->order_number} ({$daysLabel})", $locked, ['amount_fcfa' => $fee['amount'], 'days' => $fee['days']]);
                }
            }

            if ($settleBalanceWith !== null && $locked->balance_due_fcfa > 0) {
                $locked->recordPayment($locked->balance_due_fcfa, $settleBalanceWith, Auth::guard('web')->id(), 'Solde au retrait', $performedAt);
                $locked->refresh();
            }

            if ($performedAt !== null && $target === OrderStatus::Recuperee) {
                $locked->recovered_at = $performedAt;
            }

            $locked->update(['status' => $target]);

            // Fait hors connexion : l'historique garde l'heure réelle.
            if ($performedAt !== null) {
                $locked->statusHistory()->where('status', $target->value)->latest('id')->first()?->forceFill(['created_at' => $performedAt])->save();
            }

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
