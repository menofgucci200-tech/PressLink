@props(['title', 'description', 'path' => '/', 'keywords' => null, 'jsonLd' => null])
@php
    $baseUrl = rtrim((string) config('app.url'), '/');
    $e164 = config('presslink.support.phone_e164');
    // Fil d'Ariane pour Google (Accueil › page) : affiché sous le lien dans
    // les résultats de recherche à la place de l'URL brute.
    $breadcrumb = $path === '/' ? null : [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => $baseUrl.'/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => trim(\Illuminate\Support\Str::before($title, ' — ')), 'item' => $baseUrl.$path],
        ],
    ];
@endphp
{{-- Gabarit commun des pages publiques : en-tête, pied de page et bouton
     WhatsApp flottant ; chaque page ne fournit que son contenu. --}}
<x-layouts.public
    :title="$title"
    :description="$description"
    :keywords="$keywords"
    :canonical="$baseUrl.$path"
    :og-image="$baseUrl.'/og-image.png'"
    :json-ld="$jsonLd"
    :breadcrumb-json-ld="$breadcrumb">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:px-4 focus:py-2 focus:rounded-lg focus:bg-(--color-primary) focus:text-white">Aller au contenu</a>
    @if ($path !== '/' && \App\Support\LaunchOffer::isActive())
        <a href="{{ route('home') }}#rejoindre" class="block bg-[#0B1530] text-white text-center text-sm font-medium px-5 py-2.5 hover:bg-[#12245A]">
            <span class="inline-block w-2 h-2 rounded-full bg-[#22C55E] mr-1.5 align-middle" aria-hidden="true"></span>
            Lancement officiel : PressLink est gratuit pour les pressings jusqu'au {{ \App\Support\LaunchOffer::dateLabel() }}.
            <span class="font-bold text-[#FBBF24] whitespace-nowrap">En profiter →</span>
        </a>
    @endif
    <x-site.header />
    <main id="contenu">{{ $slot }}</main>
    <x-site.footer />

    <a href="https://wa.me/{{ ltrim($e164, '+') }}?text={{ rawurlencode('Bonjour, j\'ai une question sur PressLink.') }}"
       target="_blank" rel="noopener" aria-label="Écrire à PressLink sur WhatsApp"
       class="fixed bottom-5 right-5 z-30 w-14 h-14 rounded-full shadow-lg shadow-green-600/30 hover:scale-105">
        <img src="{{ asset('images/whatsapp.png') }}" alt="" width="56" height="56" class="w-14 h-14">
    </a>
</x-layouts.public>
