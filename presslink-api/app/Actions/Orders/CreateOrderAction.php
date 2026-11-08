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
use App\Models\ServiceVariant;
use App\Models\Subscription;
use App\Notifications\OrderDetailsCompletedNotification;
use Carbon\CarbonInterface;
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
 *
 * Commande faite sur le comptoir hors connexion (`$offline`) : elle a déjà
 * été promise au client, elle n'est donc refusée ni hors des horaires ni
 * pour un quota atteint (elle compte quand même dans le quota), et garde
 * son numéro et son heure de dépôt quand c'est possible.
 *
 * Dépôt rapide (`quickDeposit`) : pour les grosses commandes, seuls le client
 * et le nombre de pièces sont saisis au comptoir. La commande existe et a son
 * numéro, mais reste « à compléter » jusqu'à `completeQuickDeposit`, seule
 * exception à RB-04 : elle ne peut pas être prête tant qu'elle n'a pas de prix.
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
        bool $offline = false,
        ?string $orderNumber = null,
        ?CarbonInterface $droppedOffAt = null,
    ): Order {
        if ($items === []) {
            throw new InvalidArgumentException('Une commande doit contenir au moins un article.');
        }

        $total = array_sum(array_map(fn (array $item) => $item['unit_price_fcfa'] * $item['quantity'], $items));

        if ($amountPaidFcfa < 0 || $amountPaidFcfa > $total) {
            throw new RuntimeException('Le montant payé doit être compris entre 0 et le total de la commande.');
        }

        $this->ensureCanCreate($pressing, $customer, $offline);

        $this->ensureItemsAreValid($pressing, $items);

        $subscription = $pressing->subscription;
        $this->ensureQuotaAllows($subscription, $offline);

        return DB::transaction(function () use ($pressing, $customer, $items, $expectedAt, $notes, $subscription, $amountPaidFcfa, $paymentMethod, $orderNumber, $droppedOffAt) {
            $order = Order::create([
                'order_number' => $orderNumber,
                'dropped_off_at' => $droppedOffAt,
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
                $order->recordPayment($amountPaidFcfa, $paymentMethod, Auth::guard('web')->id(), 'Payé au dépôt', $droppedOffAt);
            }

            $subscription?->incrementOrdersUsed();

            return $order->fresh(['items', 'customer', 'pressing', 'payments']);
        });
    }

    /**
     * Dépôt rapide : le client, le nombre de pièces et un numéro tout de suite ;
     * le détail et les prix seront saisis plus tard. Compte dans le quota, comme
     * toute commande.
     */
    public function quickDeposit(Pressing $pressing, Customer $customer, int $declaredPieces, ?string $expectedAt = null, ?string $notes = null): Order
    {
        if ($declaredPieces < 1) {
            throw new InvalidArgumentException('Un dépôt doit compter au moins une pièce.');
        }

        $this->ensureCanCreate($pressing, $customer);
        $subscription = $pressing->subscription;
        $this->ensureQuotaAllows($subscription);

        return DB::transaction(function () use ($pressing, $customer, $declaredPieces, $expectedAt, $notes, $subscription) {
            $order = Order::create([
                'pressing_id' => $pressing->id,
                'customer_id' => $customer->id,
                'status' => OrderStatus::Recue,
                'details_pending' => true,
                'declared_pieces' => $declaredPieces,
                'expected_at' => $expectedAt,
                'notes' => $notes,
                'created_by' => Auth::guard('web')->id(),
            ]);

            ActivityLog::record($pressing->id, ActivityAction::QuickDepositCreated,
                "Dépôt rapide {$order->order_number} pour {$customer->fullName()} · {$declaredPieces} pièce".($declaredPieces > 1 ? 's' : '').' comptée'.($declaredPieces > 1 ? 's' : ''),
                $order, ['customer_id' => $customer->id, 'declared_pieces' => $declaredPieces]);

            $subscription?->incrementOrdersUsed();

            return $order->fresh(['items', 'customer', 'pressing', 'payments']);
        });
    }

    /**
     * Saisit le détail et les prix d'un dépôt rapide : la commande devient une
     * commande normale. Un écart avec le nombre compté au dépôt ne bloque pas
     * (on a pu se tromper au comptoir) mais reste inscrit au journal. Verrou :
     * deux collègues ne peuvent pas compléter le même dépôt en même temps.
     *
     * @param  list<array{service_id: ?int, service_variant_id?: ?int, prestation_id?: ?int, prestation_name?: ?string, name: string, color?: ?string, unit_price_fcfa: int, quantity: int}>  $items
     */
    public function completeQuickDeposit(
        Order $order,
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

        $staff = Auth::guard('web')->user();
        if ($staff !== null && ! $staff->is_super_admin && ! $staff->may(Permission::OrdersCreate, $order->pressing)) {
            throw new RuntimeException('Votre rôle ne permet pas de compléter une commande.');
        }

        $this->ensureItemsAreValid($order->pressing, $items);

        $completed = DB::transaction(function () use ($order, $items, $expectedAt, $notes, $amountPaidFcfa, $paymentMethod) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->awaitsDetails()) {
                throw new RuntimeException("Le dépôt {$locked->order_number} n'est plus à compléter.");
            }

            foreach ($items as $item) {
                $locked->items()->create($item);
            }

            $locked->details_pending = false;
            $locked->expected_at = $expectedAt;
            $locked->notes = $notes;
            $locked->recalculateTotal();

            $pieces = (int) array_sum(array_column($items, 'quantity'));
            $declared = (int) $locked->declared_pieces;
            ActivityLog::record($locked->pressing_id, ActivityAction::QuickDepositCompleted,
                "Dépôt {$locked->order_number} complété · {$pieces} pièce".($pieces > 1 ? 's' : '').' · '.number_format((int) $locked->total_fcfa, 0, ',', ' ').' F'
                    .($pieces !== $declared ? " · {$declared} comptée".($declared > 1 ? 's' : '').' au dépôt' : ''),
                $locked, ['pieces' => $pieces, 'declared_pieces' => $declared, 'total_fcfa' => (int) $locked->total_fcfa]);

            if ($amountPaidFcfa > 0) {
                $locked->recordPayment($amountPaidFcfa, $paymentMethod, Auth::guard('web')->id(), 'Payé au complément du dépôt');
            }

            return $locked->fresh(['items', 'customer', 'pressing', 'payments']);
        });

        $completed->customer->notify(new OrderDetailsCompletedNotification($completed));

        return $completed;
    }

    /** Le pressing peut recevoir ce client maintenant, et l'employé a le droit de créer. */
    private function ensureCanCreate(Pressing $pressing, Customer $customer, bool $offline = false): void
    {
        if ($pressing->status !== PressingStatus::Active) {
            throw new RuntimeException('Ce pressing est suspendu : impossible de créer une commande.');
        }

        if (! $offline) {
            $pressing->ensureAcceptsCustomers();
        }

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
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function ensureItemsAreValid(Pressing $pressing, array $items): void
    {
        // Pièces et services d'une ligne : toujours ceux de CE pressing (une
        // requête forgée ne doit pas piocher dans le catalogue d'un autre).
        $pieceIds = array_values(array_unique(array_filter(array_column($items, 'service_id'))));
        $prestationIds = array_values(array_unique(array_filter(array_column($items, 'prestation_id'))));

        if (($pieceIds !== [] && $pressing->services()->whereIn('id', $pieceIds)->count() !== count($pieceIds))
            || ($prestationIds !== [] && $pressing->prestations()->whereIn('id', $prestationIds)->count() !== count($prestationIds))) {
            throw new InvalidArgumentException('Une pièce ou un service de la commande n\'appartient pas à ce pressing.');
        }

        // Lignes revérifiées ici (elles peuvent venir du navigateur) : quantité
        // d'au moins 1, prix positif, variante qui appartient bien à sa pièce.
        foreach ($items as $item) {
            if ((int) ($item['quantity'] ?? 0) < 1 || (int) ($item['unit_price_fcfa'] ?? -1) < 0) {
                throw new InvalidArgumentException('Une ligne de la commande a une quantité ou un prix invalide.');
            }
            if (! empty($item['service_variant_id'])
                && ! ServiceVariant::whereKey($item['service_variant_id'])->where('service_id', $item['service_id'] ?? 0)->exists()) {
                throw new InvalidArgumentException('Une variante de la commande ne correspond pas à sa pièce.');
            }
        }
    }

    private function ensureQuotaAllows(?Subscription $subscription, bool $offline = false): void
    {
        if (! $offline && $subscription !== null && ! $subscription->allowsNewOrder()) {
            throw new RuntimeException($subscription->hasReachedOrdersLimit()
                ? 'Toutes les commandes de ce mois sont utilisées. Ajoutez-en depuis la page Abonnement, ou attendez le 1er du mois.'
                : "L'abonnement de ce pressing ne permet plus de créer de commande (essai terminé ou abonnement expiré).");
        }
    }
}
