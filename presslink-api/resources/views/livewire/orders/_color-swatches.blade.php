{{--
    Pastilles de couleurs usuelles + Multicolore + sélecteur libre.
    $current : couleur en cours (ou null) ; $method / $pickerMethod : actions Livewire ;
    $args : arguments placés avant la couleur (ex. « 3, » pour la ligne n° 3).
--}}
@php
    $args ??= '';
    $presets = \App\Support\ColorPalette::presets();
    $isMulti = $current === \App\Support\ColorPalette::MULTICOLOR;
    $isCustom = $current !== null && ! $isMulti && ! isset($presets[$current]);
    $customHex = \App\Support\ColorPalette::hex($current);
    $selectedRing = 'ring-2 ring-offset-2 ring-(--color-primary) ring-offset-(--color-surface)';
@endphp
<div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Couleur">
    @foreach ($presets as $colorName => $colorHex)
        @php $isColor = $current === $colorName; @endphp
        <button type="button" wire:click="{{ $method }}({{ $args }} '{{ $isColor ? '' : $colorName }}')" title="{{ $colorName }}" aria-label="{{ $colorName }}" aria-pressed="{{ $isColor ? 'true' : 'false' }}"
                class="w-7 h-7 rounded-full border border-(--color-border) flex items-center justify-center transition-transform duration-150 hover:scale-110 {{ $isColor ? $selectedRing : '' }}"
                style="background-color: {{ $colorHex }}">
            @if ($isColor)
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="{{ in_array($colorName, ['Blanc', 'Jaune', 'Beige', 'Gris'], true) ? '#111' : '#fff' }}" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
            @endif
        </button>
    @endforeach
    <button type="button" wire:click="{{ $method }}({{ $args }} '{{ $isMulti ? '' : \App\Support\ColorPalette::MULTICOLOR }}')" title="Multicolore" aria-label="Multicolore" aria-pressed="{{ $isMulti ? 'true' : 'false' }}"
            class="w-7 h-7 rounded-full border border-(--color-border) flex items-center justify-center transition-transform duration-150 hover:scale-110 {{ $isMulti ? $selectedRing : '' }}"
            style="background: conic-gradient(#dc2626, #facc15, #16a34a, #2563eb, #7c3aed, #dc2626)">
        @if ($isMulti)
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
        @endif
    </button>
    {{-- Autre couleur : le sélecteur du téléphone / de l'ordinateur --}}
    <label title="Autre couleur" class="relative w-7 h-7 rounded-full border border-dashed border-(--color-text-muted) flex items-center justify-center cursor-pointer overflow-hidden transition-transform duration-150 hover:scale-110 text-(--color-text-secondary) {{ $isCustom ? $selectedRing.' border-solid' : '' }}"
           style="{{ $isCustom && $customHex ? 'background-color: '.$customHex : '' }}">
        @unless ($isCustom)
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        @endunless
        <input type="color" value="{{ $customHex ?? '#2563eb' }}" wire:change="{{ $pickerMethod }}({{ $args }} $event.target.value)" aria-label="Autre couleur"
               class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
    </label>
</div>
