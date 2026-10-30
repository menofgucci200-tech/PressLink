{{--
    Pastilles de couleurs usuelles + Multicolore + sélecteur libre, réglées dans le
    navigateur (ticket.js).
    $current : expression JS de la couleur en cours (ex. « activeColor », « item.color ») ;
    $pick : appel JS à faire avec la couleur, « %s » à la place du nom (ex. « choisirPinceau(%s) ») ;
    $picker : expression JS lancée par le sélecteur libre ($event.target.value = code « #RRGGBB »).
--}}
@php
    $presets = \App\Support\ColorPalette::presets();
    $selectedRing = 'ring-2 ring-offset-2 ring-(--color-primary) ring-offset-(--color-surface)';
    $call = fn (string $color) => sprintf($pick, \Illuminate\Support\Js::from($color));
@endphp
<div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Couleur">
    @foreach ($presets as $colorName => $colorHex)
        <button type="button" @click="{{ $call($colorName) }}" title="{{ $colorName }}" aria-label="{{ $colorName }}" :aria-pressed="{{ $current }} === @js($colorName)"
                class="w-7 h-7 rounded-full border border-(--color-border) flex items-center justify-center transition-transform duration-150 hover:scale-110"
                :class="{{ $current }} === @js($colorName) ? '{{ $selectedRing }}' : ''"
                style="background-color: {{ $colorHex }}">
            <svg x-show="{{ $current }} === @js($colorName)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="{{ in_array($colorName, ['Blanc', 'Jaune', 'Beige', 'Gris'], true) ? '#111' : '#fff' }}" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
        </button>
    @endforeach
    <button type="button" @click="{{ $call(\App\Support\ColorPalette::MULTICOLOR) }}" title="Multicolore" aria-label="Multicolore" :aria-pressed="{{ $current }} === multicolore"
            class="w-7 h-7 rounded-full border border-(--color-border) flex items-center justify-center transition-transform duration-150 hover:scale-110"
            :class="{{ $current }} === multicolore ? '{{ $selectedRing }}' : ''"
            style="background: conic-gradient(#dc2626, #facc15, #16a34a, #2563eb, #7c3aed, #dc2626)">
        <svg x-show="{{ $current }} === multicolore" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
    </button>
    {{-- Autre couleur : le sélecteur du téléphone / de l'ordinateur --}}
    <label title="Autre couleur" class="relative w-7 h-7 rounded-full border border-dashed border-(--color-text-muted) flex items-center justify-center cursor-pointer overflow-hidden transition-transform duration-150 hover:scale-110 text-(--color-text-secondary)"
           :class="estPersonnalisee({{ $current }}) ? '{{ $selectedRing }} border-solid' : ''" :style="estPersonnalisee({{ $current }}) ? pastille({{ $current }}) : ''">
        <svg x-show="!estPersonnalisee({{ $current }})" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        <input type="color" :value="hex({{ $current }}) || '#2563eb'" @change="{{ $picker }}" aria-label="Autre couleur"
               class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
    </label>
</div>
