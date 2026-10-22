<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Contenus du site vitrine pour les pressings : témoignages (gérés depuis le
 * Super Admin, publiés seulement avec l'accord du gérant), phrases de
 * réassurance et données structurées du logiciel pour Google.
 */
class SiteContent
{
    /**
     * Témoignages publiés : uniquement ceux saisis par l'équipe PressLink avec
     * l'accord écrit du gérant. Aucun témoignage par défaut.
     *
     * @return list<array{name: string, pressing: string, commune: string, quote: string}>
     */
    public static function testimonials(): array
    {
        $all = Setting::read('testimonials', []);

        return array_values(array_map(
            fn (array $t) => [
                'name' => (string) ($t['name'] ?? ''),
                'pressing' => (string) ($t['pressing'] ?? ''),
                'commune' => (string) ($t['commune'] ?? ''),
                'quote' => (string) ($t['quote'] ?? ''),
            ],
            array_filter(is_array($all) ? $all : [], fn ($t) => is_array($t) && ($t['consent'] ?? false) && ($t['published'] ?? true) && trim((string) ($t['quote'] ?? '')) !== ''),
        ));
    }

    /**
     * Ce qui rassure un gérant avant de se lancer : uniquement des faits
     * vérifiables dans le produit.
     *
     * @return list<array{title: string, text: string}>
     */
    public static function reassurances(): array
    {
        return [
            ['title' => 'Sans engagement', 'text' => 'Vous payez une période à la fois, au mois ou à l\'année, par lien Wave. Rien n\'est prélevé automatiquement.'],
            ['title' => 'Aucun matériel à acheter', 'text' => 'Le téléphone, la tablette ou l\'ordinateur que vous avez déjà suffit. Les reçus partent sur WhatsApp ou s\'impriment.'],
            ['title' => 'Même quand Internet coupe', 'text' => 'Avec Business et Premium, le comptoir continue hors connexion et tout s\'envoie au retour du réseau.'],
            ['title' => 'Une équipe à Abidjan', 'text' => 'Nous créons votre espace et vous aidons à le mettre en place. Support par téléphone et WhatsApp.'],
        ];
    }

    /**
     * Le logiciel de gestion (payant, pour les pressings), à côté de
     * l'application client gratuite décrite sur l'accueil.
     *
     * @return array<string, mixed>
     */
    public static function softwareJsonLd(string $url): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $prices = collect(PlanCatalog::plans())->pluck('monthly')->filter()->values();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'PressLink — logiciel de gestion de pressing',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Navigateur web (ordinateur, tablette, téléphone)',
            'url' => $url,
            'inLanguage' => 'fr',
            'description' => 'Logiciel de gestion de pressing pour la Côte d\'Ivoire : commandes, clients, caisse, équipe, encaissement Wave et Orange Money, notifications aux clients, mode hors connexion.',
            'areaServed' => [
                ['@type' => 'City', 'name' => 'Abidjan'],
                ['@type' => 'Country', 'name' => 'Côte d\'Ivoire'],
            ],
            'offers' => $prices->isEmpty() ? null : [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'XOF',
                'lowPrice' => (string) $prices->min(),
                'highPrice' => (string) $prices->max(),
                'offerCount' => (string) $prices->count(),
                'url' => route('site.pricing'),
            ],
            'publisher' => ['@type' => 'Organization', 'name' => 'PressLink', 'url' => $baseUrl.'/'],
        ];
    }
}
