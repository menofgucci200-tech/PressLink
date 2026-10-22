<x-site.page
    title="Guides pour les pressings — PressLink"
    description="Conseils aux gérants de pressing en Côte d'Ivoire : choisir un logiciel, prévenir les clients, encaisser par Wave, travailler sans Internet."
    path="/guides">
    <section class="max-w-4xl mx-auto px-5 py-16 md:py-20">
        <p class="text-xs font-semibold uppercase tracking-widest text-(--color-primary) mb-3">Guides</p>
        <h1 class="font-display text-4xl md:text-5xl font-extrabold tracking-tight mb-4">Guides pour les gérants de pressing</h1>
        <p class="text-(--color-text-secondary) text-lg mb-10">Des conseils concrets pour gérer votre pressing au quotidien, à Abidjan comme partout en Côte d'Ivoire.</p>
        <div class="grid md:grid-cols-2 gap-5" data-motion="cascade">
            @foreach (\App\Support\Guides::ALL as $slug => $guide)
                <a href="{{ route('site.guide', $slug) }}" class="group rounded-2xl border border-(--color-border) bg-(--color-surface) p-6 hover:border-(--color-primary)">
                    <h2 class="font-display text-xl font-bold mb-2 group-hover:text-(--color-primary)">{{ $guide['title'] }}</h2>
                    <p class="text-sm text-(--color-text-secondary) leading-relaxed">{{ $guide['description'] }}</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-(--color-primary)">Lire le guide →</span>
                </a>
            @endforeach
        </div>
    </section>
</x-site.page>
