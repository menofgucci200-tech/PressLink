<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Canal de notification push — Cahier §9, branché sur OneSignal (remplace
 * FcmChannel/Firebase Cloud Messaging).
 *
 * Si le client n'a pas encore de "player ID" OneSignal enregistré (app pas
 * encore lancée depuis l'intégration, ou permission refusée) — ou si
 * OneSignal n'est pas configuré dans cet environnement (dev/CI) — le
 * message est simplement loggué au lieu d'échouer. Un appel HTTP en échec
 * ne doit jamais faire remonter d'exception jusqu'à l'appelant : la queue
 * ne doit pas boucler indéfiniment sur une notification push ratée.
 */
class OneSignalChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toOneSignal')) {
            return;
        }

        $payload = $notification->toOneSignal($notifiable);
        $playerId = method_exists($notifiable, 'routeNotificationForOneSignal')
            ? $notifiable->routeNotificationForOneSignal($notification)
            : null;

        if (! $playerId) {
            Log::info('OneSignal (mock) PressLink — aucun player ID enregistré', [
                'notifiable' => $notifiable::class.'#'.$notifiable->getKey(),
                'title' => $payload['title'] ?? null,
                'body' => $payload['body'] ?? null,
                'data' => $payload['data'] ?? [],
            ]);

            return;
        }

        $appId = config('services.onesignal.app_id');
        $restApiKey = config('services.onesignal.rest_api_key');

        if (! $appId || ! $restApiKey) {
            Log::warning('OneSignal non configuré dans cet environnement — notification ignorée', [
                'notifiable' => $notifiable::class.'#'.$notifiable->getKey(),
            ]);

            return;
        }

        $androidChannelId = config('services.onesignal.android_channel_id');

        $response = Http::withHeaders([
            'Authorization' => "Basic {$restApiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', [
            'app_id' => $appId,
            'include_player_ids' => [$playerId],
            'headings' => ['en' => $payload['title'] ?? ''],
            'contents' => ['en' => $payload['body'] ?? ''],
            'data' => $payload['data'] ?? [],
            ...($androidChannelId ? ['android_channel_id' => $androidChannelId] : []),
        ]);

        if ($response->failed()) {
            Log::warning('Échec de l\'envoi OneSignal', [
                'notifiable' => $notifiable::class.'#'.$notifiable->getKey(),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Player ID probablement expiré/désinscrit côté device : on
            // nettoie pour éviter de réessayer indéfiniment sur un
            // identifiant mort (OneSignal renvoie un tableau "errors").
            $errors = $response->json('errors');
            if (is_array($errors) && method_exists($notifiable, 'update')) {
                $notifiable->update(['onesignal_player_id' => null]);
            }
        }
    }
}
