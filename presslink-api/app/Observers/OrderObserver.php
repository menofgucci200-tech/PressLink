<?php

namespace App\Observers;

use App\Enums\ActivityAction;
use App\Enums\OrderStatus;
use App\Models\ActivityLog;
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

        $cancellationNote = $order->status === OrderStatus::Annulee
            ? collect([$order->cancellation_reason?->label(), $order->cancellation_note])->filter()->implode(' : ')
            : null;

        $order->statusHistory()->create([
            'status' => $order->status,
            'changed_by' => Auth::guard('web')->id(),
            'note' => $cancellationNote ?: null,
            'created_at' => now(),
        ]);

        // Journal d'activité (contrôle du propriétaire) : annulation et remise
        // au client sans encaissement complet.
        if ($order->status === OrderStatus::Annulee) {
            ActivityLog::record($order->pressing_id, ActivityAction::OrderCancelled,
                "Commande {$order->order_number} annulée".($cancellationNote ? " — {$cancellationNote}" : '').($order->amount_paid_fcfa > 0 ? ' ('.number_format($order->amount_paid_fcfa, 0, ',', ' ').' F payés et conservés)' : ''),
                $order, ['amount_paid_fcfa' => $order->amount_paid_fcfa, 'total_fcfa' => $order->total_fcfa, 'reason' => $order->cancellation_reason?->value]);
        } elseif ($order->status === OrderStatus::Recuperee && $order->balance_due_fcfa > 0) {
            ActivityLog::record($order->pressing_id, ActivityAction::RecoveredUnpaid,
                "Commande {$order->order_number} remise avec ".number_format($order->balance_due_fcfa, 0, ',', ' ').' F restant dus',
                $order, ['balance_due_fcfa' => $order->balance_due_fcfa]);
        }

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
