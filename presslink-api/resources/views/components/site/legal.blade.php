@props(['title', 'heading', 'description', 'path'])
{{-- Gabarit des pages juridiques : texte long, sommaire implicite par titres h2. --}}
<x-site.page :title="$title" :description="$description" :path="$path">
    <article class="max-w-3xl mx-auto px-5 py-14 md:py-20 text-(--color-text-secondary) leading-relaxed
                    [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-(--color-text-primary) [&_h2]:mt-12 [&_h2]:mb-4
                    [&_h3]:font-semibold [&_h3]:text-(--color-text-primary) [&_h3]:mt-6 [&_h3]:mb-2
                    [&_p]:mb-4 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:mb-4 [&_li]:mb-1.5
                    [&_a]:text-(--color-primary) [&_a]:font-semibold [&_strong]:text-(--color-text-primary)">
        <h1 class="font-display text-4xl md:text-5xl font-extrabold tracking-tight text-(--color-text-primary) mb-3">{{ $heading }}</h1>
        <p class="text-sm text-(--color-text-muted)">Dernière mise à jour : {{ config('presslink.legal.updated_at') }}</p>
        {{ $slot }}
        <nav class="mt-16 pt-6 border-t border-(--color-border) flex flex-wrap gap-x-6 gap-y-2 text-sm" aria-label="Pages juridiques">
            <a href="{{ route('site.legal') }}">Mentions légales</a>
            <a href="{{ route('site.terms') }}">Conditions générales d'utilisation</a>
            <a href="{{ route('site.privacy') }}">Politique de confidentialité</a>
        </nav>
    </article>
</x-site.page>
