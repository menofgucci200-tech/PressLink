@props(['plans', 'current' => null])
{{-- Tableau comparatif des formules, ligne par ligne (page Tarifs et page Formules).
     Sur téléphone : chaque libellé passe sur sa propre ligne, au-dessus des trois
     valeurs, et les noms des formules restent visibles en haut pendant le défilement. --}}
@php
    $fcfa = fn (?int $amount) => \App\Support\PlanCatalog::fcfa($amount);
    $yes = '<svg class="w-5 h-5 mx-auto text-(--color-success-text)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-label="Inclus"><path d="M20 6 9 17l-5-5"/></svg>';
    $no = '<span class="text-(--color-text-muted)" aria-label="Non inclus">—</span>';
    $columns = count($plans);

    // Chaque ligne : un libellé, puis une valeur par formule ou un texte commun (span).
    $rows = [
        ['label' => e('Prix par mois'), 'muted' => true, 'strong' => true, 'cells' => collect($plans)->map(fn ($item) => $item['monthly'] !== null ? $fcfa($item['monthly']).' F' : 'Sur devis')->all()],
        ['label' => e('Prix par an'), 'muted' => true, 'cells' => collect($plans)->map(fn ($item) => $item['annual'] !== null ? $fcfa($item['annual']).' F' : '—')->all()],
        ['label' => e('Commandes par mois'), 'muted' => true, 'cells' => collect($plans)->map(fn ($item) => $item['ordersLimit'] === null ? 'Illimitées' : $fcfa($item['ordersLimit']))->all()],
        ['section' => 'Dans toutes les formules'],
    ];
    foreach (\App\Support\PlanCatalog::ESSENTIALS as $essential) {
        $rows[] = ['label' => e($essential), 'html' => true, 'cells' => array_fill(0, $columns, $yes)];
    }
    $rows[] = ['section' => 'Selon la formule'];
    foreach (\App\Enums\Feature::cases() as $feature) {
        $label = '<span class="font-medium">'.e($feature->label()).'</span><span class="block text-xs text-(--color-text-secondary)">'.e($feature->description()).'</span>';
        if (collect($plans)->contains(fn ($item) => in_array($feature, $item['features'], true))) {
            $rows[] = ['label' => $label, 'html' => true, 'cells' => collect($plans)->map(fn ($item) => in_array($feature, $item['features'], true) ? $yes : $no)->all()];
        } else {
            // Dans aucune formule : proposée à part, jamais affichée comme absente.
            $rows[] = ['label' => $label, 'span' => $feature->priceFcfa() !== null ? 'En option : '.\App\Support\PlanCatalog::fcfa($feature->priceFcfa()).' F / mois' : 'Sur demande'];
        }
    }
@endphp
<div {{ $attributes->merge(['class' => 'sm:overflow-x-auto rounded-2xl border border-(--color-border) bg-(--color-surface)']) }}>
    <table class="w-full max-sm:table-fixed sm:min-w-[640px] text-sm" data-plans-compare>
        <thead>
            <tr class="border-b border-(--color-border)">
                <th class="max-sm:hidden text-left font-semibold px-5 py-4 w-2/5">Comparer en détail</th>
                @foreach ($plans as $item)
                    <th class="px-2 sm:px-4 py-3 sm:py-4 text-center font-display text-base font-bold max-sm:sticky max-sm:top-16 max-sm:z-10 max-sm:bg-(--color-surface) max-sm:shadow-[0_1px_0_var(--color-border)] max-sm:first:rounded-tl-2xl max-sm:last:rounded-tr-2xl {{ $current === $item['plan'] ? 'text-(--color-primary)' : '' }}">
                        {{ $item['label'] }}
                        @if ($current === $item['plan'])<span class="block text-[11px] font-semibold uppercase tracking-wide">Votre formule</span>@endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-(--color-border)">
            @foreach ($rows as $row)
                @if (isset($row['section']))
                    @foreach (['sm:hidden' => $columns, 'max-sm:hidden' => $columns + 1] as $visibility => $span)
                        <tr class="{{ $visibility }} bg-(--color-bg)"><td colspan="{{ $span }}" class="px-4 sm:px-5 py-2 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">{{ $row['section'] }}</td></tr>
                    @endforeach
                    @continue
                @endif
                @php $labelClass = ! empty($row['muted']) ? 'text-(--color-text-secondary)' : ''; @endphp
                {{-- Téléphone : le libellé sur toute la largeur, les valeurs juste en dessous. --}}
                <tr class="sm:hidden border-b-0!">
                    <td colspan="{{ $columns }}" class="px-4 pt-3 {{ $labelClass }}">{!! $row['label'] !!}</td>
                </tr>
                <tr>
                    <td class="max-sm:hidden px-5 py-3 {{ $labelClass }}">{!! $row['label'] !!}</td>
                    @if (isset($row['span']))
                        <td colspan="{{ $columns }}" class="px-2 sm:px-4 pt-1.5 pb-3 sm:py-3 text-center text-xs font-semibold text-(--color-text-secondary)">{{ $row['span'] }}</td>
                    @else
                        @foreach ($row['cells'] as $cell)
                            <td class="px-2 sm:px-4 pt-1.5 pb-3 sm:py-3 text-center tabular-nums {{ ! empty($row['strong']) ? 'font-semibold' : '' }}">{!! ! empty($row['html']) ? $cell : e($cell) !!}</td>
                        @endforeach
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
