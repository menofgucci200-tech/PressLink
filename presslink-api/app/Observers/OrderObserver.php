<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderReadyNotification;
use App\Notifications\OrderRecoveredNotification;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * RB-05 : chaque changement de statut est historisé.
 * RB-07 : seule une commande PRÊTE peut être marquée RÉCUPÉRÉE — plus
 * largement, seules les transitions définies par OrderStatus sont admises.
 * RB-06 : une notification est déclenchée automatiquement lors des
 * événements configurés — Cahier §9 (créée / prête / récupérée au MVP).
 */
class OrderObserver
{
    public function created(Order $order): void
    {
        $order->statusHistory()->create([
            'status' => $order->status,
            'changed_by' => Auth::guard('web')->id(),
            'created_at' => now(),
        ]);

        $order->customer->notify(new OrderCreatedNotification($order));
    }

    public function updating(Order $order): void
    {
        if (! $order->isDirty('status')) {
            return;
        }

        $from = $this->toStatus($order->getOriginal('status'));
        $to = $this->toStatus($order->status);

        if (! $from->canTransitionTo($to)) {
            throw new RuntimeException("Transition de statut invalide : {$from->label()} → {$to->label()}.");
        }

        if ($to === OrderStatus::Recuperee) {
            $order->recovered_at ??= now();
            $order->recovered_by ??= Auth::guard('web')->id();
        }
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $order->statusHistory()->create([
            'status' => $order->status,
            'changed_by' => Auth::guard('web')->id(),
            'created_at' => now(),
        ]);

        match ($order->status) {
            OrderStatus::Prete => $order->customer->notify(new OrderReadyNotification($order)),
            OrderStatus::Recuperee => $order->customer->notify(new OrderRecoveredNotification($order)),
            default => null,
        };
    }

    private function toStatus(OrderStatus|string $status): OrderStatus
    {
        return $status instanceof OrderStatus ? $status : OrderStatus::from($status);
    }
}
