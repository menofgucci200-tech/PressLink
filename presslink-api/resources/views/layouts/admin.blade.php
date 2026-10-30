<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="app-debug" content="{{ config('app.debug') ? '1' : '0' }}">
        <title>{{ $title ?? config('app.name') }}</title>
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">
        <x-theme-script />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    {{-- Fond légèrement bleuté, comme un back-office : les cartes blanches s'en détachent. --}}
    <body class="bg-[#F1F6FA] dark:bg-(--color-bg) text-(--color-text-primary)">
        <a href="#contenu" class="skip-link">Aller au contenu</a>
        @php
            $user = auth()->user();
            $navItem = fn (string $key, string $route, string $label, int $badge = 0) => [
                'key' => $key, 'route' => $route, 'label' => $label,
                'active' => ($active ?? null) === $key, 'badge' => $badge,
            ];
            // Console à la manière d'un back-office : barre latérale claire, liens en texte, rubriques.
            $navSections = [
                'Administration' => [
                    $navItem('dashboard', 'admin.dashboard', 'Vue globale'),
                    $navItem('pressings', 'admin.pressings.index', 'Pressings'),
                    $navItem('orders', 'admin.orders.index', 'Commandes'),
                    $navItem('clients', 'admin.clients.index', 'Clients'),
                    $navItem('payments', 'admin.payments.index', 'Paiements'),
                    $navItem('requests', 'admin.requests.index', 'Demandes à traiter', \App\Models\SubscriptionRequest::pending()->count()),
                ],
                'Comptes' => [
                    $navItem('administrators', 'admin.administrators.index', 'Administrateurs'),
                    $navItem('users', 'admin.users.index', 'Liste des utilisateurs'),
                    $navItem('roles', 'admin.roles.index', 'Rôles et accès'),
                ],
                'Offre et site' => [
                    $navItem('plans', 'admin.plans.index', 'Formules et prix'),
                    $navItem('services', 'admin.services.index', 'Services'),
                    $navItem('site', 'admin.site.index', 'Site vitrine'),
                ],
            ];
            $navLink = fn (bool $isActive) => 'flex items-center gap-2 rounded-r-lg border-l-4 px-3 py-2 text-sm transition-colors '.($isActive
                ? 'border-(--color-success) bg-(--color-primary-tint) font-semibold text-(--color-primary)'
                : 'border-transparent text-(--color-text-secondary) hover:bg-(--color-bg) hover:text-(--color-text-primary)');
        @endphp

        <div class="min-h-screen flex" x-data="{ menu: false }"
             @keydown.escape.window="menu = false"
             @resize.window="if (window.innerWidth >= 1024) menu = false"
             x-effect="document.body.classList.toggle('overflow-hidden', menu)">
            {{-- Mobile/tablette : le menu devient un panneau qui glisse depuis la gauche. --}}
            <div x-show="menu" x-cloak x-transition.opacity.duration.200ms @click="menu = false"
                 class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" aria-hidden="true"></div>
            {{-- Bureau : barre fixe à la hauteur de l'écran, collée en haut pendant le défilement. --}}
            <aside class="fixed inset-y-0 left-0 z-40 max-lg:-translate-x-full lg:sticky lg:top-0 lg:h-screen lg:self-start lg:z-auto w-60 flex-none flex flex-col overflow-y-auto overflow-x-hidden border-r border-(--color-border) bg-(--color-surface) transition-transform duration-200 ease-out"
                   :class="menu && 'max-lg:translate-x-0!'"
                   @click="if ($event.target.closest('a')) menu = false">
                <div class="flex justify-center px-4 pb-4 pt-6">
                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex flex-col items-center gap-1 rounded-xl border border-(--color-border) bg-(--color-surface) px-6 py-3 shadow-sm">
                        <img src="{{ asset('images/logo-presslink.png') }}" alt="" width="44" height="44" class="w-11 h-11">
                        <span class="font-display text-lg font-bold tracking-tight leading-none">Press<span class="text-(--color-primary)">Link</span></span>
                        <span class="text-[9px] font-semibold uppercase tracking-widest text-(--color-text-muted)">Console super admin</span>
                    </a>
                </div>

                <nav aria-label="Console" class="flex flex-col gap-5 pb-4 pr-3">
                    @foreach ($navSections as $section => $items)
                        <div>
                            <div class="px-5 pb-2 text-[11px] font-semibold uppercase tracking-widest text-(--color-text-muted)">{{ $section }}</div>
                            <div class="flex flex-col gap-0.5">
                                @foreach ($items as $item)
                                    <a href="{{ route($item['route']) }}" wire:navigate.hover @if ($item['active']) aria-current="page" @endif class="{{ $navLink($item['active']) }}">
                                        <span class="truncate">{{ $item['label'] }}</span>
                                        @if ($item['badge'] > 0)
                                            <span class="ml-auto inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-(--color-warning-tint) text-(--color-warning-text) text-[11px] font-bold tabular-nums" aria-label="{{ $item['badge'] }} à traiter">{{ $item['badge'] }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="flex-1"></div>

                <div class="border-t border-(--color-border) py-3 pr-3">
                    <a href="{{ route('account.settings') }}" wire:navigate class="{{ $navLink(($active ?? null) === 'account') }}">
                        <span class="truncate">Mon profil</span>
                    </a>
                </div>
            </aside>

            {{-- La page défile (pas le main) : les en-têtes de tableaux collants restent sous la barre du haut. --}}
            <div class="flex-1 min-w-0 flex flex-col" x-data="{ scrolled: window.scrollY > 4 }" @scroll.window="scrolled = window.scrollY > 4">
                <header class="flex-none bg-(--color-surface) border-b border-(--color-border) flex items-center gap-3 px-4 py-2.5 sticky top-0 z-10 transition-shadow duration-200"
                        :class="scrolled ? 'shadow-[0_4px_14px_-8px_rgba(15,23,42,.25)]' : ''">
                    <button type="button" @click="menu = true" :aria-expanded="menu" aria-label="Ouvrir le menu"
                            class="lg:hidden -ml-1 w-10 h-10 flex-none rounded-lg flex items-center justify-center text-(--color-text-secondary) hover:bg-(--color-bg)">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="17" x2="20" y2="17"></line></svg>
                    </button>

                    <x-palette-trigger />
                    <div class="flex-1"></div>

                    <a href="{{ route('account.settings') }}" wire:navigate class="hidden sm:block text-xs font-semibold uppercase tracking-wide text-(--color-text-secondary) hover:text-(--color-primary) truncate max-w-48" title="Mon profil">{{ $user->name }}</a>
                    <x-theme-toggle />
                    <form novalidate method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="h-9 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm font-medium hover:border-(--color-error) hover:text-(--color-error)">Déconnexion</button>
                    </form>
                </header>

                <main id="contenu" tabindex="-1" class="flex-1 min-w-0 overflow-x-clip p-4 md:p-6 outline-none">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-feedback />
        @php
            $shortcuts = [
                'n' => route('admin.pressings.create'),
                'g d' => route('admin.dashboard'),
                'g p' => route('admin.pressings.index'),
                'g c' => route('admin.orders.index'),
                'g l' => route('admin.clients.index'),
            ];
        @endphp
        <livewire:command-palette :admin="true" />
        <x-shortcuts-help :admin="true" />
        {{-- Raccourcis du layout, lus par resources/js/app.js à chaque page. --}}
        <script type="application/json" id="shortcut-map">@json($shortcuts ?? [])</script>
        @livewireScripts
    </body>
</html>
