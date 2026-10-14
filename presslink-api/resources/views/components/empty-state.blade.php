{{--
    État vide utile : ce qui se passe, pourquoi, et une action pour avancer
    (créer, réinitialiser les filtres…). Slot « actions » facultatif.
--}}
@props(['icon' => 'box', 'title', 'tone' => 'primary'])
@php
    $icons = [
        'box' => '<path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/><path d="M8.5 11h5"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M19 8v6M16 11h6"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'tag' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'store' => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>',
    ];
    [$bg, $fg] = $tone === 'success'
        ? ['var(--color-success-tint)', 'var(--color-success-text)']
        : ['var(--color-primary-tint)', 'var(--color-primary)'];
@endphp
<div {{ $attributes->merge(['class' => 'px-6 py-14 flex flex-col items-center text-center']) }}>
    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-3.5" style="background:{{ $bg }};color:{{ $fg }}">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] ?? $icons['box'] !!}</svg>
    </div>
    <p class="text-[15px] font-semibold">{{ $title }}</p>
    @if (trim($slot) !== '')
        <p class="text-sm text-(--color-text-muted) mt-1 max-w-md">{{ $slot }}</p>
    @endif
    @isset($actions)
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
