<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

/**
 * Canal de notification push — Cahier §9, branché sur Firebase Cloud Messaging.
 *
 * Si le client n'a pas encore de token FCM enregistré (app pas encore lancée
 * depuis le branchement Firebase, ou permission refusée) — ou si Firebase
 * n'est pas configuré dans cet environnement (dev/CI) — le message est
 * simplement loggué au lieu d'échouer. `Messaging` est résolu à la volée
 * plutôt qu'injecté au constructeur pour ne jamais faire échouer la
 * création d'une commande si les identifiants Firebase sont absents.
 */
class FcmChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $payload = $notification->toFcm($notifiable);
        $token = method_exists($notifiable, 'routeNotificationForFcm')
            ? $notifiable->routeNotificationForFcm($notification)
            : null;

        if (! $token) {
            Log::info('FCM (mock) PressLink — aucun token enregistré', [
                'notifiable' => $notifiable::class.'#'.$notifiable->getKey(),
                'title' => $payload['title'] ?? null,
                'body' => $payload['body'] ?? null,
                'data' => $payload['data'] ?? [],
            ]);

            return;
        }

        try {
            $messaging = app(Messaging::class);
        } catch (Throwable $e) {
            Log::warning('FCM non configuré dans cet environnement — notification ignorée', [
                'notifiable' => $notifiable::class.'#'.$notifiable->getKey(),
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $message = CloudMessage::new()
            ->withToken($token)
            ->withNotification(FcmNotification::create($payload['title'] ?? '', $payload['body'] ?? ''))
            ->withData(array_map('strval', $payload['data'] ?? []));

        try {
            $messaging->send($message);
        } catch (NotFound) {
            // Token expiré/désinstallé côté device : on nettoie pour éviter de réessayer.
            if (method_exists($notifiable, 'update')) {
                $notifiable->update(['fcm_token' => null]);
            }
        }
    }
}
