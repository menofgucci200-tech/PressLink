{{--
    Icônes « Télécharger » et « Partager » d'un reçu (PDF).
    <x-receipt-actions :download="$pdfUrl" :share="['url' => …, 'text' => …, 'file' => …, 'phone' => …]" />
--}}
@props(['download', 'share', 'label' => 'le reçu', 'size' => 'md'])
@php
    $box = $size === 'sm' ? 'w-7 h-7 rounded-md' : 'w-9 h-9 rounded-lg border border-(--color-border)';
    $icon = $size === 'sm' ? 14 : 16;
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1']) }} x-data="{ share: @js($share) }">
    <a href="{{ $download }}" download aria-label="Télécharger {{ $label }}" data-tooltip="Télécharger {{ $label }} (PDF)"
       class="{{ $box }} flex-none flex items-center justify-center text-(--color-text-secondary) hover:text-(--color-primary) hover:bg-(--color-primary-tint)">
        <svg width="{{ $icon }}" height="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg>
    </a>
    <button type="button" aria-label="Partager {{ $label }}" data-tooltip="Partager {{ $label }}"
            @pointerenter.once="presslinkShare.prefetch(share)" @touchstart.once.passive="presslinkShare.prefetch(share)" @focus.once="presslinkShare.prefetch(share)"
            @click="presslinkShare(share)"
            class="{{ $box }} flex-none flex items-center justify-center text-(--color-text-secondary) hover:text-(--color-primary) hover:bg-(--color-primary-tint)">
        <svg width="{{ $icon }}" height="{{ $icon }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><path d="m8.6 13.5 6.8 4"></path><path d="m15.4 6.5-6.8 4"></path></svg>
    </button>
</span>
