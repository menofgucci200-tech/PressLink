{{--
    Palette de commandes : Ctrl/⌘ + K ou « / » (voir resources/js/app.js pour
    les raccourcis et la navigation au clavier : ↑ ↓ Entrée, Échap).
--}}
@php
    $icons = [
        'order' => '<path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'client' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'pressing' => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'home' => '<path d="m3 11 9-8 9 8"/><path d="M5 10v11h14V10"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'money' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
        'flag' => '<path d="M4 22V4"/><path d="M4 4h12l-2 4 2 4H4"/>',
        'tag' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'team' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4a3.5 3.5 0 0 1 0 7"/><path d="M22 21a7 7 0 0 0-4-6.3"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M6 21v-1a6 6 0 0 1 12 0v1"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'theme' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
        'keyboard' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10"/>',
    ];
@endphp
<div data-palette x-data="{ open: false }"
     @open-palette.window="open = true; $nextTick(() => { $refs.input.focus(); $refs.input.select(); window.PressLinkPalette?.reset($el) })"
     @close-palette.window="open = false"
     @keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-[70]" role="dialog" aria-modal="true" aria-label="Rechercher ou lancer une action">
    <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" @click="open = false" aria-hidden="true"></div>

    <div class="relative mx-auto mt-[10vh] w-[calc(100%-32px)] max-w-xl bg-(--color-surface) border border-(--color-border) rounded-2xl shadow-2xl overflow-hidden animate-step">
        <div class="flex items-center gap-3 px-4 border-b border-(--color-border)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="flex-none text-(--color-text-muted)" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input x-ref="input" data-palette-input type="text" wire:model.live.debounce.150ms="query"
                   placeholder="{{ $admin ? 'Pressing, n° de commande, client… ou une action' : 'N° de commande, client, téléphone… ou une action' }}"
                   autocomplete="off" spellcheck="false" aria-label="Rechercher"
                   class="flex-1 min-w-0 h-14 bg-transparent text-[15px] text-(--color-text-primary) placeholder:text-(--color-text-muted) outline-none">
            <span wire:loading wire:target="query" class="text-xs text-(--color-text-muted)">…</span>
            <kbd class="kbd">Échap</kbd>
        </div>

        <div class="max-h-[56vh] overflow-y-auto py-2" data-palette-results role="listbox">
            @forelse ($groups as $group)
                <div class="px-4 pt-2.5 pb-1 text-[10.5px] font-semibold uppercase tracking-wide text-(--color-text-muted)">{{ $group['label'] }}</div>
                @foreach ($group['items'] as $item)
                    @php
                        $content = '<span class="palette-icon">'
                            .'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icons[$item['icon']] ?? $icons['order']).'</svg>'
                            .'</span>';
                    @endphp
                    @if ($item['url'])
                        <a href="{{ $item['url'] }}" wire:navigate data-palette-item role="option" wire:key="pi-{{ md5($item['url'].$item['title']) }}" class="palette-item">
                            {!! $content !!}
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium truncate">{{ $item['title'] }}</span>
                                @if ($item['subtitle'])
                                    <span class="block text-xs text-(--color-text-muted) truncate">{{ $item['subtitle'] }}</span>
                                @endif
                            </span>
                            @isset($item['hint'])
                                <kbd class="kbd">{{ $item['hint'] }}</kbd>
                            @endisset
                        </a>
                    @else
                        <button type="button" data-palette-item data-palette-action="{{ $item['action'] }}" role="option" wire:key="pa-{{ $item['action'] }}" class="palette-item w-full text-left">
                            {!! $content !!}
                            <span class="flex-1 min-w-0 text-sm font-medium">{{ $item['title'] }}</span>
                            @isset($item['hint'])
                                <kbd class="kbd">{{ $item['hint'] }}</kbd>
                            @endisset
                        </button>
                    @endif
                @endforeach
            @empty
                <div class="px-6 py-10 text-center">
                    <p class="text-sm font-medium">Aucun résultat pour « {{ $query }} »</p>
                    <p class="text-xs text-(--color-text-muted) mt-1">{{ $admin ? 'Essayez un nom de pressing, un code (PE-1234), un n° de commande ou un téléphone.' : 'Essayez un n° de commande (PL-…), un nom ou un numéro de téléphone.' }}</p>
                </div>
            @endforelse
        </div>

        <div class="flex items-center gap-4 px-4 py-2.5 border-t border-(--color-border) bg-(--color-bg) text-[11px] text-(--color-text-muted)">
            <span><kbd class="kbd">↑</kbd> <kbd class="kbd">↓</kbd> naviguer</span>
            <span><kbd class="kbd">Entrée</kbd> ouvrir</span>
            <span class="ml-auto hidden sm:inline"><kbd class="kbd">?</kbd> tous les raccourcis</span>
        </div>
    </div>
</div>
