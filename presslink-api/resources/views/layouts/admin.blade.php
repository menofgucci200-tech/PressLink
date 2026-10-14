<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="app-debug" content="{{ config('app.debug') ? '1' : '0' }}">
        <title>{{ $title ?? config('app.name') }}</title>
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        <x-theme-script />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-(--color-bg) text-(--color-text-primary)">
        <a href="#contenu" class="skip-link">Aller au contenu</a>
        @php
            $user = auth()->user();
            $navItem = fn (string $key, string $route, string $label, string $icon) => [
                'key' => $key, 'route' => $route, 'label' => $label, 'icon' => $icon,
                'active' => ($active ?? null) === $key,
            ];
            $navItems = [
                $navItem('dashboard', 'admin.dashboard', 'Vue globale', 'M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z|M9 21v-6h6v6'),
                $navItem('pressings', 'admin.pressings.index', 'Pressings', 'M3 21h18|M5 21V7l8-4v18|M19 21V11l-6-4|M9 9h.01|M9 13h.01|M9 17h.01'),
                $navItem('orders', 'admin.orders.index', 'Commandes', 'M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z|m3.3 7 8.7 5 8.7-5|M12 22V12'),
                $navItem('clients', 'admin.clients.index', 'Clients', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2|M9 11A4 4 0 1 0 9 3a4 4 0 0 0 0 8Z'),
                $navItem('administrators', 'admin.administrators.index', 'Administrateurs', 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2|M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z|M22 21v-2a4 4 0 0 0-3-3.87|M16 3.13a4 4 0 0 1 0 7.75'),
                $navItem('users', 'admin.users.index', 'Liste des utilisateurs', 'M8 6h13|M8 12h13|M8 18h13|M3 6h.01|M3 12h.01|M3 18h.01'),
            ];
        @endphp

        <div class="min-h-screen flex" x-data="{ menu: false }"
             @keydown.escape.window="menu = false"
             @resize.window="if (window.innerWidth >= 1024) menu = false"
             x-effect="document.body.classList.toggle('overflow-hidden', menu)">
            {{-- Mobile/tablette : le menu devient un panneau qui glisse depuis la gauche. --}}
            <div x-show="menu" x-cloak x-transition.opacity.duration.200ms @click="menu = false"
                 class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" aria-hidden="true"></div>
            <aside class="fixed inset-y-0 left-0 z-40 max-lg:-translate-x-full lg:static lg:z-auto w-[239px] flex-none flex flex-col py-[22px] pl-5 overflow-y-auto lg:overflow-hidden text-white transition-transform duration-200 ease-out app-sidebar"
                   :class="menu && 'max-lg:translate-x-0!'"
                   @click="if ($event.target.closest('a')) menu = false">
                <div class="font-display text-[21px] font-bold tracking-tight pr-1 pb-[6px]">Press<span class="text-blue-100">Link</span></div>
                <div class="pr-5 pb-[24px] text-[11px] font-semibold uppercase tracking-wide text-blue-200">Super Admin</div>

                <nav class="flex flex-col gap-1.5">
                    @foreach ($navItems as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate style="--nav-i:{{ $loop->index }}" class="nav-item {{ $item['active'] ? 'nav-item-active' : '' }}">
                            @if ($item['active'])
                                <span class="nav-notch nav-notch-top"></span>
                                <span class="nav-notch nav-notch-bottom"></span>
                            @endif
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none">
                                @foreach (explode('|', $item['icon']) as $path)
                                    <path d="{{ $path }}"></path>
                                @endforeach
                            </svg>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="flex-1"></div>

                <div class="pr-5 pt-6">
                    <div class="border-t border-white/20 pt-3.5">
                        <a href="{{ route('account.settings') }}" wire:navigate class="nav-item {{ ($active ?? null) === 'account' ? 'nav-item-active' : '' }}">
                            @if (($active ?? null) === 'account') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            <span class="truncate">Mon profil</span>
                        </a>
                    </div>
                </div>
            </aside>

            {{-- La page défile (pas le main) : les en-têtes de tableaux collants restent sous la barre du haut. --}}
            <div class="flex-1 min-w-0 flex flex-col" x-data="{ scrolled: window.scrollY > 4 }" @scroll.window="scrolled = window.scrollY > 4">
                <header class="h-16 flex-none bg-(--color-surface) border-b border-(--color-border) flex items-center gap-3 px-4 sm:px-6 sticky top-0 z-10 transition-shadow duration-200"
                        :class="scrolled ? 'shadow-[0_4px_14px_-8px_rgba(15,23,42,.25)]' : ''">
                    <button type="button" @click="menu = true" :aria-expanded="menu" aria-label="Ouvrir le menu"
                            class="lg:hidden -ml-1 w-10 h-10 flex-none rounded-lg flex items-center justify-center text-(--color-text-secondary) hover:bg-(--color-bg)">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="17" x2="20" y2="17"></line></svg>
                    </button>

                    <x-palette-trigger />
                    <div class="flex-1"></div>

                    <x-theme-toggle />
                    <x-profile-menu :user="$user" role="Super Administrateur" :active="($active ?? null) === 'account'" />
                </header>

                <main id="contenu" tabindex="-1" class="flex-1 min-w-0 overflow-x-clip p-4 sm:p-6 lg:p-8 outline-none">
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
