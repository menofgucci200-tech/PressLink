<?php

namespace App\Actions\Orders;

use App\Enums\CancellationReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Annulation d'une commande, toujours avec un motif :
 *  - « Reçue » ou « En attente » : tout le personnel du pressing ;
 *  - « En traitement » (le travail a commencé) : l'administrateur seulement ;
 *  - « Prête », « Récupérée » : plus d'annulation possible.
 * Si le client a déjà payé, on indique ce que devient l'argent : rendu
 * (remboursement enregistré comme une sortie de caisse) ou conservé.
 */
class CancelOrderAction
{
    public const REFUND = 'refund';

    public const KEEP = 'keep';

    /** Statuts depuis lesquels un employé peut annuler. */
    private const EMPLOYEE_CANCELLABLE = [OrderStatus::Recue, OrderStatus::Attente];

    public static function canCancel(User $user, Order $order): bool
    {
        return $order->status->canTransitionTo(OrderStatus::Annulee)
            && $user->belongsToPressing($order->pressing)
            && (in_array($order->status, self::EMPLOYEE_CANCELLABLE, true) || $user->isAdminOf($order->pressing));
    }

    /**
     * @throws RuntimeException annulation refusée (statut, droits, motif ou argent versé)
     */
    public function handle(Order $order, User $user, CancellationReason $reason, ?string $note = null, ?string $moneyChoice = null, ?PaymentMethod $refundMethod = null): Order
    {
        $note = trim((string) $note) !== '' ? mb_substr(trim((string) $note), 0, 500) : null;

        if ($reason === CancellationReason::Other && $note === null) {
            throw new RuntimeException('Précisez le motif de l\'annulation.');
        }

        $order->pressing->ensureAcceptsCustomers();

        return DB::transaction(function () use ($order, $user, $reason, $note, $moneyChoice, $refundMethod) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo(OrderStatus::Annulee)) {
                throw new RuntimeException($locked->status === OrderStatus::Prete
                    ? 'Une commande prête ne peut plus être annulée : le travail est fait.'
                    : 'Cette commande ne peut plus être annulée.');
            }

            if (! self::canCancel($user, $locked)) {
                throw new RuntimeException('Le travail a commencé : seul l\'administrateur du pressing peut annuler cette commande.');
            }

            $paid = (int) $locked->amount_paid_fcfa;
            if ($paid > 0) {
                if (! in_array($moneyChoice, [self::REFUND, self::KEEP], true)) {
                    throw new RuntimeException('Le client a déjà payé '.number_format($paid, 0, ',', ' ').' F : indiquez si cet argent est rendu ou conservé.');
                }

                if ($moneyChoice === self::REFUND) {
                    $locked->recordPayment(-$paid, $refundMethod ?? PaymentMethod::Cash, $user->id, 'Remboursement : commande annulée');
                    $locked->refresh();
                }
            }

            $locked->update([
                'status' => OrderStatus::Annulee,
                'cancellation_reason' => $reason,
                'cancellation_note' => $note,
            ]);

            return $locked;
        });
    }
}
