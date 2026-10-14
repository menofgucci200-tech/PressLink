@php
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect($faqs)->map(fn ($faq) => [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
        ])->all(),
    ];
@endphp
<x-site.page
    title="Questions fréquentes — PressLink, suivi de pressing"
    description="Suivi des commandes, installation de l'application, code du pressing, logiciel de gestion, support : les réponses à vos questions sur PressLink."
    path="/questions-frequentes"
    :json-ld="$jsonLd">
    <section class="bg-(--color-bg)">
        <div class="max-w-3xl mx-auto px-5 py-16 md:py-20">
            <h1 class="font-display text-4xl font-extrabold tracking-tight mb-8">Questions fréquentes</h1>
            <div class="space-y-3">
                @foreach ($faqs as $faq)
                    <details class="group bg-(--color-surface) border border-(--color-border) rounded-xl" @if ($loop->first) open @endif>
                        <summary class="flex items-center justify-between gap-4 cursor-pointer list-none px-5 py-4 font-semibold">
                            {{ $faq['question'] }}
                            <svg class="w-5 h-5 flex-none text-(--color-text-muted) transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <p class="px-5 pb-5 text-(--color-text-secondary) leading-relaxed">{{ $faq['answer'] }}</p>
                    </details>
                @endforeach
            </div>
            <p class="mt-10 text-(--color-text-secondary)">Vous ne trouvez pas votre réponse ? <a href="{{ route('site.contact') }}" class="font-semibold text-(--color-primary) hover:underline">Contactez-nous</a>.</p>
        </div>
    </section>
</x-site.page>
