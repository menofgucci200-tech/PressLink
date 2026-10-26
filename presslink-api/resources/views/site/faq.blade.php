@php
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect([...$faqs, ...$pressingFaqs])->map(fn ($faq) => [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
        ])->all(),
    ];
@endphp
<x-site.page
    title="Questions fréquentes — PressLink, suivi de pressing"
    description="Suivi des commandes, installation, inscription avec votre numéro, logiciel de gestion, essai gratuit, hors connexion : vos questions sur PressLink."
    path="/questions-frequentes"
    :json-ld="$jsonLd">
    <section class="bg-(--color-bg)">
        <div class="max-w-3xl mx-auto px-5 py-16 md:py-20">
            <h1 class="font-display text-4xl font-extrabold tracking-tight mb-8">Questions fréquentes</h1>
            @foreach (['Vous êtes client d\'un pressing' => $faqs, 'Vous gérez un pressing' => $pressingFaqs] as $heading => $list)
            <h2 class="font-display text-xl font-bold mb-4 {{ $loop->first ? '' : 'mt-10' }}">{{ $heading }}</h2>
            <div class="space-y-3" data-motion="cascade">
                @foreach ($list as $faq)
                    <details class="group bg-(--color-surface) border border-(--color-border) rounded-xl" @if ($loop->first) open @endif>
                        <summary class="flex items-center justify-between gap-4 cursor-pointer list-none px-5 py-4 font-semibold">
                            {{ $faq['question'] }}
                            <svg class="w-5 h-5 flex-none text-(--color-text-muted) transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <p class="px-5 pb-5 text-(--color-text-secondary) leading-relaxed">{{ $faq['answer'] }}</p>
                    </details>
                @endforeach
            </div>
            @endforeach
            <p class="mt-6"><a href="{{ route('site.pressings') }}" class="font-semibold text-(--color-primary) hover:underline">Découvrir le logiciel pour les pressings →</a></p>
            <p class="mt-10 text-(--color-text-secondary)">Vous ne trouvez pas votre réponse ? <a href="{{ route('site.contact') }}" class="font-semibold text-(--color-primary) hover:underline">Contactez-nous</a>.</p>
        </div>
    </section>
</x-site.page>
