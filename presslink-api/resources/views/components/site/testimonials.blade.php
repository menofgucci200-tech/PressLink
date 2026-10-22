{{-- Témoignages de gérants : rien n'est affiché tant qu'aucun témoignage réel (avec accord) n'a été saisi. --}}
@php $testimonials = \App\Support\SiteContent::testimonials(); @endphp
@if ($testimonials !== [])
    <section {{ $attributes->merge(['class' => 'max-w-6xl mx-auto px-5 py-16']) }} data-testid="testimonials">
        <h2 class="font-display text-3xl font-bold tracking-tight mb-8">Ils utilisent PressLink</h2>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5" data-motion="cascade">
            @foreach ($testimonials as $t)
                <figure class="rounded-2xl border border-(--color-border) bg-(--color-surface) p-6 flex flex-col">
                    <blockquote class="text-(--color-text-secondary) leading-relaxed flex-1">« {{ $t['quote'] }} »</blockquote>
                    <figcaption class="mt-5 text-sm">
                        <span class="font-semibold">{{ $t['name'] }}</span>
                        <span class="text-(--color-text-muted)">· {{ $t['pressing'] }}@if ($t['commune']), {{ $t['commune'] }}@endif</span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>
@endif
