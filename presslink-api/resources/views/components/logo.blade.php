{{--
    Logo PressLink (fichiers maîtres de docs/identite-presslink/logo).
    tone="auto" suit le thème clair/sombre ; tone="negatif" pour les fonds foncés.
    La hauteur se règle avec une classe sur le composant (ex. class="h-7").
--}}
@props(['tone' => 'auto', 'mark' => 'horizontal', 'alt' => 'PressLink'])
@php
    $src = fn (string $color) => asset("images/logo/presslink-{$mark}-{$color}.svg").'?v=3';
@endphp
<span {{ $attributes->class('inline-flex shrink-0') }}>
    @if ($tone === 'negatif')
        <img src="{{ $src('negatif') }}" alt="{{ $alt }}" class="h-full w-auto">
    @else
        <img src="{{ $src('couleur') }}" alt="{{ $alt }}" class="h-full w-auto dark:hidden">
        <img src="{{ $src('negatif') }}" alt="{{ $alt }}" class="h-full w-auto hidden dark:block">
    @endif
</span>
