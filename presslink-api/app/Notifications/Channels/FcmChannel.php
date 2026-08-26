<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Canal de notification push — Cahier §9 (MVP : push uniquement).
 *
 * Tant qu'aucun projet Firebase réel n'est configuré (FCM_SERVER_KEY vide),
 * le message est simplement loggué — même logique que l'OTP mocké en
 * Phase 2. Brancher un vrai token FCM par appareil (table à ajouter côté
 * Customer) et une clé serveur suffira à activer l'envoi réel, sans
 * changer les notifications métier qui appellent ce canal.
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

        $serverKey = config('services.fcm.server_key');

        if (! $serverKey || ! $token) {
            Log::info('FCM (mock) PressLink', [
                'notifiable' => $notifiable::class.'#'.$notifiable->getKey(),
                'token' => $token,
                'title' => $payload['title'] ?? null,
                'body' => $payload['body'] ?? null,
                'data' => $payload['data'] ?? [],
            ]);

            return;
        }

        Http::withHeaders(['Authorization' => "key={$serverKey}"])
            ->asJson()
            ->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => [
                    'title' => $payload['title'] ?? null,
                    'body' => $payload['body'] ?? null,
                ],
                'data' => $payload['data'] ?? [],
            ]);
    }
}
