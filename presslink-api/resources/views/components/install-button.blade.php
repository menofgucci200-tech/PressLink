@props(['variant' => 'nav'])
{{-- Bouton « Installer PressLink » (téléphone) / « Installer sur ce PC » : invite du navigateur, ou les gestes à faire (voir <x-installable-app />). --}}
<button type="button" x-data="{ ok: !!(window.presslinkInstall && window.presslinkInstall.offer()), phone: !!(window.presslinkInstall && window.presslinkInstall.phone) }" x-show="ok" x-cloak
        @presslink-install.window="ok = window.presslinkInstall.offer()" @click="window.presslinkInstall.start()"
        {{ $attributes->class([
            'nav-item w-full text-left' => $variant === 'nav',
            'inline-flex items-center gap-2 font-semibold text-(--color-primary) hover:underline' => $variant === 'link',
        ]) }}>
    <svg x-show="!phone" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="m9 8 3 3 3-3"/><path d="M12 5v6"/></svg>
    <svg x-show="phone" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M12 6v7"/><path d="m9 10 3 3 3-3"/><path d="M11 18h2"/></svg>
    <span class="truncate" x-text="phone ? 'Installer PressLink' : 'Installer sur ce PC'">Installer sur ce PC</span>
</button>
