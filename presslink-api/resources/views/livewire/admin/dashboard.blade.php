@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $periodLabel = match ($period) {
        'today' => "aujourd'hui",
        '7' => 'sur 7 jours',
        'month' => 'ce mois-ci',
        'all' => 'depuis le début',
        default => 'sur 30 jours',
    };
    $filterKey = $period.'-'.$city.'-'.$administratorId;
    $tones = [
        'error' => ['bg' => 'var(--color-error-tint)', 'fg' => 'var(--color-error)'],
        'warning' => ['bg' => 'var(--color-warning-tint)', 'fg' => 'var(--color-warning-text)'],
        'info' => ['bg' => 'var(--color-info-tint)', 'fg' => 'var(--color-info)'],
    ];
    $alerts = collect($watchlist)->where('count', '>', 0);
@endphp

<div>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Vue globale</h1>
            <p class="text-sm text-(--color-text-muted)">{{ ucfirst(now()->translatedFormat('l j F Y')) }}</p>
        </div>
        <a href="{{ route('admin.pressings.create') }}" wire:navigate class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Nouveau pressing
        </a>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-6">
        <select wire:model.live="period" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
            <option value="today">Aujourd'hui</option>
            <option value="7">7 derniers jours</option>
            <option value="30">30 derniers jours</option>
            <option value="month">Ce mois-ci</option>
            <option value="all">Toute la période</option>
        </select>
        <select wire:model.live="city" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
            <option value="">Toutes les villes</option>
            @foreach ($cities as $c)
                <option value="{{ $c }}">{{ $c }}</option>
            @endforeach
        </select>
        <select wire:model.live="administratorId" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
            <option value="">Tous les administrateurs</option>
            @foreach ($administrators as $admin)
                <option value="{{ $admin->id }}">{{ $admin->name }}</option>
            @endforeach
        </select>
        @if ($city !== '' || $administratorId !== '' || $period !== '30')
            <button wire:click="resetFilters" class="text-xs font-medium text-(--color-text-muted) hover:text-(--color-primary) underline">
                Réinitialiser les filtres
            </button>
        @endif
        <span wire:loading.delay class="text-xs text-(--color-text-muted)">Mise à jour…</span>
    </div>

    {{-- ===== Indicateurs clés ===== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
        <div wire:key="kpi-mrr-{{ $filterKey }}" class="rounded-xl p-5 text-white animate-step sm:col-span-2 xl:col-span-1" style="background:#0f172a;">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Revenu mensuel récurrent</div>
            <div class="flex items-baseline gap-1.5 mt-2.5">
                <div class="text-3xl font-bold tracking-tight tabular-nums">{{ $fcfa($monthlyRecurringRevenue) }}</div>
                <div class="text-sm font-semibold text-(--color-text-muted)">F</div>
            </div>
            <div class="text-xs text-(--color-text-muted) mt-1.5">
                <div>{{ $payingPressings }} {{ $payingPressings > 1 ? 'abonnements actifs' : 'abonnement actif' }}</div>
                <div class="whitespace-nowrap">soit {{ $fcfa($monthlyRecurringRevenue * 12) }} F / an</div>
            </div>
        </div>

        @php
            $kpis = [
                [
                    'key' => 'volume', 'label' => 'Volume traité', 'value' => $fcfa($orderVolume), 'unit' => 'F',
                    'change' => $volumeChangePct, 'sub' => 'commandes non annulées, '.$periodLabel,
                ],
                [
                    'key' => 'orders', 'label' => 'Commandes', 'value' => $fcfa($totalOrders), 'unit' => null,
                    'change' => $ordersChangePct, 'sub' => $periodLabel,
                ],
                [
                    'key' => 'pressings', 'label' => 'Pressings actifs', 'value' => $activePressings, 'unit' => null, 'change' => null,
                    'sub' => ($newPressings ? '+'.$newPressings.' '.$periodLabel.' · ' : '').$suspendedPressings.' suspendu'.($suspendedPressings > 1 ? 's' : ''),
                ],
                [
                    'key' => 'clients', 'label' => 'Clients', 'value' => $fcfa($totalClients), 'unit' => null, 'change' => null,
                    'sub' => $newClients !== null ? '+'.$newClients.' '.$periodLabel : 'inscrits sur la plateforme',
                ],
            ];
        @endphp
        @foreach ($kpis as $kpi)
            <div wire:key="kpi-{{ $kpi['key'] }}-{{ $filterKey }}" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 animate-step" style="animation-delay:{{ ($loop->index + 1) * 50 }}ms">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">{{ $kpi['label'] }}</div>
                <div class="flex items-baseline gap-1.5 mt-2.5">
                    <div class="text-3xl font-bold tracking-tight tabular-nums">{{ $kpi['value'] }}</div>
                    @if ($kpi['unit'])
                        <div class="text-sm font-semibold text-(--color-text-muted)">{{ $kpi['unit'] }}</div>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs mt-1.5">
                    @if ($kpi['change'] !== null)
                        <span class="font-semibold {{ $kpi['change'] >= 0 ? 'text-(--color-success-text)' : 'text-(--color-error)' }}"
                              title="Par rapport à la période précédente de même durée">
                            {{ $kpi['change'] >= 0 ? '↑' : '↓' }} {{ abs($kpi['change']) }} %
                        </span>
                    @endif
                    <span class="text-(--color-text-muted)">{{ $kpi['sub'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        {{-- ===== Activité ===== --}}
        <div class="xl:col-span-2 bg-(--color-surface) border border-(--color-border) rounded-xl p-5 flex flex-col">
            <div class="flex items-baseline justify-between gap-3 mb-5">
                <h2 class="font-semibold text-sm">Commandes créées</h2>
                <span class="text-xs text-(--color-text-muted)">{{ ucfirst($periodLabel) }}</span>
            </div>
            @php $bars = $chart['bars']; $labelEvery = max(1, (int) ceil(count($bars) / 8)); @endphp
            @if ($totalOrders === 0)
                <div class="flex-1 min-h-[180px] flex items-center justify-center text-sm text-(--color-text-muted)">Aucune commande sur cette période.</div>
            @else
                <div wire:key="chart-{{ $filterKey }}" class="flex-1 flex items-end gap-[3px] sm:gap-1 min-h-[180px]">
                    @foreach ($bars as $bar)
                        <div class="group relative flex-1 h-full flex items-end">
                            <div class="w-full rounded-t-[3px] bg-(--color-primary) group-hover:bg-(--color-primary-600) animate-step"
                                 style="height:{{ $bar['count'] > 0 ? max(3, round($bar['count'] / $chart['max'] * 100)) : 1 }}%;{{ $bar['count'] === 0 ? 'background:var(--color-border);' : '' }}animation-delay:{{ min($loop->index * 15, 450) }}ms"></div>
                            <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:block whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[11px] font-medium text-white z-10">
                                {{ $bar['label'] }} · {{ $bar['count'] }} commande{{ $bar['count'] > 1 ? 's' : '' }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-[3px] sm:gap-1 mt-2 text-[10.5px] text-(--color-text-muted) tabular-nums">
                    @foreach ($bars as $bar)
                        <div class="flex-1 min-w-0 text-center overflow-visible whitespace-nowrap">{{ $loop->index % $labelEvery === 0 ? $bar['label'] : '' }}</div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ===== À surveiller ===== --}}
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-(--color-border)">
                <h2 class="font-semibold text-sm">À surveiller</h2>
                @if ($alerts->isNotEmpty())
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-(--color-error-tint) text-(--color-error)">{{ $alerts->sum('count') }}</span>
                @endif
            </div>
            @if ($alerts->isEmpty())
                <div class="flex-1 flex flex-col items-center justify-center gap-2 px-5 py-10 text-center">
                    <div class="w-10 h-10 rounded-full bg-(--color-success-tint) flex items-center justify-center">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-success-text)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                    </div>
                    <p class="text-sm font-medium">Rien à signaler</p>
                    <p class="text-xs text-(--color-text-muted)">Abonnements, quotas et activité sont en ordre.</p>
                </div>
            @else
                <div class="divide-y divide-(--color-border)">
                    @foreach ($alerts as $alert)
                        <div wire:key="alert-{{ $alert['key'] }}" x-data="{ open: false }" class="px-5 py-3.5">
                            <button type="button" @click="open = !open" class="w-full flex items-center gap-3 text-left">
                                <span class="flex-none min-w-7 h-7 px-1.5 rounded-lg flex items-center justify-center text-xs font-bold tabular-nums"
                                      style="background:{{ $tones[$alert['tone']]['bg'] }};color:{{ $tones[$alert['tone']]['fg'] }}">{{ $alert['count'] }}</span>
                                <span class="flex-1 min-w-0 text-sm font-medium">{{ $alert['label'] }}</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="flex-none text-(--color-text-muted) transition-transform duration-150" :class="open && 'rotate-180'"><path d="m6 9 6 6 6-6"></path></svg>
                            </button>
                            <div x-show="open" x-collapse x-cloak class="pt-2.5 pl-10">
                                <p class="text-xs text-(--color-text-muted) mb-2">{{ $alert['hint'] }}</p>
                                <ul class="space-y-1">
                                    @foreach ($alert['pressings']->take(6) as $p)
                                        <li><a href="{{ route('admin.pressings.show', $p) }}" wire:navigate class="text-sm text-(--color-primary) hover:underline">{{ $p->name }}</a></li>
                                    @endforeach
                                    @if ($alert['pressings']->count() > 6)
                                        <li class="text-xs text-(--color-text-muted)">et {{ $alert['pressings']->count() - 6 }} autre(s)…</li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        {{-- ===== Top pressings ===== --}}
        <div class="xl:col-span-2 bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
            <div class="flex items-baseline justify-between px-5 py-4 border-b border-(--color-border)">
                <h2 class="font-semibold text-sm">Pressings les plus actifs</h2>
                <span class="text-xs text-(--color-text-muted)">{{ ucfirst($periodLabel) }}</span>
            </div>
            @if ($topPressings->isEmpty())
                <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucune commande sur cette période.</div>
            @else
                @php $topMax = max(1, $topPressings->max('period_orders_count')); @endphp
                <div class="overflow-x-auto"><table class="w-full min-w-[560px] whitespace-nowrap text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wide text-(--color-text-muted)">
                            <th class="px-5 py-2.5 text-left font-semibold">Pressing</th>
                            <th class="px-5 py-2.5 text-left font-semibold w-[40%]">Commandes</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topPressings as $pressing)
                            <tr wire:key="top-{{ $pressing->id }}" class="border-t border-(--color-border) hover:bg-(--color-bg) cursor-pointer" onclick="window.location='{{ route('admin.pressings.show', $pressing) }}'">
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $pressing->name }}</div>
                                    <div class="text-xs text-(--color-text-muted)">{{ $pressing->city ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 h-2 rounded-full bg-(--color-bg) overflow-hidden">
                                            <div class="h-full rounded-full bg-(--color-primary)" style="width:{{ round($pressing->period_orders_count / $topMax * 100) }}%"></div>
                                        </div>
                                        <span class="w-10 text-right font-semibold tabular-nums">{{ $pressing->period_orders_count }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ $fcfa((int) $pressing->period_volume) }} F</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>

        {{-- ===== Abonnements ===== --}}
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <h2 class="font-semibold text-sm mb-4">Abonnements</h2>
            @php
                $planTotal = max(1, collect($planBreakdown)->sum('count'));
                $planColors = ['#93c5fd', '#3b82f6', '#1e3a8a'];
                $statusColors = ['trialing' => 'var(--color-info)', 'active' => 'var(--color-success)', 'expired' => 'var(--color-warning)', 'canceled' => 'var(--color-text-muted)'];
            @endphp
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Par formule</div>
            <div class="flex h-2.5 rounded-full overflow-hidden bg-(--color-bg) mb-3">
                @foreach ($planBreakdown as $i => $row)
                    @if ($row['count'] > 0)
                        <div style="width:{{ $row['count'] / $planTotal * 100 }}%;background:{{ $planColors[$i] }}"></div>
                    @endif
                @endforeach
            </div>
            <div class="space-y-1.5 mb-6">
                @foreach ($planBreakdown as $i => $row)
                    <div class="flex items-center gap-2 text-sm">
                        <span class="w-2.5 h-2.5 rounded-sm flex-none" style="background:{{ $planColors[$i] }}"></span>
                        <span class="flex-1">{{ $row['label'] }}</span>
                        <span class="font-semibold tabular-nums">{{ $row['count'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Par statut</div>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($statusBreakdown as $row)
                    <div class="rounded-lg border border-(--color-border) px-3 py-2.5">
                        <div class="flex items-center gap-1.5 text-xs text-(--color-text-secondary)">
                            <span class="w-2 h-2 rounded-full" style="background:{{ $statusColors[$row['key']] }}"></span>{{ $row['label'] }}
                        </div>
                        <div class="text-xl font-bold tabular-nums mt-0.5">{{ $row['count'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== Pressings récents ===== --}}
    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-(--color-border)">
            <h2 class="font-semibold text-sm">Pressings récents</h2>
            <a href="{{ route('admin.pressings.index') }}" wire:navigate class="text-sm font-medium text-(--color-primary) hover:underline">Voir tout →</a>
        </div>
        @if ($recentPressings->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun pressing pour ces filtres.</div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[640px] whitespace-nowrap text-sm">
                <tbody>
                    @foreach ($recentPressings as $pressing)
                        <tr class="border-t border-(--color-border) first:border-t-0 hover:bg-(--color-bg) cursor-pointer" onclick="window.location='{{ route('admin.pressings.show', $pressing) }}'">
                            <td class="px-5 py-3.5 font-medium">{{ $pressing->name }}</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">{{ $pressing->city }}</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">
                                @if ($pressing->subscription)
                                    {{ $pressing->subscription->plan->label() }} · {{ $pressing->subscription->status->label() }}
                                @else
                                    Sans abonnement
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-(--color-text-muted)">Créé le {{ $pressing->created_at->format('d/m/Y') }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-xs font-medium {{ $pressing->status->value === 'active' ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">
                                    {{ $pressing->status->label() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    {{-- Environnement de démo (comptes fictifs en production pour les démonstrations) --}}
    <div class="mt-8 bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <h2 class="font-display text-lg font-bold mb-1">Environnement de démo</h2>
                <p class="text-sm text-(--color-text-secondary)">
                    Pressing fictif « Pressing Démo PressLink » ({{ \App\Actions\Demo\ResetDemoPressing::PRESSING_CODE }}) avec un gérant, un employé, 4 clients et des commandes à chaque statut.
                    La réinitialisation efface la démo précédente et génère un nouveau mot de passe.
                </p>
            </div>
            <button wire:click="resetDemo" wire:loading.attr="disabled"
                    wire:confirm="{{ $demoExists ? 'Réinitialiser la démo ? Toutes les données de démo actuelles seront effacées.' : 'Créer le pressing de démo ?' }}"
                    class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
                <span wire:loading.remove wire:target="resetDemo">{{ $demoExists ? 'Réinitialiser la démo' : 'Créer la démo' }}</span>
                <span wire:loading wire:target="resetDemo">Préparation…</span>
            </button>
        </div>

        @if ($demoAccess)
            <div class="mt-5 rounded-lg bg-(--color-success-tint) border border-emerald-200 p-4 text-sm">
                <p class="font-semibold text-(--color-success-text) mb-3">Démo prête. Notez le mot de passe : il ne sera plus affiché.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Espace pressing ({{ url('/login') }})</p>
                        <p>Gérant : <code class="font-mono font-semibold">{{ \App\Actions\Demo\ResetDemoPressing::MANAGER_LOGIN }}</code></p>
                        <p>Employé : <code class="font-mono font-semibold">{{ \App\Actions\Demo\ResetDemoPressing::EMPLOYEE_LOGIN }}</code></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Application client ({{ url('/app/') }})</p>
                        <p>Téléphone : <code class="font-mono font-semibold">+225 00 00 00 00 01</code> (à 04)</p>
                        <p>Code du pressing : <code class="font-mono font-semibold">{{ $demoAccess['code'] }}</code></p>
                    </div>
                </div>
                <p class="mt-3">Mot de passe (tous les comptes) : <code class="font-mono text-base font-bold bg-white px-2 py-0.5 rounded">{{ $demoAccess['password'] }}</code></p>
            </div>
        @endif
    </div>
</div>
