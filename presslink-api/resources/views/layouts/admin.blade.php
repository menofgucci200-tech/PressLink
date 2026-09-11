<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-(--color-bg) text-(--color-text-primary)">
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
            ];
            $userInitials = collect(explode(' ', $user->name))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
        @endphp

        <div class="min-h-screen flex">
            <aside class="w-[239px] flex-none flex flex-col py-[22px] pl-5 overflow-hidden text-white"
                   style="background:linear-gradient(180deg, #1e3a8a 0%, #1e40af 45%, #2563eb 100%);">
                <div class="font-display text-[21px] font-bold tracking-tight pr-1 pb-[6px]">Press<span class="text-(--color-primary-tint)">Link</span></div>
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
            </aside>

            <div class="flex-1 min-w-0 flex flex-col" x-data="{ scrolled: false }">
                <header class="h-16 flex-none bg-(--color-surface) border-b border-(--color-border) flex items-center gap-3 px-6 sticky top-0 z-10 transition-shadow duration-200"
                        :class="scrolled ? 'shadow-[0_4px_14px_-8px_rgba(15,23,42,.25)]' : ''">
                    <div class="flex-1"></div>

                    <div class="hidden sm:block text-right min-w-0">
                        <div class="text-sm font-semibold leading-tight truncate">{{ $user->name }}</div>
                        <div class="text-xs text-(--color-text-muted) truncate">Super Administrateur</div>
                    </div>
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold text-white" style="background:#1e3a8a;">
                        {{ $userInitials ?: '?' }}
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-error) hover:text-(--color-error)" title="Se déconnecter">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        </button>
                    </form>
                </header>

                <main class="flex-1 overflow-y-auto p-8" @scroll="scrolled = $event.target.scrollTop > 4">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
