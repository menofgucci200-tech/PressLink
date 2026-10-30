{{-- Ouvre la palette de commandes (même effet que Ctrl/⌘ + K). --}}
<button type="button" onclick="window.dispatchEvent(new CustomEvent('open-palette'))"
        data-tooltip="Rechercher (Ctrl K)" data-tooltip-pos="bottom" aria-label="Rechercher une commande, un client ou une action"
        {{ $attributes->merge(['class' => 'group inline-flex items-center justify-center sm:justify-start gap-2 h-9 w-9 sm:w-64 px-0 sm:px-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-(--color-text-muted) text-[13px] hover:border-(--color-primary) hover:text-(--color-text-secondary) transition-colors duration-150']) }}>
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="flex-none" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
    <span class="hidden sm:inline flex-1 text-left truncate">Rechercher…</span>
    {{-- Raccourci clavier : seulement sur grand écran (la classe .kbd imposerait son affichage sur téléphone) --}}
    <span class="hidden sm:inline-flex"><kbd class="kbd">Ctrl K</kbd></span>
</button>
