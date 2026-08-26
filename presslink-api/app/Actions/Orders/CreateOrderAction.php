<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PressingStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Pressing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Création d'une commande — applique :
 * RB-03 (1 pressing + 1 client), RB-04 (≥ 1 article), RB-09 (quota
 * d'abonnement respecté avant toute création), et le blocage d'un
 * pressing suspendu par le Super Admin.
 */
class CreateOrderAction
{
    /**
     * @param  list<array{service_id: ?int, service_variant_id: ?int, name: string, color: ?string, unit_price_fcfa: int, quantity: int}>  $items
     */
    public function handle(
        Pressing $pressing,
        Customer $customer,
        array $items,
        ?string $expectedAt = null,
        ?string $notes = null,
    ): Order {
        if ($items === []) {
            throw new InvalidArgumentException('Une commande doit contenir au moins un article.');
        }

        if ($pressing->status !== PressingStatus::Active) {
            throw new RuntimeException('Ce pressing est suspendu : impossible de créer une nouvelle commande.');
        }

        $subscription = $pressing->subscription;

        if ($subscription !== null && ! $subscription->allowsNewOrder()) {
            throw new RuntimeException("L'abonnement de ce pressing ne permet plus de créer de commande (quota atteint ou essai expiré).");
        }

        return DB::transaction(function () use ($pressing, $customer, $items, $expectedAt, $notes, $subscription) {
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

            $subscription?->incrementOrdersUsed();

            return $order->fresh(['items', 'customer', 'pressing']);
        });
    }
}
