@props(['variant' => 'nav'])
{{-- Bouton « Installer sur ce PC », visible seulement quand le navigateur le permet (voir <x-installable-app />). --}}
<button type="button" x-data="{ ok: !!(window.presslinkInstall && window.presslinkInstall.available()) }" x-show="ok" x-cloak
        @presslink-install.window="ok = window.presslinkInstall.available()" @click="window.presslinkInstall.prompt()"
        {{ $attributes->class([
            'nav-item w-full text-left' => $variant === 'nav',
            'inline-flex items-center gap-2 font-semibold text-(--color-primary) hover:underline' => $variant === 'link',
        ]) }}>
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="m9 8 3 3 3-3"/><path d="M12 5v6"/></svg>
    <span class="truncate">Installer sur ce PC</span>
</button>
