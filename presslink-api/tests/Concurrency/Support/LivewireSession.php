<?php

namespace Tests\Concurrency\Support;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client Livewire "maison" pour les tests de concurrence — équivalent PHP
 * de load-testing/k6/lib/livewire.js (même protocole, vérifié manuellement
 * par curl avant d'être encodé ici : GET la page pour son wire:snapshot +
 * préfixe /livewire-xxxx/update, puis POST components:[{snapshot, updates,
 * calls}] avec le header X-XSRF-TOKEN dérivé du cookie).
 *
 * Chaque instance a son propre cookie jar Guzzle — indispensable pour
 * simuler plusieurs utilisateurs distincts en parallèle avec Http::pool().
 */
class LivewireSession
{
    private CookieJar $jar;

    private ?string $updatePath = null;

    public function __construct(private readonly string $baseUrl)
    {
        $this->jar = new CookieJar;
    }

    public function client(): PendingRequest
    {
        return Http::withOptions(['cookies' => $this->jar])->withHeaders(['Accept' => 'text/html']);
    }

    public function get(string $path): Response
    {
        return $this->client()->get($this->baseUrl.$path);
    }

    /**
     * @return array{res: Response, snapshot: ?string, updatePath: string}
     */
    public function visit(string $path): array
    {
        $res = $this->get($path);
        $this->updatePath = self::extractUpdatePath($res->body()) ?? $this->updatePath ?? '/livewire/update';

        return [
            'res' => $res,
            'snapshot' => self::extractSnapshot($res->body()),
            'updatePath' => $this->updatePath,
        ];
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  list<array{path?: string, method: string, params?: array}>  $calls
     * @return array{res: Response, snapshot: ?string, effects: array}
     */
    public function call(string $snapshot, array $updates = [], array $calls = []): array
    {
        $csrf = $this->csrfToken();

        $res = Http::withOptions(['cookies' => $this->jar])
            ->withHeaders([
                'X-Livewire' => 'true',
                'X-XSRF-TOKEN' => $csrf ?? '',
                'Accept' => 'application/json',
            ])
            ->post($this->baseUrl.$this->updatePath, [
                '_token' => '',
                'components' => [[
                    'snapshot' => $snapshot,
                    'updates' => (object) $updates,
                    'calls' => $calls,
                ]],
            ]);

        $component = $res->json('components.0') ?? [];

        return [
            'res' => $res,
            'snapshot' => $component['snapshot'] ?? null,
            'effects' => $component['effects'] ?? [],
        ];
    }

    public function loginAsStaff(string $login, string $password): bool
    {
        $page = $this->visit('/login');

        if ($page['snapshot'] === null) {
            return false;
        }

        $result = $this->call($page['snapshot'], [
            'login' => $login,
            'password' => $password,
        ], [
            ['path' => '', 'method' => 'authenticate', 'params' => []],
        ]);

        return isset($result['effects']['redirect']);
    }

    /**
     * Requête préparée (headers + cookies de CETTE session) mais pas
     * encore envoyée — à utiliser dans Http::pool(fn ($pool) => [...])
     * pour de vraies requêtes concurrentes : chaque appelant doit ensuite
     * appeler ->post($session->updateUrl(), $session->updatePayload(...))
     * dessus.
     */
    public function pooledClient(Pool $pool): PendingRequest
    {
        $csrf = $this->csrfToken();

        return $pool->withOptions(['cookies' => $this->jar])
            ->withHeaders([
                'X-Livewire' => 'true',
                'X-XSRF-TOKEN' => $csrf ?? '',
                'Accept' => 'application/json',
            ])
            ->asJson();
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  list<array{path?: string, method: string, params?: array}>  $calls
     * @return array{_token: string, components: list<array>}
     */
    public function updatePayload(string $snapshot, array $updates = [], array $calls = []): array
    {
        return [
            '_token' => '',
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => (object) $updates,
                'calls' => $calls,
            ]],
        ];
    }

    public function updateUrl(): string
    {
        return $this->baseUrl.$this->updatePath;
    }

    public static function extractComponent(Response $response): array
    {
        return $response->json('components.0') ?? [];
    }

    private function csrfToken(): ?string
    {
        foreach ($this->jar->toArray() as $cookie) {
            if ($cookie['Name'] === 'XSRF-TOKEN') {
                return urldecode($cookie['Value']);
            }
        }

        return null;
    }

    private static function extractUpdatePath(string $html): ?string
    {
        if (preg_match('/livewire-([0-9a-f]+)\//', $html, $m) === 1) {
            return "/livewire-{$m[1]}/update";
        }

        return null;
    }

    private static function extractSnapshot(string $html): ?string
    {
        if (preg_match('/wire:snapshot="([^"]*)"/', $html, $m) === 1) {
            return html_entity_decode($m[1]);
        }

        return null;
    }
}
