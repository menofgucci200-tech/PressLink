<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Base commune aux notifications de commande — Cahier §9.
 * MVP : canal push (FcmChannel) + persistance en base pour la liste
 * "Notifications" de l'app client.
 *
 * ShouldQueueAfterCommit (pas juste ShouldQueue) : l'écriture en base et
 * l'appel FCM sont déportés sur un worker de queue au lieu de s'exécuter
 * dans la requête HTTP qui déclenche le changement de statut (voir
 * load-testing/RAPPORT.md, finding A). "AfterCommit" est indispensable ici
 * : ces notifications sont envoyées depuis OrderObserver, DANS la
 * transaction DB de CreateOrderAction — avec un simple ShouldQueue (et
 * config('queue.connections.database.after_commit') = false par défaut),
 * le job serait poussé sur la queue AVANT que la commande ne soit
 * committée, et pourrait référencer une commande qui n'existe pas encore
 * (ou plus, si la transaction est finalement annulée).
 */
abstract class OrderNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable, SerializesModels;

    public function __construct(protected Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    abstract public function title(): string;

    abstract public function body(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'pressing_name' => $this->order->pressing->name,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function toFcm(mixed $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'data' => [
                'order_id' => (string) $this->order->id,
                'order_number' => $this->order->order_number,
            ],
        ];
    }
}
