@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $periodLabel = match ($period) {
        'today' => "aujourd'hui",
        '7' => 'sur 7 jours',
        '90' => 'sur 90 jours',
        'month' => 'ce mois-ci',
        'all' => 'depuis le début',
        default => 'sur 30 jours',
    };
    $periods = ['today' => "Aujourd'hui", '7' => '7 jours', '30' => '30 jours', '90' => '90 jours', 'all' => 'Tout'];
    $filterKey = $period.'-'.$city.'-'.$administratorId;
    $tones = [
        'error' => ['bg' => 'var(--color-error-tint)', 'fg' => 'var(--color-error)'],
        'warning' => ['bg' => 'var(--color-warning-tint)', 'fg' => 'var(--color-warning-text)'],
        'info' => ['bg' => 'var(--color-info-tint)', 'fg' => 'var(--color-info)'],
    ];
    $alerts = collect($watchlist)->where('count', '>', 0);
    // Début de la période en date, pour ouvrir les listes déjà filtrées.
    $periodFrom = match ($period) {
        'today' => now()->toDateString(),
        '7' => now()->subDays(7)->toDateString(),
        '90' => now()->subDays(90)->toDateString(),
        'month' => now()->startOfMonth()->toDateString(),
        'all' => null,
        default => now()->subDays(30)->toDateString(),
    };
    $ordersListUrl = route('admin.orders.index', array_filter(['du' => $periodFrom]));

    // Styles communs : cartes blanches bordées, titres discrets, chiffres en gros.
    $card = 'rounded-xl border border-(--color-border) bg-(--color-surface) p-4 shadow-sm';
    $cardTitle = 'mb-3 text-sm font-medium text-(--color-text-muted)';
    $select = 'h-9 px-2.5 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $change = fn (?int $pct) => $pct === null ? '' : '<span class="font-semibold '.($pct >= 0 ? 'text-(--color-success-text)' : 'text-(--color-error)').'" title="Par rapport à la période précédente de même durée">'.($pct >= 0 ? '↑' : '↓').' '.abs($pct).' %</span> ';

    $plural = fn (int $n, string $word) => $n.' '.$word.($n > 1 ? 's' : '');
    $rate = fn (?float $value) => $value === null ? '—' : number_format($value, 1, ',', ' ').' %';
    $online = $payments['online'];
    $kpiRows = [
        [
            ['label' => 'Encaissé en ligne', 'value' => $fcfa($online['collected']).' F CFA',
                'sub' => $plural($online['count'], 'paiement').' · net reçu '.$fcfa($online['net']).' F'],
            ['label' => 'Encaissé manuel', 'value' => $fcfa($payments['manual']['collected']).' F CFA',
                'sub' => $plural($payments['manual']['count'], 'paiement').' enregistré'.($payments['manual']['count'] > 1 ? 's' : '').' par l\'équipe'],
            ['label' => 'Taux de réussite', 'value' => $rate($online['rate']),
                'sub' => $fcfa($online['paid']).' payés sur '.$fcfa($online['paid'] + $online['failed']).' tentatives'.($online['pending'] > 0 ? ' · '.$online['pending'].' en cours' : '')],
            ['label' => 'Paiements échoués', 'value' => $fcfa($online['failed']), 'sub' => 'en ligne, refusés, abandonnés ou expirés',
                'link' => $online['failed'] > 0 ? ['url' => route('admin.payments.index', array_filter(['statut' => 'echoue', 'du' => $periodFrom])), 'label' => 'Voir les paiements échoués', 'tone' => 'warning'] : null],
        ],
        [
            ['label' => 'Revenu mensuel récurrent', 'value' => $fcfa($monthlyRecurringRevenue).' F CFA',
                'sub' => $plural($payingPressings, 'abonnement').' actif'.($payingPressings > 1 ? 's' : '').' · '.$fcfa($monthlyRecurringRevenue * 12).' F / an'],
            ['label' => 'Demandes à traiter', 'value' => $fcfa($payments['pending']['count']),
                'sub' => $payments['pending']['amount'] > 0 ? $fcfa($payments['pending']['amount']).' F en attente de paiement' : 'abonnements, options et commandes en plus',
                'link' => $payments['pending']['count'] > 0 ? ['url' => route('admin.requests.index'), 'label' => 'Voir les demandes'] : null],
            ['label' => 'Pressings actifs', 'value' => $fcfa($activePressings),
                'sub' => ($newPressings ? '+'.$newPressings.' '.$periodLabel.' · ' : '').$suspendedPressings.' suspendu'.($suspendedPressings > 1 ? 's' : ''),
                'link' => ['url' => route('admin.pressings.index', array_filter(['status' => 'active', 'ville' => $city])), 'label' => 'Voir les pressings']],
            ['label' => 'Clients', 'value' => $fcfa($totalClients),
                'sub' => $newClients !== null ? '+'.$newClients.' '.$periodLabel : 'inscrits sur la plateforme',
                'link' => ['url' => route('admin.clients.index'), 'label' => 'Voir les clients']],
        ],
    ];
    $paymentTone = [
        \App\Models\PaymentAttempt::PAID => 'bg-(--color-success-tint) text-(--color-success-text)',
        \App\Models\PaymentAttempt::PENDING => 'bg-(--color-info-tint) text-(--color-info)',
    ];
@endphp

<div class="space-y-6">
    {{-- ===== Titre, filtres et période ===== --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Tableau de bord</h1>
            <p class="text-sm text-(--color-text-muted)">{{ ucfirst(now()->translatedFormat('l j F Y')) }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span wire:loading.delay class="text-xs text-(--color-text-muted)">Mise à jour…</span>
            <select wire:model.live="city" aria-label="Ville" class="{{ $select }}">
                <option value="">Toutes les villes</option>
                @foreach ($cities as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
            <select wire:model.live="administratorId" aria-label="Administrateur" class="{{ $select }}">
                <option value="">Tous les administrateurs</option>
                @foreach ($administrators as $admin)
                    <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                @endforeach
            </select>
            @if ($city !== '' || $administratorId !== '' || $period !== '30')
                <button wire:click="resetFilters" class="text-xs font-medium text-(--color-text-muted) hover:text-(--color-primary) underline">Réinitialiser</button>
            @endif
            <div class="inline-flex max-w-full overflow-x-auto rounded-lg border border-(--color-border) bg-(--color-surface) p-0.5" role="group" aria-label="Période">
                @foreach ($periods as $value => $label)
                    <button type="button" wire:click="$set('period', '{{ $value }}')" aria-pressed="{{ $period === (string) $value ? 'true' : 'false' }}"
                            @class(['rounded-md px-3 py-1 text-sm whitespace-nowrap', 'bg-(--color-primary) text-white' => $period === (string) $value, 'hover:bg-(--color-bg)' => $period !== (string) $value])>{{ $label }}</button>
                @endforeach
            </div>
            <a href="{{ route('admin.pressings.create') }}" wire:navigate class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nouveau pressing
            </a>
        </div>
    </div>

    {{-- ===== Indicateurs : encaissements, puis activité des pressings ===== --}}
    <div class="space-y-4 loading-dim rounded-xl" wire:loading.delay.class="is-loading" wire:target="period, city, administratorId, resetFilters">
        @foreach ($kpiRows as $row)
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($row as $kpi)
                    <div wire:key="kpi-{{ $loop->parent->index }}-{{ $loop->index }}-{{ $filterKey }}" class="{{ $card }}">
                        <p class="text-sm text-(--color-text-muted)">{{ $kpi['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $kpi['value'] }}</p>
                        <p class="text-xs text-(--color-text-muted)">{!! $kpi['html'] ?? '' !!}{{ $kpi['sub'] }}</p>
                        @if (! empty($kpi['link']))
                            <a href="{{ $kpi['link']['url'] }}" wire:navigate @class(['inline-block mt-1 text-xs font-medium underline underline-offset-2 hover:no-underline', 'text-(--color-warning-text)' => ($kpi['link']['tone'] ?? null) === 'warning', 'text-(--color-primary)' => ($kpi['link']['tone'] ?? null) !== 'warning'])>{{ $kpi['link']['label'] }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- ===== Commandes par jour + pressings les plus actifs ===== --}}
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="{{ $card }} xl:col-span-2 flex flex-col">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 mb-3">
                <h2 class="text-sm font-medium text-(--color-text-muted)">Commandes créées ({{ $periodLabel }})</h2>
                <p class="text-xs text-(--color-text-muted)">
                    <span class="font-semibold text-(--color-text-primary) tabular-nums">{{ $plural($totalOrders, 'commande') }}</span> {!! $change($ordersChangePct) !!}
                    · <span class="font-semibold text-(--color-text-primary) tabular-nums">{{ $fcfa($orderVolume) }} F</span> traités {!! $change($volumeChangePct) !!}
                    · <a href="{{ $ordersListUrl }}" wire:navigate class="text-(--color-primary) hover:underline">Voir les commandes</a>
                </p>
            </div>
            @php
                $bars = $chart['bars'];
                $labelEvery = max(1, (int) ceil(count($bars) / 12));
                // Graduation « ronde » de l'axe : 1, 2 ou 5 × 10^n par palier, quatre paliers.
                $rawStep = max(1, $chart['max'] / 4);
                $magnitude = 10 ** floor(log10($rawStep));
                $step = collect([1, 2, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($s) => $s >= $rawStep);
                $axisTop = $step * 4;
            @endphp
            @if ($totalOrders === 0)
                <div class="flex-1 min-h-[220px] flex items-center justify-center text-sm text-(--color-text-muted)">Aucune commande sur cette période.</div>
            @else
                <div wire:key="chart-{{ $filterKey }}" class="relative h-56 pl-10">
                    @for ($i = 0; $i <= 4; $i++)
                        <div class="absolute left-10 right-0 border-t border-dashed border-(--color-border)" style="bottom:{{ $i * 25 }}%"></div>
                        <div class="absolute left-0 w-8 text-right text-[11px] text-(--color-text-muted) tabular-nums translate-y-1/2" style="bottom:{{ $i * 25 }}%">{{ $fcfa((int) ($step * $i)) }}</div>
                    @endfor
                    <div class="absolute inset-y-0 left-10 right-0 flex items-end gap-[3px] sm:gap-1">
                        @foreach ($bars as $bar)
                            <a href="{{ route('admin.orders.index', ['du' => $bar['from'], 'au' => $bar['to']]) }}" wire:navigate
                               aria-label="{{ $bar['label'] }} : {{ $bar['count'] }} commande{{ $bar['count'] > 1 ? 's' : '' }}"
                               class="group relative flex-1 h-full flex items-end {{ $bar['count'] === 0 ? 'pointer-events-none' : '' }}">
                                <div class="w-full rounded-t-[3px] bg-(--color-primary) group-hover:bg-(--color-primary-600)" style="height:{{ $bar['count'] > 0 ? max(1, round($bar['count'] / $axisTop * 100, 1)) : 0 }}%"></div>
                                <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover:block whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[11px] font-medium text-white z-10">
                                    {{ $bar['label'] }} · {{ $bar['count'] }} commande{{ $bar['count'] > 1 ? 's' : '' }} · voir
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="flex gap-[3px] sm:gap-1 mt-2 pl-10 text-[11px] text-(--color-text-muted) tabular-nums">
                    @foreach ($bars as $bar)
                        <div class="flex-1 min-w-0 text-center overflow-visible whitespace-nowrap">{{ $loop->index % $labelEvery === 0 ? $bar['label'] : '' }}</div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="{{ $card }}">
            <h2 class="{{ $cardTitle }}">Pressings les plus actifs</h2>
            @if ($topPressings->isEmpty())
                <p class="py-10 text-center text-sm text-(--color-text-muted)">Aucune commande sur cette période.</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($topPressings as $pressing)
                        <li wire:key="top-{{ $pressing->id }}" class="flex items-center justify-between gap-2">
                            <a href="{{ route('admin.pressings.show', $pressing) }}" wire:navigate class="min-w-0 truncate hover:underline">{{ $pressing->name }}</a>
                            <span class="shrink-0 text-right">
                                <span class="font-medium tabular-nums">{{ $fcfa((int) $pressing->period_volume) }} F CFA</span>
                                <span class="block text-xs text-(--color-text-muted) tabular-nums">{{ $pressing->period_orders_count }} commande{{ $pressing->period_orders_count > 1 ? 's' : '' }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- ===== Réussite par moyen de paiement ===== --}}
    <div class="{{ $card }}">
        <h2 class="{{ $cardTitle }}">Réussite par moyen de paiement ({{ $periodLabel }})</h2>
        @if (empty($payments['byMethod']))
            <p class="py-6 text-center text-sm text-(--color-text-muted)">Aucun paiement sur cette période.</p>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-sm">
                <thead>
                    <tr class="text-left text-(--color-text-muted)">
                        <th class="py-1 pr-4 font-semibold">Moyen de paiement</th>
                        <th class="py-1 pr-4 font-semibold text-right">Payés</th>
                        <th class="py-1 pr-4 font-semibold text-right">Échoués</th>
                        <th class="py-1 pr-4 font-semibold text-right">En cours</th>
                        <th class="py-1 pr-4 font-semibold text-right">Taux de réussite</th>
                        <th class="py-1 pr-4 font-semibold text-right">Encaissé</th>
                        <th class="py-1 pr-4 font-semibold text-right">Frais GeniusPay</th>
                        <th class="py-1 font-semibold text-right">Net reçu</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments['byMethod'] as $row)
                        @php $manualRow = $row['manual'] ?? false; @endphp
                        <tr class="border-t border-(--color-border)">
                            <td class="py-2 pr-4 font-medium">{{ $row['label'] }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ $fcfa($row['paid']) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ $manualRow ? '—' : $fcfa($row['failed']) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ $manualRow ? '—' : $fcfa($row['pending']) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums font-medium">{{ $manualRow ? '—' : $rate($row['rate']) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ $fcfa($row['collected']) }} F</td>
                            <td class="py-2 pr-4 text-right tabular-nums text-(--color-text-muted)">{{ $row['fees'] > 0 ? $fcfa($row['fees']).' F' : '—' }}</td>
                            <td class="py-2 text-right tabular-nums font-semibold">{{ $fcfa($row['net']) }} F</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    {{-- ===== Réussite par pressing + derniers paiements ===== --}}
    <div class="grid gap-4 xl:grid-cols-2">
        <div class="{{ $card }}">
            <h2 class="{{ $cardTitle }}">Réussite par pressing (paiements en ligne)</h2>
            @if (empty($payments['byPressing']))
                <p class="py-6 text-center text-sm text-(--color-text-muted)">Aucun paiement en ligne sur cette période.</p>
            @else
                <div class="overflow-x-auto"><table class="w-full min-w-[520px] text-sm">
                    <thead>
                        <tr class="text-left text-(--color-text-muted)">
                            <th class="py-1 pr-4 font-semibold">Pressing</th>
                            <th class="py-1 pr-4 font-semibold text-right">Payés</th>
                            <th class="py-1 pr-4 font-semibold text-right">Échoués</th>
                            <th class="py-1 pr-4 font-semibold text-right">Taux</th>
                            <th class="py-1 font-semibold text-right">Encaissé</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments['byPressing'] as $row)
                            <tr class="border-t border-(--color-border)">
                                <td class="py-2 pr-4 max-w-56">
                                    <a href="{{ route('admin.payments.index', array_filter(['pressing' => $row['pressing_id'], 'du' => $periodFrom])) }}" wire:navigate class="block truncate font-medium hover:underline">{{ $row['name'] }}</a>
                                </td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ $fcfa($row['paid']) }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ $fcfa($row['failed']) }}</td>
                                <td @class(['py-2 pr-4 text-right tabular-nums font-medium', 'text-(--color-error)' => $row['rate'] !== null && $row['rate'] < 50])>{{ $rate($row['rate']) }}</td>
                                <td class="py-2 text-right tabular-nums whitespace-nowrap">{{ $fcfa($row['collected']) }} F</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>

        <div class="{{ $card }}">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-medium text-(--color-text-muted)">Derniers paiements</h2>
                <a href="{{ route('admin.payments.index') }}" wire:navigate class="text-xs font-medium text-(--color-primary) hover:underline">Voir tous les paiements</a>
            </div>
            @if ($payments['recent']->isEmpty())
                <p class="py-6 text-center text-sm text-(--color-text-muted)">Aucun paiement sur cette période.</p>
            @else
                <div class="overflow-x-auto"><table class="w-full min-w-[520px] text-sm">
                    <thead>
                        <tr class="text-left text-(--color-text-muted)">
                            <th class="py-1 pr-4 font-semibold">Référence</th>
                            <th class="py-1 pr-4 font-semibold">Pressing</th>
                            <th class="py-1 pr-4 font-semibold text-right">Montant</th>
                            <th class="py-1 font-semibold">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments['recent'] as $payment)
                            <tr class="border-t border-(--color-border) align-top">
                                <td class="py-2 pr-4">
                                    <a href="{{ route('admin.payments.index', ['search' => $payment->reference]) }}" wire:navigate class="font-mono font-semibold whitespace-nowrap hover:underline">{{ $payment->reference }}</a>
                                    <span class="block text-xs text-(--color-text-muted) whitespace-nowrap">{{ $payment->started_at->format('d/m H:i') }} · {{ $payment->methodLabel() }}</span>
                                </td>
                                <td class="py-2 pr-4 max-w-40"><span class="block truncate">{{ $payment->pressing_name }}</span></td>
                                <td class="py-2 pr-4 text-right tabular-nums whitespace-nowrap">{{ $fcfa($payment->collectedFcfa()) }} F</td>
                                <td class="py-2">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap {{ $paymentTone[$payment->status] ?? 'bg-(--color-error-tint) text-(--color-error)' }}">{{ $payment->statusLabel() }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
    </div>

    {{-- ===== À surveiller · Abonnements · Pressings récents ===== --}}
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="{{ $card }} flex flex-col">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-medium text-(--color-text-muted)">À surveiller</h2>
                @if ($alerts->isNotEmpty())
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-(--color-error-tint) text-(--color-error)">{{ $alerts->sum('count') }}</span>
                @endif
            </div>
            @if ($alerts->isEmpty())
                <div class="flex-1 flex flex-col items-center justify-center gap-2 py-8 text-center">
                    <div class="w-10 h-10 rounded-full bg-(--color-success-tint) flex items-center justify-center">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-success-text)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                    </div>
                    <p class="text-sm font-medium">Rien à signaler</p>
                    <p class="text-xs text-(--color-text-muted)">Abonnements, quotas et activité sont en ordre.</p>
                </div>
            @else
                <div class="-mx-4 divide-y divide-(--color-border) border-t border-(--color-border)">
                    @foreach ($alerts as $alert)
                        <div wire:key="alert-{{ $alert['key'] }}" x-data="{ open: false }" class="px-4 py-3">
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
                                @if ($alert['key'] === 'requests')
                                    <a href="{{ route('admin.requests.index') }}" wire:navigate class="inline-block mt-2 text-xs font-semibold text-(--color-primary) hover:underline">Traiter les demandes →</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="{{ $card }}">
            <h2 class="{{ $cardTitle }}">Abonnements</h2>
            @php
                $planTotal = max(1, collect($planBreakdown)->sum('count'));
                $planColors = ['#93c5fd', '#3b82f6', '#1e3a8a'];
                $statusColors = ['trialing' => 'var(--color-info)', 'active' => 'var(--color-success)', 'expired' => 'var(--color-warning)', 'canceled' => 'var(--color-text-muted)'];
            @endphp
            <div class="flex h-2.5 rounded-full overflow-hidden bg-(--color-bg) mb-3">
                @foreach ($planBreakdown as $i => $row)
                    @if ($row['count'] > 0)
                        <div style="width:{{ $row['count'] / $planTotal * 100 }}%;background:{{ $planColors[$i] }}"></div>
                    @endif
                @endforeach
            </div>
            <ul class="space-y-1 mb-4 text-sm">
                @foreach ($planBreakdown as $i => $row)
                    <li>
                        <a href="{{ route('admin.pressings.index', ['plan' => $row['key']]) }}" wire:navigate class="flex items-center gap-2 -mx-2 px-2 py-1 rounded-md hover:bg-(--color-bg)">
                            <span class="w-2.5 h-2.5 rounded-sm flex-none" style="background:{{ $planColors[$i] }}"></span>
                            <span class="flex-1">{{ $row['label'] }}</span>
                            <span class="font-medium tabular-nums">{{ $row['count'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-(--color-text-muted)"><th class="py-1 font-semibold">Statut</th><th class="py-1 font-semibold text-right">Pressings</th></tr></thead>
                <tbody>
                    @foreach ($statusBreakdown as $row)
                        <tr class="border-t border-(--color-border)">
                            <td class="py-2">
                                <a href="{{ route('admin.pressings.index', ['abonnement' => $row['key']]) }}" wire:navigate class="inline-flex items-center gap-2 hover:underline">
                                    <span class="w-2 h-2 rounded-full" style="background:{{ $statusColors[$row['key']] }}"></span>{{ $row['label'] }}
                                </a>
                            </td>
                            <td class="py-2 text-right font-medium tabular-nums">{{ $row['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="{{ $card }}">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-medium text-(--color-text-muted)">Pressings récents</h2>
                <a href="{{ route('admin.pressings.index') }}" wire:navigate class="text-xs font-medium text-(--color-primary) hover:underline">Voir tout</a>
            </div>
            @if ($recentPressings->isEmpty())
                <p class="py-10 text-center text-sm text-(--color-text-muted)">Aucun pressing pour ces filtres.</p>
            @else
                <ul class="space-y-2.5 text-sm">
                    @foreach ($recentPressings as $pressing)
                        <li class="flex items-start justify-between gap-2">
                            <span class="min-w-0">
                                <a href="{{ route('admin.pressings.show', $pressing) }}" wire:navigate class="block truncate font-medium hover:underline">{{ $pressing->name }}</a>
                                <span class="block text-xs text-(--color-text-muted) truncate">
                                    {{ $pressing->city ?? '—' }} ·
                                    {{ $pressing->subscription ? $pressing->subscription->plan->label().' · '.$pressing->subscription->status->label() : 'Sans abonnement' }}
                                </span>
                            </span>
                            <span class="shrink-0 text-right text-xs">
                                <span class="block text-(--color-text-muted) tabular-nums">{{ $pressing->created_at->format('d/m/Y') }}</span>
                                <span class="font-medium {{ $pressing->status->value === 'active' ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">{{ $pressing->status->label() }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- ===== Tests découverte : pressings fictifs lancés depuis le site (hors statistiques) ===== --}}
    <div class="{{ $card }}" data-testid="sandbox-trials">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-3">
            <div class="max-w-2xl">
                <h2 class="text-sm font-medium text-(--color-text-muted)">Tests découverte</h2>
                <p class="text-xs text-(--color-text-muted) mt-0.5">Pressings fictifs lancés depuis la page « Tester » ({{ \App\Actions\Sandbox\StartSandbox::DAYS }} jours, effacés ensuite). Ils n'entrent dans aucun chiffre ci-dessus.</p>
            </div>
            <dl class="flex flex-wrap gap-6 text-sm">
                <div><dt class="text-(--color-text-muted)">Lancés (7 j)</dt><dd class="text-xl font-semibold tabular-nums">{{ $sandboxTrials['startedThisWeek'] }}</dd></div>
                <div><dt class="text-(--color-text-muted)">Devenus clients (7 j)</dt><dd class="text-xl font-semibold tabular-nums text-(--color-success-text)">{{ $sandboxTrials['convertedThisWeek'] }}</dd></div>
                <div><dt class="text-(--color-text-muted)">En cours</dt><dd class="text-xl font-semibold tabular-nums">{{ $sandboxTrials['running'] }}</dd></div>
                <div><dt class="text-(--color-text-muted)">Depuis le début</dt><dd class="text-xl font-semibold tabular-nums">{{ $sandboxTrials['convertedTotal'] }}<span class="text-sm font-medium text-(--color-text-muted)"> / {{ $sandboxTrials['total'] }}</span></dd></div>
            </dl>
        </div>
        @if ($sandboxTrials['recent']->isEmpty())
            <p class="py-6 text-center text-sm text-(--color-text-muted)">Aucun test lancé pour l'instant.</p>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[640px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="text-left text-(--color-text-muted)"><th class="py-1 pr-4 font-semibold">Pressing</th><th class="py-1 pr-4 font-semibold">WhatsApp</th><th class="py-1 pr-4 font-semibold">Lancé le</th><th class="py-1 font-semibold text-right">Statut</th></tr>
                </thead>
                <tbody>
                    @foreach ($sandboxTrials['recent'] as $trial)
                        <tr class="border-t border-(--color-border)">
                            <td class="py-2 pr-4 font-medium">{{ $trial->pressing_name }}</td>
                            <td class="py-2 pr-4">
                                <a href="https://wa.me/{{ ltrim($trial->contact_phone, '+') }}" target="_blank" rel="noopener" class="tabular-nums text-(--color-primary) hover:underline">{{ \App\Support\PhoneNumber::format($trial->contact_phone) }}</a>
                            </td>
                            <td class="py-2 pr-4 text-(--color-text-secondary) tabular-nums">{{ $trial->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2 text-right">
                                @if ($trial->isConverted() && $trial->convertedPressing)
                                    <a href="{{ route('admin.pressings.show', $trial->convertedPressing) }}" wire:navigate class="text-xs font-semibold text-(--color-success-text) hover:underline">Devenu client · {{ $trial->convertedPressing->name }}</a>
                                @else
                                    <span @class(['text-xs font-medium', 'text-(--color-success-text)' => $trial->isConverted(), 'text-(--color-info)' => $trial->isRunning(), 'text-(--color-text-muted)' => ! $trial->isConverted() && ! $trial->isRunning()])>{{ $trial->statusLabel() }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    {{-- ===== Environnement de démo (comptes fictifs en production pour les démonstrations) ===== --}}
    <div class="{{ $card }}">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <h2 class="text-sm font-medium text-(--color-text-muted) mb-1">Environnement de démo</h2>
                <p class="text-sm text-(--color-text-secondary)">
                    Pressing fictif « Pressing Démo PressLink » ({{ \App\Actions\Demo\ResetDemoPressing::PRESSING_CODE }}) avec un gérant, un employé, 4 clients et des commandes à chaque statut.
                    La réinitialisation efface la démo précédente et génère un nouveau mot de passe.
                </p>
            </div>
            <button wire:click="resetDemo" wire:loading.attr="disabled"
                    data-confirm-title="{{ $demoExists ? 'Réinitialiser la démo' : 'Créer la démo' }}" data-confirm-label="{{ $demoExists ? 'Réinitialiser' : 'Créer la démo' }}" data-confirm-tone="{{ $demoExists ? 'warning' : 'primary' }}"
                    wire:confirm="{{ $demoExists ? 'Réinitialiser la démo ? Toutes les données de démo actuelles seront effacées.' : 'Créer le pressing de démo ?' }}"
                    class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
                <span wire:loading.remove wire:target="resetDemo">{{ $demoExists ? 'Réinitialiser la démo' : 'Créer la démo' }}</span>
                <span wire:loading wire:target="resetDemo">Préparation…</span>
            </button>
        </div>

        @if ($demoAccess)
            <div class="mt-4 rounded-lg bg-(--color-success-tint) border border-(--color-success)/30 p-4 text-sm">
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
                <p class="mt-3">Mot de passe (tous les comptes) : <code class="font-mono text-base font-bold bg-(--color-surface) px-2 py-0.5 rounded">{{ $demoAccess['password'] }}</code></p>
            </div>
        @endif
    </div>
</div>
