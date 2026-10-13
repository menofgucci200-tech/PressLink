<?php

namespace App\Notifications\Channels;

use Illuminate\Http\Client\ConnectionException;
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

        // Clés « App API Key » actuelles (os_v2_…) : schéma « Key » ; les
        // anciennes clés REST restent acceptées avec « Basic ».
        $scheme = str_starts_with($restApiKey, 'os_v2_') ? 'Key' : 'Basic';

        try {
            $response = Http::withHeaders([
                'Authorization' => "{$scheme} {$restApiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://api.onesignal.com/notifications', [
                'app_id' => $appId,
                'target_channel' => 'push',
                'include_subscription_ids' => [$playerId],
                'headings' => ['en' => $payload['title'] ?? '', 'fr' => $payload['title'] ?? ''],
                'contents' => ['en' => $payload['body'] ?? '', 'fr' => $payload['body'] ?? ''],
                // PWA : sans URL, un tap ouvrirait la page d'accueil du site.
                'web_url' => url('/app/'),
                'data' => $payload['data'] ?? [],
                ...($androidChannelId ? ['android_channel_id' => $androidChannelId] : []),
            ]);
        } catch (ConnectionException $e) {
            Log::warning('OneSignal injoignable', ['error' => $e->getMessage()]);

            return;
        }

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
