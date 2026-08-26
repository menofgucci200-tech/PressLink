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
            $pressing = $user->currentPressing();
            $isAdmin = $pressing && $user->isAdminOf($pressing);
            $navItem = fn (string $key, string $route, string $label, string $icon) => [
                'key' => $key, 'route' => $route, 'label' => $label, 'icon' => $icon,
                'active' => ($active ?? null) === $key,
            ];
            $navItems = [
                $navItem('dashboard', 'dashboard', 'Dashboard', 'M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z|M9 21v-6h6v6'),
                $navItem('orders', 'orders.index', 'Commandes', 'M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z|m3.3 7 8.7 5 8.7-5|M12 22V12'),
                $navItem('clients', 'clients.index', 'Clients', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2|M9 11A4 4 0 1 0 9 3a4 4 0 0 0 0 8Z'),
                $navItem('issues', 'issues.index', 'Signalements', 'M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z|M12 9v4|M12 17h.01'),
            ];
            $openIssuesCount = $pressing
                ? \App\Models\OrderIssue::whereHas('order', fn ($q) => $q->where('pressing_id', $pressing->id))
                    ->where('status', \App\Enums\OrderIssueStatus::Open->value)
                    ->count()
                : 0;
        @endphp

        <div class="min-h-screen flex">
            <aside class="w-60 flex-none bg-(--color-surface) border-r border-(--color-border) flex flex-col py-5">
                <div class="flex items-center gap-2 px-5 pb-6">
                    <span class="font-display text-lg font-bold">Press<span class="text-(--color-primary)">Link</span></span>
                </div>

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
                            @if ($item['key'] === 'issues' && $openIssuesCount > 0)
                                <span class="ml-auto inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-(--color-error) text-white text-[11px] font-semibold">
                                    {{ $openIssuesCount }}
                                </span>
                            @endif
                        </a>
                    @endforeach

                    @if ($isAdmin)
                        <a href="{{ route('services.index') }}"
                           class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium {{ ($active ?? null) === 'services' ? 'bg-(--color-primary-tint) text-(--color-primary)' : 'text-(--color-text-secondary) hover:bg-(--color-bg)' }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6"></circle><path d="M18.09 10.37A6 6 0 1 1 10.34 18"></path></svg>
                            Tarifs
                        </a>
                        <a href="{{ route('team.index') }}"
                           class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium {{ ($active ?? null) === 'team' ? 'bg-(--color-primary-tint) text-(--color-primary)' : 'text-(--color-text-secondary) hover:bg-(--color-bg)' }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            Équipe
                        </a>
                        <a href="{{ route('pressing.settings') }}"
                           class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium {{ ($active ?? null) === 'settings' ? 'bg-(--color-primary-tint) text-(--color-primary)' : 'text-(--color-text-secondary) hover:bg-(--color-bg)' }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"></path></svg>
                            Paramètres
                        </a>
                    @endif
                </nav>

                <div class="flex-1"></div>

                @if ($isAdmin)
                    <div class="px-3">
                        <div class="h-px bg-(--color-border) mx-3 mb-3"></div>
                        <a href="{{ route('subscription.show') }}"
                           class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium {{ ($active ?? null) === 'subscription' ? 'bg-(--color-primary-tint) text-(--color-primary)' : 'text-(--color-text-secondary) hover:bg-(--color-bg)' }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><rect x="1" y="4" width="22" height="16" rx="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                            Abonnement
                        </a>
                    </div>
                @endif
            </aside>

            <div class="flex-1 min-w-0 flex flex-col">
                <header class="h-16 flex-none bg-(--color-surface) border-b border-(--color-border) flex items-center justify-end gap-4 px-7">
                    <div class="text-right">
                        <div class="text-sm font-medium leading-tight">{{ $user->name }}</div>
                        <div class="text-xs text-(--color-text-muted)">{{ $isAdmin ? 'Administrateur' : 'Employé' }} · {{ $pressing?->name }}</div>
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
