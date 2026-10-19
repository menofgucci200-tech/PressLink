<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Bibliothèque d'icônes des services (Repassage, Nettoyage…) et des pièces
 * (Chemise, Robe, Drap…) : dessins plats et colorés, propres à PressLink.
 *
 * Chaque icône est un fragment SVG sur une grille de 64 × 64 ; le contour
 * (trait bleu-gris arrondi) est posé par le gabarit commun, les couleurs de
 * remplissage par chaque dessin. Les chaînes ci-dessous sont écrites ici, en
 * dur : jamais de contenu saisi par un utilisateur dans un SVG.
 */
class ServiceIcons
{
    public const FALLBACK = 'autre';

    /** @var array<string, array{label: string, svg: string}> */
    private const SERVICES = [
        'repassage' => ['label' => 'Repassage', 'svg' => '<path d="M4 46h40c8 0 14 3 16 8H4z" fill="#5EEAD4"/><path d="M16 54l-6 8M44 54l6 8"/><path d="M12 44c0-9 7-16 16-16h8c8 0 13 7 14 16z" fill="#F472B6"/><path d="M12 44h38"/><path d="M24 28c0-6 4-9 10-9"/><circle cx="38" cy="36" r="2.5" fill="#fff"/><path d="M12 14c-3 3 3 5 0 9M20 10c-3 3 3 5 0 9" stroke="#60A5FA"/>'],
        'nettoyage' => ['label' => 'Nettoyage', 'svg' => '<rect x="10" y="6" width="44" height="52" rx="6" fill="#BFDBFE"/><path d="M10 18h44"/><circle cx="20" cy="12" r="2.5" fill="#F472B6"/><path d="M30 12h14"/><circle cx="32" cy="38" r="14" fill="#fff"/><circle cx="32" cy="38" r="9" fill="#60A5FA"/><path d="M25 39c2-2 4-2 7 0s5 2 7 0" stroke="#fff"/><path d="M16 58v3M48 58v3"/>'],
        'nettoyage_sec' => ['label' => 'Nettoyage à sec', 'svg' => '<path d="M32 22v-3c0-2 4-2 4-6a4 4 0 1 0-8 0"/><path d="M32 22L6 46h52z" fill="#C4B5FD"/><path d="M14 46h36" stroke="#7C3AED"/><path d="M50 6l2 6 6 2-6 2-2 6-2-6-6-2 6-2z" fill="#FBBF24"/>'],
        'retouche' => ['label' => 'Retouches et couture', 'svg' => '<rect x="10" y="12" width="26" height="6" rx="2" fill="#E2E8F0"/><rect x="10" y="46" width="26" height="6" rx="2" fill="#E2E8F0"/><rect x="13" y="18" width="20" height="28" fill="#F472B6"/><path d="M13 25h20M13 32h20M13 39h20" stroke="#BE185D"/><path d="M26 60L54 10" stroke="#64748B" stroke-width="3"/><path d="M53 15c-8 10 4 14-10 22" stroke="#F472B6"/>'],
        'detachage' => ['label' => 'Détachage', 'svg' => '<path d="M28 6C20 22 14 28 14 38a14 14 0 0 0 28 0C42 28 36 22 28 6z" fill="#7DD3FC"/><path d="M22 40a6 6 0 0 0 6 6" stroke="#fff"/><path d="M50 10l2 6 6 2-6 2-2 6-2-6-6-2 6-2z" fill="#FBBF24"/><path d="M48 44l1.5 4 4 1.5-4 1.5-1.5 4-1.5-4-4-1.5 4-1.5z" fill="#FDE68A"/>'],
        'teinture' => ['label' => 'Teinture', 'svg' => '<path d="M32 8C17 8 6 19 6 32s10 24 22 24c5 0 6-3 4-6s0-6 5-6h8c7 0 13-4 13-11C58 18 47 8 32 8z" fill="#FDE68A"/><circle cx="20" cy="26" r="4" fill="#F87171"/><circle cx="32" cy="18" r="4" fill="#60A5FA"/><circle cx="45" cy="24" r="4" fill="#34D399"/><circle cx="16" cy="40" r="3.5" fill="#A78BFA"/>'],
        'express' => ['label' => 'Express', 'svg' => '<path d="M36 4L12 36h16l-4 24 28-34H36z" fill="#FBBF24"/>'],
        'blanchisserie' => ['label' => 'Blanchisserie', 'svg' => '<path d="M8 34h48l-5 18a6 6 0 0 1-6 4H19a6 6 0 0 1-6-4z" fill="#93C5FD"/><circle cx="20" cy="22" r="7" fill="#E0F2FE"/><circle cx="34" cy="16" r="9" fill="#E0F2FE"/><circle cx="47" cy="26" r="6" fill="#E0F2FE"/><path d="M31 12a5 5 0 0 1 4-2" stroke="#fff"/>'],
        'cordonnerie' => ['label' => 'Cordonnerie', 'svg' => '<path d="M6 40c0-6 2-8 6-8l8-4 6 6h10c10 0 20 4 22 12v4H6z" fill="#FB923C"/><path d="M6 48h52v7H6z" fill="#F1F5F9"/><path d="M22 32l-2 6M28 34l-2 6"/>'],
        'tapis' => ['label' => 'Tapis', 'svg' => '<rect x="6" y="22" width="40" height="26" rx="4" fill="#F9A8D4"/><ellipse cx="46" cy="35" rx="8" ry="13" fill="#FBCFE8"/><path d="M46 28c-4 0-4 8 0 8s2-4 0-4"/><path d="M12 28v14M20 28v14M28 28v14M36 28v14" stroke="#EC4899"/>'],
        'livraison' => ['label' => 'Livraison', 'svg' => '<path d="M4 20h34v28H4z" fill="#BFDBFE"/><path d="M38 28h12l10 10v10H38z" fill="#60A5FA"/><path d="M43 32h6l6 6H43z" fill="#fff"/><circle cx="16" cy="50" r="6" fill="#334155"/><circle cx="48" cy="50" r="6" fill="#334155"/><circle cx="16" cy="50" r="2" fill="#fff"/><circle cx="48" cy="50" r="2" fill="#fff"/>'],
        'autre' => ['label' => 'Autre', 'svg' => '<path d="M32 6l7 16 17 2-13 12 4 17-15-9-15 9 4-17L8 24l17-2z" fill="#FDE047"/>'],
    ];

    /** @var array<string, array{label: string, svg: string}> */
    private const PIECES = [
        'chemise' => ['label' => 'Chemise', 'svg' => '<path d="M22 8L8 16l4 12 6-3v31h28V25l6 3 4-12-14-8-10 12z" fill="#BFDBFE"/><path d="M22 8l10 12 10-12" fill="#fff"/><path d="M32 20v36"/><circle cx="32" cy="30" r="1.2" fill="#334155"/><circle cx="32" cy="40" r="1.2" fill="#334155"/>'],
        'tshirt' => ['label' => 'T-shirt', 'svg' => '<path d="M22 8L6 16l6 12 8-4v32h24V24l8 4 6-12-16-8c-1 5-5 8-10 8s-9-3-10-8z" fill="#FCA5A5"/>'],
        'pantalon' => ['label' => 'Pantalon', 'svg' => '<path d="M18 6h28l4 52H35l-3-30-3 30H14z" fill="#93C5FD"/><path d="M18 12h28"/><path d="M40 16v8" />'],
        'short' => ['label' => 'Short', 'svg' => '<path d="M16 10h32l6 30H36l-4-14-4 14H10z" fill="#FDBA74"/><path d="M16 16h32"/>'],
        'jupe' => ['label' => 'Jupe', 'svg' => '<path d="M22 10h20l12 44H10z" fill="#F9A8D4"/><path d="M22 16h20"/><path d="M26 16l-6 38M32 16v38M38 16l6 38" stroke="#EC4899"/>'],
        'robe' => ['label' => 'Robe', 'svg' => '<path d="M24 6h4l4 6 4-6h4l2 16 14 34H8l14-34z" fill="#C4B5FD"/><path d="M22 24h20"/>'],
        'costume' => ['label' => 'Costume', 'svg' => '<path d="M20 8L8 16l6 40h36l6-40-12-8-12 14z" fill="#64748B"/><path d="M26 10l6 20 6-20" fill="#fff"/><path d="M30 20h4l2 14-4 6-4-6z" fill="#F87171"/>'],
        'veste' => ['label' => 'Veste', 'svg' => '<path d="M22 8L8 16v40h48V16L42 8l-10 10z" fill="#FDBA74"/><path d="M32 18v38"/><path d="M14 40h10M40 40h10"/>'],
        'manteau' => ['label' => 'Manteau', 'svg' => '<path d="M22 6L10 14v46h44V14L42 6l-10 10z" fill="#A78BFA"/><path d="M32 16l-8 10M32 16l8 10"/><circle cx="30" cy="36" r="1.6" fill="#fff"/><circle cx="30" cy="46" r="1.6" fill="#fff"/>'],
        'pull' => ['label' => 'Pull', 'svg' => '<path d="M20 10h24l14 12-6 10-6-4v28H18V28l-6 4-6-10z" fill="#FDE68A"/><path d="M26 10c0 4 3 6 6 6s6-2 6-6"/><path d="M18 50h28"/>'],
        'cravate' => ['label' => 'Cravate', 'svg' => '<path d="M26 6h12l-2 8h-8z" fill="#F87171"/><path d="M28 14h8l6 32-10 12-10-12z" fill="#EF4444"/><path d="M30 26l8-4M28 34l11-5" stroke="#fff"/>'],
        'boubou' => ['label' => 'Boubou', 'svg' => '<path d="M24 6c2 5 5 7 8 7s6-2 8-7l18 10v14l-8-3v32H14V27l-8 3V16z" fill="#FBBF24"/><path d="M26 14l6 8 6-8" stroke="#B45309"/><circle cx="32" cy="32" r="3" fill="#B45309"/><path d="M22 56h20" stroke="#B45309"/>'],
        'pagne' => ['label' => 'Pagne', 'svg' => '<rect x="8" y="10" width="48" height="42" rx="4" fill="#34D399"/><path d="M32 16l9 9-9 9-9-9z" fill="#FDE047"/><path d="M32 36l6 6-6 6-6-6z" fill="#F87171"/><path d="M8 20h48" stroke="#065F46"/>'],
        'drap' => ['label' => 'Drap', 'svg' => '<rect x="8" y="36" width="48" height="14" rx="3" fill="#BFDBFE"/><rect x="12" y="23" width="40" height="14" rx="3" fill="#E0F2FE"/><rect x="16" y="10" width="32" height="14" rx="3" fill="#fff"/>'],
        'couette' => ['label' => 'Couette', 'svg' => '<rect x="8" y="12" width="48" height="40" rx="8" fill="#DDD6FE"/><path d="M8 25h48M8 38h48M24 12v40M40 12v40"/>'],
        'nappe' => ['label' => 'Nappe', 'svg' => '<path d="M6 20h52l-4 6H10z" fill="#FCA5A5"/><path d="M10 26h44l2 28H8z" fill="#FECACA"/><path d="M20 26v28M32 26v28M44 26v28"/>'],
        'rideau' => ['label' => 'Rideau', 'svg' => '<path d="M6 10h52"/><path d="M8 12h18c0 14-2 30-4 42H8z" fill="#99F6E4"/><path d="M56 12H38c0 14 2 30 4 42h14z" fill="#5EEAD4"/>'],
        'serviette' => ['label' => 'Serviette', 'svg' => '<rect x="10" y="14" width="44" height="36" rx="4" fill="#FDBA74"/><path d="M10 24h44M10 40h44" stroke="#fff"/><path d="M14 50v7M22 50v7M30 50v7M38 50v7M46 50v7"/>'],
        'chaussures' => ['label' => 'Chaussures', 'svg' => '<path d="M6 40c0-6 2-8 6-8l8-4 6 6h10c10 0 20 4 22 12v4H6z" fill="#F9A8D4"/><path d="M6 48h52v7H6z" fill="#F1F5F9"/><path d="M22 32l-2 6M28 34l-2 6"/>'],
        'sac' => ['label' => 'Sac', 'svg' => '<path d="M12 22h40l4 34H8z" fill="#F9A8D4"/><path d="M22 22v-6a10 10 0 0 1 20 0v6"/><rect x="28" y="30" width="8" height="6" rx="1" fill="#FDE047"/>'],
        'casquette' => ['label' => 'Casquette', 'svg' => '<path d="M10 40c0-14 10-22 22-22s22 8 22 22z" fill="#60A5FA"/><path d="M40 40h22c0 4-4 6-10 6H40z" fill="#3B82F6"/><circle cx="32" cy="18" r="2.5" fill="#fff"/>'],
        'tapis' => ['label' => 'Tapis', 'svg' => '<rect x="6" y="22" width="40" height="26" rx="4" fill="#F9A8D4"/><ellipse cx="46" cy="35" rx="8" ry="13" fill="#FBCFE8"/><path d="M46 28c-4 0-4 8 0 8s2-4 0-4"/><path d="M12 28v14M20 28v14M28 28v14M36 28v14" stroke="#EC4899"/>'],
        'autre' => ['label' => 'Autre pièce', 'svg' => '<path d="M32 16a4 4 0 1 1 4 4c-3 1-4 2-4 4"/><path d="M32 26L6 46h52z" fill="#E2E8F0"/>'],
    ];

    /** Mots-clés (sans accent, en minuscules) → icône de pièce. */
    private const KEYWORDS = [
        'tshirt' => ['t-shirt', 'tee shirt', 'tee-shirt', 'tshirt', 'polo', 'debardeur'],
        'chemise' => ['chemise', 'chemisier', 'blouse'],
        'pantalon' => ['pantalon', 'jean', 'jogging', 'legging', 'treillis'],
        'short' => ['short', 'bermuda'],
        'jupe' => ['jupe'],
        'robe' => ['robe'],
        'costume' => ['costume', 'complet', 'smoking', 'tailleur'],
        'veste' => ['veste', 'blazer', 'blouson', 'doudoune', 'gilet'],
        'manteau' => ['manteau', 'parka', 'impermeable', 'trench', 'pardessus'],
        'pull' => ['pull', 'sweat', 'tricot'],
        'cravate' => ['cravate', 'noeud'],
        'boubou' => ['boubou', 'kaftan', 'bazin', 'tenue', 'djellaba', 'kita'],
        'pagne' => ['pagne', 'wax', 'foulard', 'echarpe', 'cache-col'],
        'drap' => ['drap', 'housse', 'taie', 'linge de lit'],
        'couette' => ['couette', 'couverture', 'edredon', 'plaid', 'oreiller', 'duvet'],
        'nappe' => ['nappe'],
        'rideau' => ['rideau', 'voilage'],
        'serviette' => ['serviette', 'peignoir', 'essuie'],
        'chaussures' => ['chaussure', 'basket', 'sandale', 'botte', 'mocassin'],
        'sac' => ['sac'],
        'casquette' => ['casquette', 'chapeau', 'bonnet', 'bob'],
        'tapis' => ['tapis', 'moquette', 'paillasson'],
    ];

    /** @return array<string, string> clé => libellé */
    public static function options(string $kind): array
    {
        return array_map(fn (array $icon) => $icon['label'], self::library($kind));
    }

    public static function has(string $kind, ?string $key): bool
    {
        return $key !== null && isset(self::library($kind)[$key]);
    }

    /** Clé d'icône de pièce la plus plausible d'après le nom (« Chemise blanche » → chemise). */
    public static function guessPiece(string $name): string
    {
        $haystack = ' '.Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9\-]+/', ' ')->trim().' ';

        foreach (self::KEYWORDS as $key => $words) {
            foreach ($words as $word) {
                if (str_contains($haystack, ' '.$word)) {
                    return $key;
                }
            }
        }

        return self::FALLBACK;
    }

    /** Icône de service la plus plausible d'après le nom (« Nettoyage à sec » → nettoyage_sec). */
    public static function guessService(string $name): string
    {
        $n = ' '.Str::of($name)->ascii()->lower()->trim().' ';

        return match (true) {
            str_contains($n, 'sec') && str_contains($n, 'nettoy') => 'nettoyage_sec',
            str_contains($n, 'repass') => 'repassage',
            str_contains($n, 'nettoy'), str_contains($n, 'lavage'), str_contains($n, 'laver') => 'nettoyage',
            str_contains($n, 'retouch'), str_contains($n, 'couture'), str_contains($n, 'rafist'), str_contains($n, 'ourlet') => 'retouche',
            str_contains($n, 'tach') => 'detachage',
            str_contains($n, 'teint') => 'teinture',
            str_contains($n, 'express'), str_contains($n, 'urgent') => 'express',
            str_contains($n, 'blanch') => 'blanchisserie',
            str_contains($n, 'cordon'), str_contains($n, 'chaussure') => 'cordonnerie',
            str_contains($n, 'tapis') => 'tapis',
            str_contains($n, 'livr') => 'livraison',
            default => self::FALLBACK,
        };
    }

    public static function svg(string $kind, ?string $key, int $size = 40, ?string $class = null): HtmlString
    {
        $library = self::library($kind);
        $icon = $library[$key] ?? $library[self::FALLBACK];
        $class = $class !== null ? ' class="'.e($class).'"' : '';

        return new HtmlString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="'.$size.'" height="'.$size.'"'.$class.' aria-hidden="true" focusable="false">'
            .'<g fill="none" stroke="#334155" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$icon['svg'].'</g></svg>'
        );
    }

    /** @return array<string, array{label: string, svg: string}> */
    private static function library(string $kind): array
    {
        return $kind === 'service' ? self::SERVICES : self::PIECES;
    }
}
