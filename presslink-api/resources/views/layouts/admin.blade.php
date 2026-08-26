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
        @endphp

        <div class="min-h-screen flex">
            <aside class="w-60 flex-none bg-(--color-surface) border-r border-(--color-border) flex flex-col py-5">
                <div class="flex items-center gap-2 px-5 pb-1">
                    <span class="font-display text-lg font-bold">Press<span class="text-(--color-primary)">Link</span></span>
                </div>
                <div class="px-5 pb-6 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Super Admin</div>

                <nav class="flex flex-col gap-0.5 px-3">
                    @foreach ($navItems as $item)
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium {{ $item['active'] ? 'bg-(--color-primary-tint) text-(--color-primary)' : 'text-(--color-text-secondary) hover:bg-(--color-bg)' }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                @foreach (explode('|', $item['icon']) as $path)
                                    <path d="{{ $path }}"></path>
                                @endforeach
                            </svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="flex-1"></div>
            </aside>

            <div class="flex-1 min-w-0 flex flex-col">
                <header class="h-16 flex-none bg-(--color-surface) border-b border-(--color-border) flex items-center justify-end gap-4 px-7">
                    <div class="text-right">
                        <div class="text-sm font-medium leading-tight">{{ $user->name }}</div>
                        <div class="text-xs text-(--color-text-muted)">Super Administrateur</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-error) hover:text-(--color-error)" title="Se déconnecter">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        </button>
                    </form>
                </header>

                <main class="flex-1 overflow-y-auto p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
