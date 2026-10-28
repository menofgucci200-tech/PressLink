<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * API marchand GeniusPay : création d'un paiement (page de paiement hébergée
 * par GeniusPay : Wave, Orange Money, MTN, carte), lecture d'un paiement et
 * vérification de la signature des notifications (webhooks).
 */
class GeniusPay
{
    /** Montant minimum accepté par GeniusPay (F CFA). */
    public const MIN_AMOUNT = 200;

    /** Clés API renseignées : le paiement en ligne est proposé aux pressings. */
    public static function isConfigured(): bool
    {
        return filled(config('services.geniuspay.api_key')) && filled(config('services.geniuspay.api_secret'));
    }

    /** « sandbox » ou « live » : l'environnement attendu dans les réponses et notifications. */
    public static function environment(): string
    {
        return config('services.geniuspay.sandbox') ? 'sandbox' : 'live';
    }

    /**
     * Crée un paiement et renvoie la transaction (reference, checkout_url, status…).
     * `external_reference` sert aussi de clé d'idempotence chez GeniusPay.
     *
     * @param  array{amount: int, description: string, external_reference: string, success_url: string, error_url: string, customer?: array<string, string>, metadata?: array<string, mixed>}  $payment
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public static function createPayment(array $payment): array
    {
        $response = self::client()->post('/payments', $payment + ['currency' => 'XOF']);
        $data = $response->json('data');

        if (! $response->successful() || ! is_array($data) || blank($data['reference'] ?? null) || blank($data['checkout_url'] ?? null)) {
            throw new RuntimeException('GeniusPay : création du paiement impossible ('.$response->status().' '.($response->json('error.code') ?? $response->json('message') ?? '').').');
        }

        return $data;
    }

    /**
     * Transaction telle que GeniusPay la connaît (statut, montant, moyen de paiement).
     *
     * @return array<string, mixed>|null null si introuvable ou injoignable
     */
    public static function findPayment(string $reference): ?array
    {
        $response = self::client()->get('/payments/'.rawurlencode($reference));
        $data = $response->json('data');

        return $response->successful() && is_array($data) ? $data : null;
    }

    /** Signature HMAC-SHA256 de « timestamp.corps brut », avec le secret du webhook. */
    public static function hasValidSignature(string $payload, ?string $timestamp, ?string $signature): bool
    {
        $secret = (string) config('services.geniuspay.webhook_secret');

        if ($secret === '' || blank($timestamp) || blank($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$payload, $secret), $signature);
    }

    private static function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.geniuspay.base_url'), '/'))
            ->withHeaders([
                'X-API-Key' => (string) config('services.geniuspay.api_key'),
                'X-API-Secret' => (string) config('services.geniuspay.api_secret'),
            ])
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
