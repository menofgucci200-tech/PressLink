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
            $isOverviewMode = $user->hasMultiplePressings() && ! session()->has('active_pressing_id');
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

            $subscription = $pressing?->subscription;
            $userInitials = collect(explode(' ', $user->name))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
        @endphp

        <div class="min-h-screen flex">
            <aside class="w-[239px] flex-none flex flex-col py-[22px] pl-5 overflow-hidden text-white"
                   style="background:linear-gradient(180deg, #1e3a8a 0%, #1e40af 45%, #2563eb 100%);">
                <div class="font-display text-[21px] font-bold tracking-tight pr-1 pb-[30px]">Press<span class="text-(--color-primary-tint)">Link</span></div>

                @if ($user->hasMultiplePressings())
                    <div class="pr-5 pb-4">
                        <details class="relative group">
                            <summary class="cursor-pointer list-none flex items-center justify-between gap-2 px-3 py-2 rounded-lg border border-white/20 bg-white/10 text-sm font-medium hover:bg-white/15">
                                <span class="truncate">{{ $isOverviewMode ? "Vue d'ensemble" : $pressing?->name }}</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="m6 9 6 6 6-6"></path></svg>
                            </summary>
                            <div class="absolute left-0 right-0 mt-1 bg-(--color-surface) text-(--color-text-primary) border border-(--color-border) rounded-lg shadow-lg py-1 z-10">
                                <a href="{{ route('pressings.overview') }}" wire:navigate
                                   class="block px-3 py-2 text-sm transition-colors duration-150 {{ $isOverviewMode ? 'text-(--color-primary) font-medium' : 'text-(--color-text-secondary)' }} hover:bg-(--color-bg)">
                                    Vue d'ensemble
                                </a>
                                <div class="h-px bg-(--color-border) my-1"></div>
                                @foreach ($user->activePressings() as $p)
                                    <a href="{{ route('pressings.switch', $p) }}" wire:navigate
                                       class="block px-3 py-2 text-sm truncate transition-colors duration-150 {{ ! $isOverviewMode && $pressing?->id === $p->id ? 'text-(--color-primary) font-medium' : 'text-(--color-text-secondary)' }} hover:bg-(--color-bg)">
                                        {{ $p->name }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    </div>
                @endif

                <nav class="flex flex-col gap-1.5">
                    @foreach ($navItems as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate class="nav-item {{ $item['active'] ? 'nav-item-active' : '' }}">
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
                            @if ($item['key'] === 'issues' && $openIssuesCount > 0)
                                <span class="ml-auto inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-(--color-error) text-white text-[11px] font-semibold">
                                    {{ $openIssuesCount }}
                                </span>
                            @endif
                        </a>
                    @endforeach

                    @if ($isAdmin)
                        @php $active = $active ?? null; @endphp
                        <a href="{{ route('services.index') }}" wire:navigate class="nav-item {{ $active === 'services' ? 'nav-item-active' : '' }}">
                            @if ($active === 'services') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><circle cx="8" cy="8" r="6"></circle><path d="M18.09 10.37A6 6 0 1 1 10.34 18"></path></svg>
                            <span class="truncate">Tarifs</span>
                        </a>
                        <a href="{{ route('team.index') }}" wire:navigate class="nav-item {{ $active === 'team' ? 'nav-item-active' : '' }}">
                            @if ($active === 'team') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <span class="truncate">Équipe</span>
                        </a>
                        <a href="{{ route('pressing.settings') }}" wire:navigate class="nav-item {{ $active === 'settings' ? 'nav-item-active' : '' }}">
                            @if ($active === 'settings') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"></path></svg>
                            <span class="truncate">Paramètres</span>
                        </a>
                    @endif
                </nav>

                <div class="flex-1"></div>

                <div class="pr-5">
                    @if ($isAdmin && $subscription)
                        @php
                            $periodEnd = $subscription->current_period_ends_at ?? $subscription->trial_ends_at;
                            $daysLeft = $periodEnd ? max(0, now()->diffInDays($periodEnd, false)) : null;
                            $periodStart = $subscription->current_period_starts_at ?? $subscription->trial_ends_at?->subDays(14);
                            $totalDays = ($periodStart && $periodEnd) ? max(1, $periodStart->diffInDays($periodEnd)) : 1;
                            $elapsedPct = $periodEnd ? min(100, max(0, round((1 - ($daysLeft / $totalDays)) * 100))) : 0;
                        @endphp
                        <div class="bg-white/10 border border-white/15 rounded-xl p-3.5 mb-3.5">
                            <div class="flex justify-between items-baseline mb-2">
                                <div class="text-[12.5px] font-bold text-white">Formule {{ $subscription->plan->label() }}</div>
                                @if ($daysLeft !== null)
                                    <div class="text-[11px] text-blue-200">{{ $daysLeft }} j</div>
                                @endif
                            </div>
                            <div class="h-[5px] bg-white/20 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-300 rounded-full" style="width:{{ $elapsedPct }}%"></div>
                            </div>
                        </div>
                    @endif
                    @if ($isAdmin)
                        <div class="border-t border-white/20 pt-3.5">
                            <a href="{{ route('subscription.show') }}" wire:navigate class="nav-item {{ ($active ?? null) === 'subscription' ? 'nav-item-active' : '' }}">
                                @if (($active ?? null) === 'subscription') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" class="flex-none"><rect x="1" y="4" width="22" height="16" rx="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                <span class="truncate">Abonnement</span>
                            </a>
                        </div>
                    @endif
                </div>
            </aside>

            <div class="flex-1 min-w-0 flex flex-col" x-data="{ scrolled: false }">
                <header class="h-16 flex-none bg-(--color-surface) border-b border-(--color-border) flex items-center gap-3 px-6 sticky top-0 z-10 transition-shadow duration-200"
                        :class="scrolled ? 'shadow-[0_4px_14px_-8px_rgba(15,23,42,.25)]' : ''">
                    <div class="hidden md:flex flex-1 min-w-0 max-w-[360px] items-center gap-2 bg-(--color-bg) border border-(--color-border) rounded-lg px-3 py-2.5 text-(--color-text-muted)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                        <span class="text-[13.5px] flex-1 truncate">Rechercher un n° ou un client…</span>
                        <span class="text-[10px] border border-(--color-border) rounded px-1 py-px">⌘K</span>
                    </div>
                    <div class="flex-1"></div>

                    @if ($pressing)
                        @php $isOpen = $pressing->status === \App\Enums\PressingStatus::Active; @endphp
                        <div class="flex items-center gap-1.5 rounded-full pl-2.5 pr-3 py-1.5 {{ $isOpen ? 'bg-(--color-success-tint)' : 'bg-(--color-error-tint)' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isOpen ? 'bg-(--color-success) animate-pulse-dot' : 'bg-(--color-error)' }}"></span>
                            <span class="text-xs font-semibold {{ $isOpen ? 'text-(--color-success-text)' : 'text-(--color-error)' }}">{{ $isOpen ? 'Ouvert' : 'Suspendu' }}</span>
                        </div>
                        <div class="w-px h-6 bg-(--color-border)"></div>
                    @endif

                    <a href="{{ route('account.settings') }}" wire:navigate class="hidden sm:block text-right min-w-0 hover:opacity-70 transition-opacity duration-150">
                        <div class="text-sm font-semibold leading-tight truncate">{{ $user->name }}</div>
                        <div class="text-xs text-(--color-text-muted) truncate">{{ $isAdmin ? 'Administrateur' : 'Employé' }} · {{ $isOverviewMode ? "Vue d'ensemble" : $pressing?->name }}</div>
                    </a>
                    <a href="{{ route('account.settings') }}" wire:navigate
                       class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold text-white transition-transform duration-100 active:scale-90 {{ ($active ?? null) === 'account' ? 'ring-2 ring-offset-1 ring-(--color-primary)' : '' }}"
                       style="background:#1e3a8a;" title="Mon compte">
                        {{ $userInitials ?: '?' }}
                    </a>
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
