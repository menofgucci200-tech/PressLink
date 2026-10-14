@props(['size' => 'lg', 'message' => 'Bonjour, j\'ai besoin d\'aide avec PressLink.'])
@php
    $e164 = config('presslink.support.phone_e164');
    $whatsapp = 'https://wa.me/'.ltrim($e164, '+').'?text='.rawurlencode($message);
    $button = $size === 'sm'
        ? 'inline-flex items-center gap-2 h-10 pl-1.5 pr-4 rounded-full text-sm font-semibold'
        : 'inline-flex items-center justify-center gap-3 h-13 pl-2 pr-6 rounded-full font-semibold';
    $icon = $size === 'sm' ? 'w-7 h-7' : 'w-9 h-9';
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-3']) }}>
    <a href="tel:{{ $e164 }}" class="{{ $button }} bg-(--color-surface) border border-(--color-border) hover:border-(--color-text-primary)">
        <img src="{{ asset('images/phone.png') }}" alt="" class="{{ $icon }} rounded-full dark:bg-white" width="36" height="36" loading="lazy">
        Appeler
    </a>
    <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="{{ $button }} bg-[#25D366] text-white hover:brightness-95">
        <img src="{{ asset('images/whatsapp.png') }}" alt="" class="{{ $icon }} rounded-full ring-2 ring-white" width="36" height="36" loading="lazy">
        WhatsApp
    </a>
</div>
