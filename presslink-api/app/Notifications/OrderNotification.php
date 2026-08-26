<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Notifications\Notification;

/**
 * Base commune aux notifications de commande — Cahier §9.
 * MVP : canal push (FcmChannel) + persistance en base pour la liste
 * "Notifications" de l'app client.
 */
abstract class OrderNotification extends Notification
{
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
