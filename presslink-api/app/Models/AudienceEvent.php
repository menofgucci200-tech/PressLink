<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Une page vue ou un clic sur le site vitrine (menu Audience de la console).
 * Mesure faite par PressLink lui-même, sans cookie : l'adresse IP n'est pas
 * gardée, seulement une empreinte du jour (IP + navigateur + clé de l'appli)
 * qui permet de compter les visiteurs sans pouvoir les suivre d'un jour à
 * l'autre.
 */
class AudienceEvent extends Model
{
    public const PAGE = 'page';

    public const CLICK = 'click';

    /** Conservation, comme les journaux techniques (politique de confidentialité). */
    public const RETENTION_MONTHS = 12;

    /** Robots, aperçus de liens et navigateurs automatisés : jamais comptés. */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|headless|lighthouse|pingdom|monitor|facebookexternalhit|whatsapp|telegram|curl|wget|python|scrapy|httpclient|okhttp|axios/i';

    /** Sites d'origine reconnus, regroupés sous un nom lisible. */
    private const SOURCES = [
        'google' => 'Google',
        'bing' => 'Bing',
        'yahoo' => 'Yahoo',
        'duckduckgo' => 'DuckDuckGo',
        'facebook' => 'Facebook',
        'fb.' => 'Facebook',
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
        'wa.me' => 'WhatsApp',
        'linkedin' => 'LinkedIn',
        'lnkd.in' => 'LinkedIn',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        't.co' => 'X (Twitter)',
        'twitter' => 'X (Twitter)',
        'x.com' => 'X (Twitter)',
        'telegram' => 'Telegram',
        't.me' => 'Telegram',
        'chatgpt' => 'ChatGPT',
    ];

    public $timestamps = false;

    protected $fillable = [
        'type',
        'path',
        'label',
        'target',
        'source',
        'is_entry',
        'device',
        'visitor',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'is_entry' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    /** @param  Builder<AudienceEvent>  $query */
    public function scopePages(Builder $query): void
    {
        $query->where('type', self::PAGE);
    }

    /** @param  Builder<AudienceEvent>  $query */
    public function scopeClicks(Builder $query): void
    {
        $query->where('type', self::CLICK);
    }

    public static function isBot(?string $userAgent): bool
    {
        return $userAgent === null || trim($userAgent) === '' || preg_match(self::BOT_PATTERN, $userAgent) === 1;
    }

    /** Empreinte du visiteur pour la journée : impossible d'en retrouver l'IP. */
    public static function visitorFingerprint(?string $ip, ?string $userAgent, ?Carbon $day = null): string
    {
        $day ??= now();

        return substr(hash_hmac('sha256', $day->toDateString().'|'.$ip.'|'.$userAgent, (string) config('app.key')), 0, 16);
    }

    public static function deviceFor(?string $userAgent): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet|kindle|silk|(android(?!.*mobile))/i', (string) $userAgent) => 'tablette',
            (bool) preg_match('/mobi|iphone|ipod|android|opera mini/i', (string) $userAgent) => 'mobile',
            default => 'ordinateur',
        };
    }

    /**
     * D'où vient la visite : la campagne (utm_source) si le lien en a une,
     * sinon le site qui a envoyé le visiteur. Null = accès direct (adresse
     * tapée, favori, appli installée ou lien partagé sans origine, comme
     * souvent depuis WhatsApp).
     */
    public static function sourceFor(?string $referrerHost, ?string $campaign): ?string
    {
        $campaign = Str::of((string) $campaign)->trim()->lower()->limit(80, '')->toString();
        if ($campaign !== '') {
            return self::nameForHost($campaign) ?? $campaign;
        }

        if ($referrerHost === null || $referrerHost === '') {
            return null;
        }

        return self::nameForHost($referrerHost) ?? Str::limit(preg_replace('/^www\./', '', $referrerHost), 80, '');
    }

    private static function nameForHost(string $host): ?string
    {
        $host = strtolower($host);
        foreach (self::SOURCES as $needle => $name) {
            if (str_starts_with($host, $needle) || str_contains($host, '.'.$needle) || str_contains($host, $needle.'.') || $host === rtrim($needle, '.')) {
                return $name;
            }
        }

        return null;
    }
}
