@props(['dark' => false])
{{-- Ce qui rassure un gérant avant de se lancer (faits vérifiables dans le produit). --}}
<ul {{ $attributes->merge(['class' => 'grid sm:grid-cols-2 lg:grid-cols-4 gap-4']) }} data-motion="cascade">
    @foreach (\App\Support\SiteContent::reassurances() as $item)
        <li @class([
            'rounded-2xl p-5 border',
            'border-white/10 bg-white/5' => $dark,
            'border-(--color-border) bg-(--color-surface)' => ! $dark,
        ])>
            <div class="flex items-center gap-2 font-semibold mb-1.5">
                <svg class="w-5 h-5 flex-none {{ $dark ? 'text-blue-300' : 'text-(--color-primary)' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                {{ $item['title'] }}
            </div>
            <p @class(['text-sm leading-relaxed', 'text-slate-300' => $dark, 'text-(--color-text-secondary)' => ! $dark])>{{ $item['text'] }}</p>
        </li>
    @endforeach
</ul>
