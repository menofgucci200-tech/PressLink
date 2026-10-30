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
        <x-installable-app />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    @php
        $offlineUser = auth()->user();
        $offlinePressing = $offlineUser?->currentPressing();
        $offlineCounter = $offlinePressing !== null && $offlineUser->pressingHasFeature($offlinePressing, \App\Enums\Feature::Offline);
    @endphp
    <body class="bg-(--color-bg) text-(--color-text-primary)"
          @if ($offlineCounter) data-hors-ligne-pressing="{{ $offlinePressing->id }}" data-hors-ligne-user="{{ $offlineUser->id }}" @endif>
        <a href="#contenu" class="skip-link">Aller au contenu</a>
        @php
            $user = auth()->user();
            $isOverviewMode = $user->hasMultiplePressings() && ! session()->has('active_pressing_id');
            $pressing = $user->currentPressing();
            $isAdmin = $pressing && $user->isAdminOf($pressing);
            // Accès du rôle dans le pressing actif (voir App\Enums\Permission).
            $can = fn (\App\Enums\Permission $permission) => $pressing !== null && $user->may($permission, $pressing);
            $canCash = $can(\App\Enums\Permission::CashExpenses) || $can(\App\Enums\Permission::CashClose) || $can(\App\Enums\Permission::CashHistory);
            // Fonction hors formule : le propriétaire la voit avec un cadenas,
            // le lien mène à l'option sur la page Abonnement.
            $lockedFor = fn (\App\Enums\Feature $feature) => $pressing !== null && $can(\App\Enums\Permission::SubscriptionManage)
                && ! $user->pressingHasFeature($pressing, $feature);
            $optionUrl = fn (\App\Enums\Feature $feature) => route('subscription.show', ['option' => $feature->value]).'#options';
            $navItem = fn (string $key, string $route, string $label, string $icon, ?string $url = null) => [
                'key' => $key, 'route' => $route, 'label' => $label, 'icon' => $icon, 'url' => $url,
                'active' => ($active ?? null) === $key,
            ];
            $navItems = [
                $navItem('dashboard', 'dashboard', 'Dashboard', 'M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z|M9 21v-6h6v6'),
                $navItem('orders', 'orders.index', 'Commandes', 'M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z|m3.3 7 8.7 5 8.7-5|M12 22V12'),
                $navItem('clients', 'clients.index', 'Clients', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2|M9 11A4 4 0 1 0 9 3a4 4 0 0 0 0 8Z'),
                $navItem('issues', 'issues.index', 'Signalements', 'M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z|M12 9v4|M12 17h.01'),
                $canCash ? $navItem('cash', 'cash.index', 'Caisse', 'M2 7h20v12H2Z|M16 13h.01|M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2')
                    : ($lockedFor(\App\Enums\Feature::Cash) ? $navItem('cash', 'cash.index', 'Caisse', 'M2 7h20v12H2Z|M16 13h.01|M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2', $optionUrl(\App\Enums\Feature::Cash)) : null),
            ];
            $navItems = array_values(array_filter($navItems));
            // Groupe : qui administre plusieurs pressings. En vue d'ensemble, le
            // menu ne montre que les pages du groupe ; dans un pressing, un lien
            // ramène à la vue du groupe.
            $managesGroup = $user->managesGroup();
            $groupMode = $isOverviewMode && $managesGroup;
            $groupNavItems = [
                $navItem('dashboard', 'pressings.overview', "Vue d'ensemble", 'M3 3h7v9H3Z|M14 3h7v5h-7Z|M14 12h7v9h-7Z|M3 16h7v5H3Z'),
                $navItem('group-orders', 'group.orders', 'Commandes', 'M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z|m3.3 7 8.7 5 8.7-5|M12 22V12'),
                $navItem('group-cash', 'group.cash', 'Caisse', 'M2 7h20v12H2Z|M16 13h.01|M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2'),
                $navItem('group-clients', 'group.clients', 'Clients', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2|M9 11A4 4 0 1 0 9 3a4 4 0 0 0 0 8Z'),
                $navItem('group-team', 'group.team', 'Équipe', 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2|M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z|M23 21v-2a4 4 0 0 0-3-3.87|M16 3.13a4 4 0 0 1 0 7.75'),
                $navItem('group-harmonize', 'group.harmonize', 'Harmoniser', 'M4 6h16|M4 12h10|M4 18h6|m15 15 3 3 4-5'),
                $navItem('group-activity', 'group.activity', 'Journal', 'M12 8v4l3 3|M3.05 11a9 9 0 1 1 .5 4'),
                $navItem('group-reports', 'group.reports', 'Rapports', 'M3 3v18h18|m7 15 4-4 3 3 5-6'),
            ];
            $openIssuesCount = $pressing
                ? \App\Models\OrderIssue::whereHas('order', fn ($q) => $q->where('pressing_id', $pressing->id))
                    ->where('status', \App\Enums\OrderIssueStatus::Open->value)
                    ->count()
                : 0;

            $subscription = $pressing?->subscription;
            $contextLabel = $isOverviewMode ? "Vue d'ensemble" : $pressing?->name;
        @endphp

        <div class="min-h-screen flex" x-data="{ menu: false }"
             @keydown.escape.window="menu = false"
             @resize.window="if (window.innerWidth >= 1024) menu = false"
             x-effect="document.body.classList.toggle('overflow-hidden', menu)">
            {{-- Mobile/tablette : le menu devient un panneau qui glisse depuis la gauche. --}}
            <div x-show="menu" x-cloak x-transition.opacity.duration.200ms @click="menu = false"
                 class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" aria-hidden="true"></div>
            {{-- Bureau : barre fixe à la hauteur de l'écran (collée en haut pendant le
                 défilement), les menus du bas restent visibles en bas de l'écran. --}}
            <aside class="fixed inset-y-0 left-0 z-40 max-lg:-translate-x-full lg:sticky lg:top-0 lg:h-screen lg:self-start lg:z-auto w-[239px] flex-none flex flex-col py-[22px] pl-5 overflow-y-auto overflow-x-hidden text-white transition-transform duration-200 ease-out app-sidebar"
                   :class="menu && 'max-lg:translate-x-0!'"
                   @click="if ($event.target.closest('a')) menu = false">
                <div class="font-display text-[21px] font-bold tracking-tight pr-1 pb-[30px]">Press<span class="text-blue-100">Link</span></div>

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

                @if (! $managesGroup && $user->administersSeveralPressings() && $lockedFor(\App\Enums\Feature::Group))
                    <div class="pr-5 pb-3">
                        <a href="{{ $optionUrl(\App\Enums\Feature::Group) }}" wire:navigate class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/10 hover:bg-white/15 text-[13px] font-semibold">
                            Vue du groupe
                            <x-lock-icon />
                        </a>
                    </div>
                @endif
                @if ($managesGroup && ! $groupMode)
                    <div class="pr-5 pb-3">
                        <a href="{{ route('pressings.overview') }}" wire:navigate class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/10 hover:bg-white/15 text-[13px] font-semibold">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                            Vue du groupe
                        </a>
                    </div>
                @endif
                @if ($groupMode)
                    <div class="pr-5 pb-2 text-[11px] font-semibold uppercase tracking-wide text-blue-200">Groupe · {{ $user->managedPressings()->count() }} pressings</div>
                @endif

                <nav class="flex flex-col gap-1.5">
                    @foreach ($groupMode ? $groupNavItems : $navItems as $item)
                        <a href="{{ $item['url'] ?? route($item['route']) }}" wire:navigate.hover style="--nav-i:{{ $loop->index }}" class="nav-item {{ $item['active'] ? 'nav-item-active' : '' }}">
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
                            @if ($item['url'] ?? null)
                                <x-lock-icon />
                            @endif
                            @if ($item['key'] === 'issues' && $openIssuesCount > 0)
                                <span class="ml-auto inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-(--color-error) text-white text-[11px] font-semibold">
                                    {{ $openIssuesCount }}
                                </span>
                            @endif
                        </a>
                    @endforeach

                    @if (! $groupMode)
                        @php $active = $active ?? null; @endphp
                        @if ($can(\App\Enums\Permission::CatalogManage))
                        <a href="{{ route('services.index') }}" wire:navigate.hover style="--nav-i:4" class="nav-item {{ $active === 'services' ? 'nav-item-active' : '' }}">
                            @if ($active === 'services') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><circle cx="8" cy="8" r="6"></circle><path d="M18.09 10.37A6 6 0 1 1 10.34 18"></path></svg>
                            <span class="truncate">Services</span>
                        </a>
                        @endif
                        @if ($can(\App\Enums\Permission::TeamManage) || $can(\App\Enums\Permission::RolesManage))
                        <a href="{{ route('team.index') }}" wire:navigate.hover style="--nav-i:5" class="nav-item {{ $active === 'team' ? 'nav-item-active' : '' }}">
                            @if ($active === 'team') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            <span class="truncate">Équipe</span>
                        </a>
                        @endif
                        @if ($can(\App\Enums\Permission::SettingsManage))
                        <a href="{{ route('pressing.settings') }}" wire:navigate.hover style="--nav-i:6" class="nav-item {{ $active === 'settings' ? 'nav-item-active' : '' }}">
                            @if ($active === 'settings') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"></path></svg>
                            <span class="truncate">Paramètres</span>
                        </a>
                        @endif
                        @if ($can(\App\Enums\Permission::ActivityView))
                        <a href="{{ route('activity.index') }}" wire:navigate.hover style="--nav-i:7" class="nav-item {{ $active === 'activity' ? 'nav-item-active' : '' }}">
                            @if ($active === 'activity') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="M12 8v4l3 3"/><path d="M3.05 11a9 9 0 1 1 .5 4"/><path d="M3 4v5h5"/></svg>
                            <span class="truncate">Journal</span>
                        </a>
                        @elseif ($lockedFor(\App\Enums\Feature::ActivityLog))
                        <a href="{{ $optionUrl(\App\Enums\Feature::ActivityLog) }}" wire:navigate style="--nav-i:7" class="nav-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="M12 8v4l3 3"/><path d="M3.05 11a9 9 0 1 1 .5 4"/><path d="M3 4v5h5"/></svg>
                            <span class="truncate">Journal</span>
                            <x-lock-icon />
                        </a>
                        @endif
                    @endif
                </nav>

                <div class="flex-1"></div>

                <div class="pr-5">
                    {{-- Carte « Formule » masquée pour le moment, en attendant de décider
                         de l'offre à mettre en avant (passer à true pour la réafficher). --}}
                    @php $showPlanCard = false; @endphp
                    @if ($showPlanCard && $can(\App\Enums\Permission::SubscriptionManage) && $subscription)
                        @php
                            $periodEnd = $subscription->current_period_ends_at ?? $subscription->trial_ends_at;
                            $daysLeft = $periodEnd ? max(0, (int) ceil(now()->diffInDays($periodEnd, false))) : null;
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
                    <div class="border-t border-white/20 pt-3.5 flex flex-col gap-1.5">
                        <x-install-button />
                        @if ($can(\App\Enums\Permission::SubscriptionManage))
                            <a href="{{ route('subscription.show') }}" wire:navigate.hover class="nav-item {{ ($active ?? null) === 'subscription' ? 'nav-item-active' : '' }}">
                                @if (($active ?? null) === 'subscription') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" class="flex-none"><rect x="1" y="4" width="22" height="16" rx="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                <span class="truncate">Abonnement</span>
                            </a>
                        @endif
                        <a href="{{ route('account.settings') }}" wire:navigate.hover class="nav-item {{ ($active ?? null) === 'account' ? 'nav-item-active' : '' }}">
                            @if (($active ?? null) === 'account') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            <span class="truncate">Profil</span>
                        </a>
                        <a href="{{ route('support') }}" wire:navigate.hover class="nav-item {{ ($active ?? null) === 'support' ? 'nav-item-active' : '' }}">
                            @if (($active ?? null) === 'support') <span class="nav-notch nav-notch-top"></span><span class="nav-notch nav-notch-bottom"></span> @endif
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"></path></svg>
                            <span class="truncate">Support</span>
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
                    
                    {{-- Les recherches qui filtrent une liste restent sur leur page
                         (Commandes, Clients…) ; ce bouton ouvre la palette (Ctrl/⌘ + K)
                         pour aller directement à une commande, un client ou une action. --}}
                    @if ($pressing)
                        <x-palette-trigger />
                    @endif
                    <div class="flex-1"></div>

                    @if ($pressing)
                        <livewire:pressing.open-status />
                        <div class="w-px h-6 bg-(--color-border)"></div>
                    @endif

                    <x-theme-toggle />
                    <x-profile-menu :user="$user"
                                    :role="$pressing ? $user->roleLabelIn($pressing) : 'Membre'"
                                    :context="$contextLabel"
                                    :active="($active ?? null) === 'account'">
                        @if ($can(\App\Enums\Permission::SettingsManage))
                            <a href="{{ route('pressing.settings') }}" wire:navigate role="menuitem" class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-sm font-medium text-(--color-text-primary) hover:bg-(--color-bg)">
                                <svg class="w-4 h-4 flex-none text-(--color-text-muted)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.2 4.2l2.8 2.8M17 17l2.8 2.8M1 12h4M19 12h4M4.2 19.8 7 17M17 7l2.8-2.8"/></svg>
                                Paramètres du pressing
                            </a>
                        @endif
                    </x-profile-menu>
                </header>

                {{-- Horaires : en dehors de l'ouverture, l'accueil des clients est en lecture seule --}}
                @if ($pressing && $pressing->status !== \App\Enums\PressingStatus::Suspended)
                    @php
                        $closesAt = $pressing->acceptsCustomers() ? $pressing->closesAt() : null;
                    @endphp
                    @if (! $pressing->acceptsCustomers())
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 sm:px-6 lg:px-8 py-2.5 bg-(--color-bg) border-b border-(--color-border) text-[13px]" role="status">
                            <span class="flex items-center gap-2 font-semibold text-(--color-text-primary)">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                Pressing fermé{{ $pressing->nextOpeningLabel() ? ' · réouverture '.$pressing->nextOpeningLabel() : '' }}
                            </span>
                            <span class="flex-1 min-w-0 text-(--color-text-secondary)">Lecture seule pour l'accueil : pas de commande, de paiement, de remise ni d'annulation. Le travail d'atelier et la clôture de caisse restent possibles.</span>
                            @if ($can(\App\Enums\Permission::OpenExceptionally))
                                <button type="button" onclick="Livewire.dispatch('open-exceptionally', { duration: 'hour' })"
                                        class="h-8 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-[13px] font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">Ouvrir exceptionnellement</button>
                            @endif
                        </div>
                    @elseif ($pressing->isExceptionallyOpen())
                        <div class="px-4 sm:px-6 lg:px-8 py-2 bg-(--color-warning-tint) text-[13px] text-(--color-warning-text) font-medium" role="status">
                            Ouverture exceptionnelle jusqu'à {{ $pressing->exceptional_open_until->format('H:i') }} (inscrite au journal).
                        </div>
                    @elseif ($closesAt && now()->diffInMinutes($closesAt, false) <= 15)
                        <div class="px-4 sm:px-6 lg:px-8 py-2 bg-(--color-warning-tint) text-[13px] text-(--color-warning-text) font-medium" role="status">
                            Fermeture à {{ $closesAt->format('H:i') }} : terminez les commandes et les encaissements en cours.
                        </div>
                    @endif
                @endif

                @if ($pressing?->isSandbox())
                    @php
                        $sandboxLeft = now()->diff($pressing->sandbox_expires_at);
                        $sandboxLeftLabel = $sandboxLeft->days >= 1 ? $sandboxLeft->days.' j '.$sandboxLeft->h.' h' : max(1, $sandboxLeft->h).' h';
                    @endphp
                    <div class="px-4 sm:px-6 lg:px-8 py-2 text-[13px] font-medium flex flex-wrap items-center gap-x-3 gap-y-1 bg-(--color-warning-tint) text-(--color-warning-text)" role="status" data-testid="sandbox-banner">
                        <span><span class="font-bold">Pressing de test</span> : clients et commandes fictifs, aucun message n'est envoyé. Effacé dans {{ $sandboxLeftLabel }}.</span>
                        <a href="{{ route('sandbox.convert') }}" class="font-semibold underline">Créer mon vrai compte</a>
                    </div>
                @endif

                @if ($subscription?->isInTrial() && $subscription->trial_ends_at && $can(\App\Enums\Permission::SubscriptionManage) && ! in_array($active ?? null, ['subscription'], true))
                    @php $trialDaysLeft = max(0, (int) ceil(now()->diffInDays($subscription->trial_ends_at, false))); @endphp
                    <div @class([
                        'px-4 sm:px-6 lg:px-8 py-2 text-[13px] font-medium flex flex-wrap items-center gap-x-3 gap-y-1',
                        'bg-(--color-warning-tint) text-(--color-warning-text)' => $trialDaysLeft <= 3,
                        'bg-(--color-info-tint) text-(--color-info)' => $trialDaysLeft > 3,
                    ]) role="status" data-testid="trial-banner">
                        <span>Essai gratuit : {{ $trialDaysLeft <= 1 ? 'dernier jour' : 'encore '.$trialDaysLeft.' jours' }}, jusqu'au {{ $subscription->trial_ends_at->format('d/m/Y') }}.</span>
                        <a href="{{ route('subscription.plans') }}" wire:navigate class="font-semibold underline">Choisir ma formule</a>
                    </div>
                @endif

                <main id="contenu" tabindex="-1" class="flex-1 min-w-0 overflow-x-clip p-4 sm:p-6 lg:p-8 max-lg:pb-24 outline-none">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-feedback />
        @if ($pressing && $can(\App\Enums\Permission::OrdersCreate) && request()->routeIs('dashboard', 'orders.index', 'orders.show', 'clients.index', 'clients.show'))
            {{-- Téléphone : l'action la plus fréquente reste sous le pouce, même après avoir défilé --}}
            <a href="{{ route('orders.create') }}" wire:navigate.hover aria-label="Nouvelle commande"
               class="lg:hidden fixed right-4 bottom-[calc(1rem+env(safe-area-inset-bottom))] z-20 inline-flex items-center gap-2 h-14 pl-4 pr-5 rounded-full bg-(--color-primary) text-white text-[15px] font-semibold shadow-[0_8px_24px_rgba(37,99,235,.35)] active:scale-95 transition-transform">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Commande
            </a>
        @endif
        @if ($pressing)
            @php
                $shortcuts = [
                    'n' => route('orders.create'),
                    'g d' => route('dashboard'),
                    'g c' => route('orders.index'),
                    'g p' => route('orders.index', ['status' => 'prete']),
                    'g l' => route('clients.index'),
                    'g s' => route('issues.index'),
                ];
            @endphp
            <livewire:command-palette />
        @endif
        <x-shortcuts-help />
        @include('orders._share-script')
        {{-- Raccourcis du layout, lus par resources/js/app.js à chaque page. --}}
        <script type="application/json" id="shortcut-map">@json($shortcuts ?? [])</script>
        @livewireScripts
    </body>
</html>
