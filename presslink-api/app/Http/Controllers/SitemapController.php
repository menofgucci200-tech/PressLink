<?php

namespace App\Http\Controllers;

use App\Support\Guides;
use Illuminate\Http\Response;

/**
 * sitemap.xml — uniquement les pages publiques à indexer (l'accueil, les
 * pages du site et l'application web). Les espaces connectés (login, dashboard, admin) sont
 * volontairement absents : ils sont en noindex et interdits dans robots.txt.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $lastModified = date('Y-m-d', (int) filemtime(resource_path('views/landing.blade.php')));

        $urls = [
            ['loc' => $baseUrl.'/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => $baseUrl.'/app/', 'priority' => '0.9', 'changefreq' => 'monthly'],
            ['loc' => route('site.clients'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('site.pressings'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('site.pricing'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('site.how'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('site.install'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => route('site.faq'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => route('site.guides'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ...array_map(fn (string $slug) => ['loc' => route('site.guide', $slug), 'priority' => '0.6', 'changefreq' => 'yearly'], array_keys(Guides::ALL)),
            ['loc' => route('site.contact'), 'priority' => '0.5', 'changefreq' => 'yearly'],
            ['loc' => route('site.legal'), 'priority' => '0.2', 'changefreq' => 'yearly'],
            ['loc' => route('site.terms'), 'priority' => '0.2', 'changefreq' => 'yearly'],
            ['loc' => route('site.privacy'), 'priority' => '0.2', 'changefreq' => 'yearly'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1)."</loc>\n";
            $xml .= "    <lastmod>{$lastModified}</lastmod>\n";
            $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
