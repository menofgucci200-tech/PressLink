<div>
    @if (! $pressing)
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-8 text-center text-(--color-text-secondary)">
            Votre compte n'est rattaché à aucun pressing pour le moment.
        </div>
    @else
        <div class="flex items-start justify-between gap-6 mb-7">
            <div>
                <h1 class="font-display text-2xl font-bold mb-1">Bonjour, {{ $pressing->name }}</h1>
                <p class="text-sm text-(--color-text-muted)">{{ now()->translatedFormat('l j F Y') }} @if($pressing->city) · {{ $pressing->city }} @endif</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative">
                    <button type="button" wire:click="$toggle('showExportMenu')"
                            class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Exporter
                    </button>
                    @if ($showExportMenu)
                        <div class="absolute right-0 mt-2 w-56 bg-(--color-surface) border border-(--color-border) rounded-lg shadow-lg overflow-hidden z-10">
                            <a href="{{ route('orders.export', ['format' => 'csv']) }}" class="block px-4 py-2.5 text-sm hover:bg-(--color-bg)">CSV — toutes les commandes</a>
                            <a href="{{ route('orders.export', ['format' => 'xlsx']) }}" class="block px-4 py-2.5 text-sm hover:bg-(--color-bg)">Excel — toutes les commandes</a>
                            <a href="{{ route('orders.export', ['format' => 'pdf']) }}" class="block px-4 py-2.5 text-sm hover:bg-(--color-bg)">PDF — toutes les commandes</a>
                        </div>
                    @endif
                </div>
                <a href="{{ route('orders.create') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Nouvelle commande
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-[19px] mb-5">
            @php
                $totalToday = array_sum($counts);
                $kpis = [
                    ['key' => 'recue', 'label' => 'Nouvelles', 'value' => $counts['recue'], 'color' => 'var(--color-info)', 'hint' => $counts['recue'] > 0 ? 'À réceptionner' : 'Aucune commande reçue'],
                    ['key' => 'traitement', 'label' => 'En traitement', 'value' => $counts['traitement'], 'color' => 'var(--color-secondary)', 'hint' => $counts['traitement'] > 0 ? 'En atelier' : 'Rien en atelier'],
                    ['key' => 'prete', 'label' => 'Prêtes', 'value' => $counts['prete'], 'color' => 'var(--color-success)', 'hint' => $counts['prete'] > 0 ? 'À retirer' : 'Aucune en attente de retrait'],
                    ['key' => 'recuperee', 'label' => 'Récupérées', 'value' => $counts['recuperee'], 'color' => 'var(--color-text-muted)', 'hint' => $counts['recuperee'] > 0 ? number_format($counts['recuperee'], 0, ',', ' ').' aujourd\'hui' : 'Aucune aujourd\'hui'],
                ];
            @endphp
            @foreach ($kpis as $kpi)
                @php
                    $yesterday = (int) ($countsYesterday[$kpi['key']] ?? 0);
                    $delta = $kpi['value'] - $yesterday;
                    $pct = $totalToday > 0 ? round(($kpi['value'] / $totalToday) * 100) : 0;
                @endphp
                <div class="bg-(--color-surface) border border-(--color-border) rounded-xl px-5 pt-[19px] pb-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-[7px] h-[7px] rounded-full" style="background:{{ $kpi['color'] }}"></span>
                            <span class="text-[13.5px] font-medium text-(--color-text-secondary)">{{ $kpi['label'] }}</span>
                        </div>
                        @if ($delta !== 0)
                            <span class="text-[11.5px] font-semibold {{ $delta > 0 ? 'text-(--color-success-text)' : 'text-(--color-text-muted)' }}">{{ $delta > 0 ? '+'.$delta : $delta }}</span>
                        @else
                            <span class="text-[11.5px] font-semibold text-(--color-text-muted)">—</span>
                        @endif
                    </div>
                    <div class="text-[34px] font-bold tracking-tight my-3.5 tabular-nums">{{ $kpi['value'] }}</div>
                    <div class="text-[12.5px] text-(--color-text-muted) mb-3">aujourd'hui</div>
                    <div class="h-1 bg-(--color-border) rounded-full overflow-hidden">
                        <div class="h-full rounded-full animate-step" style="width:{{ $pct }}%;background:{{ $kpi['color'] }}"></div>
                    </div>
                    <div class="mt-2.5 text-[11.5px] text-(--color-text-muted) truncate">{{ $kpi['hint'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_300px] gap-5 items-start pb-8">
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
                <div class="flex items-center px-5 py-[17px]">
                    <h2 class="font-semibold text-sm">Commandes récentes</h2>
                    <div class="flex-1"></div>
                    <a href="{{ route('orders.index') }}" wire:navigate class="text-sm font-semibold text-(--color-primary)">Voir tout →</a>
                </div>

                @if ($recent->isEmpty())
                    <div class="px-5 py-12 text-center text-sm text-(--color-text-muted) border-t border-(--color-border)">
                        Aucune commande pour le moment.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <div class="min-w-[560px]">
                            <div class="grid grid-cols-[100px_minmax(140px,1fr)_130px_90px_70px_24px] items-center px-5 py-2.5 border-y border-(--color-border) text-[10.5px] font-semibold tracking-wide text-(--color-text-muted) uppercase">
                                <div>N°</div><div>Client</div><div>Statut</div><div class="text-right">Total</div><div class="text-right">Dépôt</div><div></div>
                            </div>
                            @foreach ($recent as $order)
                                <a href="{{ route('orders.show', $order) }}" wire:navigate
                                   class="grid grid-cols-[100px_minmax(140px,1fr)_130px_90px_70px_24px] items-center px-5 py-[15px] border-b border-(--color-border) last:border-b-0 hover:bg-(--color-bg) transition-colors duration-100">
                                    <div class="text-sm font-semibold truncate">{{ $order->order_number }}</div>
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="w-[26px] h-[26px] flex-none rounded-lg bg-(--color-bg) text-(--color-text-secondary) text-[10px] font-bold flex items-center justify-center">
                                            {{ mb_strtoupper(mb_substr($order->customer->first_name, 0, 1).mb_substr($order->customer->last_name, 0, 1)) }}
                                        </span>
                                        <span class="text-sm truncate">{{ $order->customer->fullName() }}</span>
                                    </div>
                                    <div><x-status-badge :status="$order->status" /></div>
                                    <div class="text-right text-sm font-bold tabular-nums">{{ number_format($order->total_fcfa, 0, ',', ' ') }} F</div>
                                    <div class="text-right text-[13.5px] text-(--color-text-muted)">{{ $order->dropped_off_at->format('d/m') }}</div>
                                    <div class="text-right text-(--color-border) text-[15px]">›</div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-5">
                <div class="bg-(--color-surface) border border-(--color-border) rounded-xl px-[18px] py-[17px]">
                    <div class="text-[13.5px] font-bold">À traiter aujourd'hui</div>
                    <div class="text-xs text-(--color-text-muted) mt-0.5 mb-3.5">Ce qui bloque l'atelier</div>
                    <div class="flex flex-col gap-2">
                        @php
                            $taskItems = [
                                ['title' => 'Prêtes depuis > 5 jours', 'sub' => 'Relancer le client', 'count' => $tasks['readyOverdue'], 'color' => 'var(--color-warning)', 'route' => 'orders.index'],
                                ['title' => 'Commandes en file d\'attente', 'sub' => 'Pas encore démarrées', 'count' => $tasks['received'], 'color' => 'var(--color-info)', 'route' => 'orders.index'],
                                ['title' => 'Signalement ouvert', 'sub' => $tasks['openIssues'] > 1 ? 'À traiter' : 'Ticket en attente', 'count' => $tasks['openIssues'], 'color' => 'var(--color-error)', 'route' => 'issues.index'],
                            ];
                        @endphp
                        @foreach ($taskItems as $task)
                            <a href="{{ route($task['route']) }}" wire:navigate class="flex items-center gap-2.5 px-2 py-2 rounded-lg hover:bg-(--color-bg) transition-colors duration-150">
                                <div class="w-1 h-[30px] rounded-full flex-none" style="background:{{ $task['color'] }}"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[12.5px] font-semibold leading-tight">{{ $task['title'] }}</div>
                                    <div class="text-[11.5px] text-(--color-text-muted) mt-px">{{ $task['sub'] }}</div>
                                </div>
                                <div class="text-[15px] font-bold tabular-nums">{{ $task['count'] }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl p-[18px] text-white" style="background:#0f172a;">
                    <div class="text-[10.5px] font-semibold tracking-widest text-(--color-text-muted) uppercase">Encaissé · 7 derniers jours</div>
                    <div class="flex items-baseline gap-2 mt-[11px]">
                        <div class="text-[29px] font-bold tracking-tight tabular-nums">{{ number_format($revenueLast7, 0, ',', ' ') }}</div>
                        <div class="text-sm font-semibold text-(--color-text-muted)">F</div>
                    </div>
                    @if ($revenueChangePct !== null)
                        <div class="text-xs font-semibold mt-1 {{ $revenueChangePct >= 0 ? 'text-(--color-success)' : 'text-(--color-error)' }}">
                            {{ $revenueChangePct >= 0 ? '↑' : '↓' }} {{ abs($revenueChangePct) }} % vs semaine passée
                        </div>
                    @else
                        <div class="text-xs text-(--color-text-muted) mt-1">Pas de comparaison disponible</div>
                    @endif
                    @php $bestDayIndex = $spark->max() > 0 ? $spark->search($spark->max()) : null; @endphp
                    <div class="flex items-end gap-[5px] h-[52px] mt-[18px]">
                        @foreach ($spark as $i => $value)
                            <div class="flex-1 rounded animate-step" style="height:{{ max(4, round($value / $sparkMax * 100)) }}%;background:{{ $i === $bestDayIndex ? 'var(--color-success)' : '#1e293b' }};animation-delay:{{ $i * 60 }}ms"></div>
                        @endforeach
                    </div>
                    <div class="flex justify-between mt-2 text-[9.5px] text-(--color-text-muted)">
                        @foreach ($sparkLabels as $d)
                            <span>{{ $d }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
