<?php

namespace App\Notifications;

use App\Models\PickupRequest;
use App\Notifications\Channels\OneSignalChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Suivi d'une demande de ramassage dans l'appli client : push et liste
 * « Notifications » (même fonctionnement que les notifications de commande).
 */
abstract class PickupRequestNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable, SerializesModels;

    public function __construct(protected PickupRequest $pickupRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        if ($this->pickupRequest->pressing?->isSandbox()) {
            return [];
        }

        return ['database', OneSignalChannel::class];
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
            'pickup_request_id' => $this->pickupRequest->id,
            'pressing_name' => $this->pickupRequest->pressing->name,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function toOneSignal(mixed $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'data' => ['pickup_request_id' => (string) $this->pickupRequest->id],
        ];
    }
}
