{{-- Aide des raccourcis clavier : touche « ? » ou palette > Raccourcis clavier. --}}
@props(['admin' => false])
@php
    $groups = [
        'Partout' => [
            ['Ctrl K  ou  /', 'Rechercher une commande, un client… ou une action'],
            ['?', 'Afficher cette aide'],
            ['Échap', 'Fermer la fenêtre ou le panneau ouvert'],
        ],
        'Aller à' => $admin ? [
            ['G  puis  D', 'Vue globale'],
            ['G  puis  P', 'Pressings'],
            ['G  puis  C', 'Commandes'],
            ['G  puis  L', 'Clients'],
            ['N', 'Nouveau pressing'],
        ] : [
            ['N', 'Nouvelle commande'],
            ['G  puis  D', 'Tableau de bord'],
            ['G  puis  C', 'Commandes'],
            ['G  puis  P', 'Commandes prêtes'],
            ['G  puis  L', 'Clients'],
            ['G  puis  S', 'Signalements'],
        ],
        'Dans les listes' => [
            ['Clic', $admin ? 'Ouvrir le détail' : 'Aperçu de la commande'],
            ['Ctrl + clic', 'Ouvrir dans un nouvel onglet'],
            ['Tab  puis  Entrée', 'Choisir une ligne au clavier et l\'ouvrir'],
            ['↑  ↓', 'Commande précédente / suivante dans l\'aperçu'],
        ],
    ];
@endphp
<div data-shortcuts-help x-data="{ open: false }" @open-shortcuts.window="open = true" @keydown.escape.window="open = false"
     x-show="open" x-cloak class="fixed inset-0 z-[75] flex items-start justify-center p-4" role="dialog" aria-modal="true" aria-label="Raccourcis clavier">
    <div class="absolute inset-0 bg-slate-900/45" @click="open = false" aria-hidden="true"></div>
    <div class="relative mt-[8vh] w-full max-w-lg bg-(--color-surface) border border-(--color-border) rounded-2xl shadow-2xl animate-step" x-trap.noscroll="open">
        <div class="flex items-center justify-between px-5 py-4 border-b border-(--color-border)">
            <h2 class="font-semibold">Raccourcis clavier</h2>
            <button type="button" @click="open = false" aria-label="Fermer" data-tooltip="Fermer (Échap)"
                    class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="px-5 py-4 space-y-5 max-h-[65vh] overflow-y-auto">
            @foreach ($groups as $title => $shortcuts)
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">{{ $title }}</div>
                    <dl class="space-y-2">
                        @foreach ($shortcuts as [$keys, $description])
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <dt class="text-(--color-text-secondary)">{{ $description }}</dt>
                                <dd class="flex-none flex items-center gap-1">
                                    @foreach (preg_split('/\s{2,}/', $keys) as $part)
                                        @if (in_array($part, ['puis', 'ou'], true))
                                            <span class="text-xs text-(--color-text-muted)">{{ $part }}</span>
                                        @else
                                            <kbd class="kbd">{{ $part }}</kbd>
                                        @endif
                                    @endforeach
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
    </div>
</div>
