@props(['kind' => 'piece', 'name' => null, 'size' => 48])
{{-- Pastille blanche + dessin de ServiceIcons : lisible en mode clair comme en mode nuit. --}}
<span {{ $attributes->merge(['class' => 'inline-flex flex-none items-center justify-center rounded-2xl bg-white ring-1 ring-black/5']) }}
      style="width: {{ (int) round($size * 1.28) }}px; height: {{ (int) round($size * 1.28) }}px">
    {!! \App\Support\ServiceIcons::svg($kind, $name, (int) $size) !!}
</span>
