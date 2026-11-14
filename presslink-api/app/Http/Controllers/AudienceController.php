<?php

namespace App\Http\Controllers;

use App\Models\AudienceEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Reçoit les pages vues et les clics envoyés par le site vitrine
 * (resources/js/audience.js). Route d'API : pas de session ni de cookie
 * créés pour le visiteur, et les pages gardées en cache restent comptées.
 */
class AudienceController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.AudienceEvent::PAGE.','.AudienceEvent::CLICK],
            'path' => ['required', 'string', 'max:255', 'starts_with:/'],
            'label' => ['nullable', 'string', 'max:500'],
            'target' => ['nullable', 'string', 'max:500'],
            'referrer' => ['nullable', 'string', 'max:1000'],
            'campaign' => ['nullable', 'string', 'max:200'],
        ]);

        $userAgent = $request->userAgent();
        if (AudienceEvent::isBot($userAgent)) {
            return response()->noContent();
        }

        $isPage = $data['type'] === AudienceEvent::PAGE;
        $referrerHost = parse_url((string) ($data['referrer'] ?? ''), PHP_URL_HOST) ?: null;
        // Arrivée sur le site : aucune page précédente, ou une page d'un autre site.
        $isEntry = $isPage && ($referrerHost === null || strcasecmp($referrerHost, $request->getHost()) !== 0);

        AudienceEvent::create([
            'type' => $data['type'],
            'path' => strtok($data['path'], '?#') ?: '/',
            'label' => $isPage ? null : $this->clean($data['label'] ?? null, 120),
            'target' => $isPage ? null : $this->clean($data['target'] ?? null, 255),
            'source' => $isEntry ? AudienceEvent::sourceFor($referrerHost, $data['campaign'] ?? null) : null,
            'is_entry' => $isEntry,
            'device' => AudienceEvent::deviceFor($userAgent),
            'visitor' => AudienceEvent::visitorFingerprint($request->ip(), $userAgent),
            'occurred_at' => now(),
        ]);

        return response()->noContent();
    }

    private function clean(?string $text, int $max): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
