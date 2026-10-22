@props(['slug'])
{{-- Gabarit d'un guide pratique : article, appel à l'essai, autres guides. --}}
@php
    $guide = \App\Support\Guides::ALL[$slug];
    $path = '/guides/'.$slug;
    $baseUrl = rtrim((string) config('app.url'), '/');
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $guide['title'],
        'description' => $guide['description'],
        'inLanguage' => 'fr-CI',
        'datePublished' => $guide['published'],
        'mainEntityOfPage' => $baseUrl.$path,
        'author' => ['@type' => 'Organization', 'name' => 'PressLink', 'url' => $baseUrl.'/'],
        'publisher' => ['@type' => 'Organization', 'name' => 'PressLink', 'logo' => ['@type' => 'ImageObject', 'url' => $baseUrl.'/images/logo-presslink.png']],
    ];
@endphp
<x-site.page :title="mb_strlen($guide['title']) <= 48 ? $guide['title'].' — PressLink' : $guide['title']" :description="$guide['description']" :path="$path" :json-ld="$jsonLd">
    <article class="max-w-3xl mx-auto px-5 py-14 md:py-20 text-(--color-text-secondary) text-lg leading-relaxed
                    [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-(--color-text-primary) [&_h2]:mt-12 [&_h2]:mb-4
                    [&_p]:mb-5 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:mb-5 [&_li]:mb-2
                    [&_a]:text-(--color-primary) [&_a]:font-semibold [&_strong]:text-(--color-text-primary)">
        <p class="text-sm font-semibold uppercase tracking-widest text-(--color-primary) mb-3"><a href="{{ route('site.guides') }}" class="!font-semibold">Guides</a> · Pour les pressings</p>
        <h1 class="font-display text-4xl md:text-5xl font-extrabold tracking-tight text-(--color-text-primary) leading-[1.1] mb-8">{{ $guide['title'] }}</h1>
        {{ $slot }}
    </article>

    <section class="max-w-3xl mx-auto px-5 pb-16">
        <div class="bg-(--color-primary-tint) border border-(--color-primary)/15 rounded-3xl px-6 py-8 md:px-10 flex flex-col md:flex-row md:items-center justify-between gap-5">
            <div>
                <h2 class="font-display text-xl font-bold mb-1">PressLink fait tout cela pour vous</h2>
                <p class="text-(--color-text-secondary)">Logiciel de gestion de pressing conçu en Côte d'Ivoire. <a href="{{ route('site.pressings') }}" class="font-semibold text-(--color-primary) hover:underline">Découvrir →</a></p>
            </div>
            <x-site.trial-button class="flex-none" />
        </div>
        <h2 class="font-display text-lg font-bold mt-12 mb-4">Autres guides</h2>
        <ul class="space-y-2">
            @foreach (\App\Support\Guides::ALL as $otherSlug => $other)
                @continue($otherSlug === $slug)
                <li><a href="{{ route('site.guide', $otherSlug) }}" class="font-semibold text-(--color-primary) hover:underline">{{ $other['title'] }}</a></li>
            @endforeach
        </ul>
    </section>
</x-site.page>
