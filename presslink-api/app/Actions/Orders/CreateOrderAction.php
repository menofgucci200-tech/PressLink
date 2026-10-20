<?php

namespace App\Actions\Orders;

use App\Enums\ActivityAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\PressingStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Création d'une commande — applique :
 * RB-03 (1 pressing + 1 client, et le client doit appartenir au
 * pressing), RB-04 (≥ 1 article), RB-09 (quota d'abonnement respecté
 * avant toute création), et refuse toute création pour un pressing
 * suspendu par le Super Admin (voir load-testing/RAPPORT.md, finding B)
 * ou pour un client dont le compte a été désactivé depuis la console.
 * Le montant déjà payé au dépôt (tout ou acompte) est enregistré comme un
 * premier paiement ; le reste se règle au retrait.
 */
class CreateOrderAction
{
    public const INACTIVE_CUSTOMER_MESSAGE = 'Le compte de ce client a été désactivé par PressLink : impossible de lui créer une commande.';

    /**
     * @param  list<array{service_id: ?int, service_variant_id?: ?int, prestation_id?: ?int, prestation_name?: ?string, name: string, color?: ?string, unit_price_fcfa: int, quantity: int}>  $items
     */
    public function handle(
        Pressing $pressing,
        Customer $customer,
        array $items,
        ?string $expectedAt = null,
        ?string $notes = null,
        int $amountPaidFcfa = 0,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
    ): Order {
        if ($items === []) {
            throw new InvalidArgumentException('Une commande doit contenir au moins un article.');
        }

        $total = array_sum(array_map(fn (array $item) => $item['unit_price_fcfa'] * $item['quantity'], $items));

        if ($amountPaidFcfa < 0 || $amountPaidFcfa > $total) {
            throw new RuntimeException('Le montant payé doit être compris entre 0 et le total de la commande.');
        }

        if ($pressing->status !== PressingStatus::Active) {
            throw new RuntimeException('Ce pressing est suspendu : impossible de créer une commande.');
        }

        $pressing->ensureAcceptsCustomers();

        $staff = Auth::guard('web')->user();
        if ($staff !== null && ! $staff->is_super_admin && ! $staff->may(Permission::OrdersCreate, $pressing)) {
            throw new RuntimeException('Votre rôle ne permet pas de créer une commande.');
        }

        if ($customer->is_active === false) {
            throw new RuntimeException(self::INACTIVE_CUSTOMER_MESSAGE);
        }

        if (! $pressing->customers()->where('customers.id', $customer->id)->exists()) {
            throw new InvalidArgumentException('Ce client ne fait pas partie de ce pressing.');
        }

        // Pièces et services d'une ligne : toujours ceux de CE pressing (une
        // requête forgée ne doit pas piocher dans le catalogue d'un autre).
        $pieceIds = array_values(array_unique(array_filter(array_column($items, 'service_id'))));
        $prestationIds = array_values(array_unique(array_filter(array_column($items, 'prestation_id'))));

        if (($pieceIds !== [] && $pressing->services()->whereIn('id', $pieceIds)->count() !== count($pieceIds))
            || ($prestationIds !== [] && $pressing->prestations()->whereIn('id', $prestationIds)->count() !== count($prestationIds))) {
            throw new InvalidArgumentException('Une pièce ou un service de la commande n\'appartient pas à ce pressing.');
        }

        $subscription = $pressing->subscription;

        if ($subscription !== null && ! $subscription->allowsNewOrder()) {
            throw new RuntimeException("L'abonnement de ce pressing ne permet plus de créer de commande (quota atteint ou essai expiré).");
        }

        return DB::transaction(function () use ($pressing, $customer, $items, $expectedAt, $notes, $subscription, $amountPaidFcfa, $paymentMethod) {
            $order = Order::create([
                'pressing_id' => $pressing->id,
                'customer_id' => $customer->id,
                'status' => OrderStatus::Recue,
                'expected_at' => $expectedAt,
                'notes' => $notes,
                'created_by' => Auth::guard('web')->id(),
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            $order->recalculateTotal();

            $pieces = (int) array_sum(array_column($items, 'quantity'));
            ActivityLog::record($pressing->id, ActivityAction::OrderCreated,
                "Commande {$order->order_number} créée pour {$customer->fullName()} · {$pieces} pièce".($pieces > 1 ? 's' : '').' · '.number_format((int) $order->total_fcfa, 0, ',', ' ').' F',
                $order, ['customer_id' => $customer->id, 'pieces' => $pieces, 'total_fcfa' => (int) $order->total_fcfa]);

            if ($amountPaidFcfa > 0) {
                $order->recordPayment($amountPaidFcfa, $paymentMethod, Auth::guard('web')->id(), 'Payé au dépôt');
            }

            $subscription?->incrementOrdersUsed();

            return $order->fresh(['items', 'customer', 'pressing', 'payments']);
        });
    }
}
