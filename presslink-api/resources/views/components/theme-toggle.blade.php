{{-- Bouton jour / nuit (cf. components/theme-script) : lune en journée,
     soleil la nuit. Sans Alpine ni Livewire, pour servir aussi au site. --}}
<button type="button" onclick="PressLinkTheme.toggle()" data-tooltip="Mode jour / nuit" data-tooltip-pos="bottom"
        {{ $attributes->merge(['class' => 'w-9 h-9 flex-none rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary)']) }}>
    <svg class="w-4 h-4 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
    <svg class="w-4 h-4 hidden dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
    <span class="sr-only dark:hidden">Activer le mode nuit</span>
    <span class="sr-only hidden dark:inline">Activer le mode jour</span>
</button>
