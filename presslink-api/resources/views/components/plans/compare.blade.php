@props(['plans', 'current' => null])
{{-- Tableau comparatif des formules, ligne par ligne (page Tarifs et page Formules). --}}
@php
    $fcfa = fn (?int $amount) => \App\Support\PlanCatalog::fcfa($amount);
    $yes = '<svg class="w-5 h-5 mx-auto text-(--color-success-text)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-label="Inclus"><path d="M20 6 9 17l-5-5"/></svg>';
    $no = '<span class="text-(--color-text-muted)" aria-label="Non inclus">—</span>';
@endphp
<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-2xl border border-(--color-border) bg-(--color-surface)']) }}>
    <table class="w-full min-w-[640px] text-sm">
        <thead>
            <tr class="border-b border-(--color-border)">
                <th class="text-left font-semibold px-5 py-4 w-2/5">Comparer en détail</th>
                @foreach ($plans as $item)
                    <th class="px-4 py-4 text-center font-display text-base font-bold {{ $current === $item['plan'] ? 'text-(--color-primary)' : '' }}">
                        {{ $item['label'] }}
                        @if ($current === $item['plan'])<span class="block text-[11px] font-semibold uppercase tracking-wide">Votre formule</span>@endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-(--color-border)">
            <tr>
                <td class="px-5 py-3 text-(--color-text-secondary)">Prix par mois</td>
                @foreach ($plans as $item)<td class="px-4 py-3 text-center font-semibold tabular-nums">{{ $item['monthly'] !== null ? $fcfa($item['monthly']).' F' : 'Sur devis' }}</td>@endforeach
            </tr>
            <tr>
                <td class="px-5 py-3 text-(--color-text-secondary)">Prix par an</td>
                @foreach ($plans as $item)<td class="px-4 py-3 text-center tabular-nums">{{ $item['annual'] !== null ? $fcfa($item['annual']).' F' : '—' }}</td>@endforeach
            </tr>
            <tr>
                <td class="px-5 py-3 text-(--color-text-secondary)">Commandes par mois</td>
                @foreach ($plans as $item)<td class="px-4 py-3 text-center tabular-nums">{{ $item['ordersLimit'] === null ? 'Illimitées' : $fcfa($item['ordersLimit']) }}</td>@endforeach
            </tr>
            <tr class="bg-(--color-bg)"><td colspan="{{ count($plans) + 1 }}" class="px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Dans toutes les formules</td></tr>
            @foreach (\App\Support\PlanCatalog::ESSENTIALS as $essential)
                <tr>
                    <td class="px-5 py-3">{{ $essential }}</td>
                    @foreach ($plans as $item)<td class="px-4 py-3 text-center">{!! $yes !!}</td>@endforeach
                </tr>
            @endforeach
            <tr class="bg-(--color-bg)"><td colspan="{{ count($plans) + 1 }}" class="px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Selon la formule</td></tr>
            @foreach (\App\Enums\Feature::cases() as $feature)
                <tr>
                    <td class="px-5 py-3"><span class="font-medium">{{ $feature->label() }}</span><span class="block text-xs text-(--color-text-secondary)">{{ $feature->description() }}</span></td>
                    @foreach ($plans as $item)<td class="px-4 py-3 text-center">{!! in_array($feature, $item['features'], true) ? $yes : $no !!}</td>@endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
