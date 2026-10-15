@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $range = ['du' => $periodFrom, 'au' => $periodTo];
    $kpis = [
        ['label' => 'Encaissé', 'value' => $totals['revenue'], 'unit' => 'F', 'hint' => $periodLabel, 'href' => route('group.reports'), 'color' => 'var(--color-success)'],
        ['label' => 'Reste à encaisser', 'value' => $totals['outstanding'], 'unit' => 'F', 'hint' => 'acomptes et impayés', 'href' => route('group.orders', ['paiement' => \App\Models\Pressing::UNSETTLED_FILTER]), 'color' => 'var(--color-warning)'],
        ['label' => 'Commandes déposées', 'value' => $totals['orders'], 'unit' => null, 'hint' => $periodLabel.' · panier moyen '.$fcfa($totals['average_basket']).' F', 'href' => route('group.orders', $range), 'color' => 'var(--color-primary)'],
        ['label' => 'En cours', 'value' => $totals['in_progress'], 'unit' => null, 'hint' => 'reçues, en traitement, en attente', 'href' => route('group.orders', ['statut' => 'en_cours']), 'color' => 'var(--color-secondary)'],
        ['label' => 'Prêtes à retirer', 'value' => $totals['ready'], 'unit' => null, 'hint' => $totals['ready_overdue'] > 0 ? $totals['ready_overdue'].' depuis plus de '.\App\Models\Order::READY_REMINDER_DAYS.' j' : 'aucune en souffrance', 'href' => route('group.orders', ['statut' => 'prete']), 'color' => 'var(--color-success-text)'],
        ['label' => 'En retard', 'value' => $totals['late'], 'unit' => null, 'hint' => 'date de retrait dépassée', 'href' => route('group.orders', ['retard' => 1]), 'color' => 'var(--color-error)'],
    ];
    $tones = [
        'error' => ['var(--color-error-tint)', 'var(--color-error)'],
        'warning' => ['var(--color-warning-tint)', 'var(--color-warning-text)'],
        'info' => ['var(--color-info-tint)', 'var(--color-info)'],
    ];
    $sortedRows = $rows->sortByDesc(fn ($r) => $r[$groupSort] ?? $r['revenue'])->values();
    $th = 'px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)';
@endphp

<div wire:poll.60s.visible>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Vue d'ensemble</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $rows->count() }} pressings · {{ now()->translatedFormat('l j F Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('group.orders') }}" wire:navigate class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm font-semibold hover:border-(--color-primary)">Commandes du groupe</a>
            <a href="{{ route('group.reports') }}" wire:navigate class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Rapport du mois</a>
        </div>
    </div>

    <div class="flex items-center gap-2 mb-4 flex-wrap">
        @foreach (['today' => "Aujourd'hui", '7d' => '7 jours', '30d' => '30 jours', 'custom' => 'Personnalisé'] as $value => $label)
            <button type="button" wire:click="setPeriod('{{ $value }}')"
                    class="h-8 px-3 rounded-lg text-[12.5px] font-semibold transition-colors duration-150 {{ $period === $value ? 'bg-(--color-primary) text-white' : 'bg-(--color-surface) border border-(--color-border) text-(--color-text-secondary) hover:border-(--color-primary)' }}">
                {{ $label }}
            </button>
        @endforeach
        @if ($period === 'custom')
            <div class="flex items-center gap-2 animate-step">
                <input type="date" wire:model.live="customFrom" aria-label="Du" class="h-8 px-2 rounded-lg border border-(--color-border) text-[12.5px]">
                <span class="text-(--color-text-muted) text-xs">→</span>
                <input type="date" wire:model.live="customTo" aria-label="Au" class="h-8 px-2 rounded-lg border border-(--color-border) text-[12.5px]">
            </div>
        @endif
    </div>

    {{-- Totaux du groupe --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-4 mb-6 loading-dim rounded-xl"
         wire:loading.delay.class="is-loading" wire:target="setPeriod, customFrom, customTo">
        @foreach ($kpis as $kpi)
            <a href="{{ $kpi['href'] }}" wire:navigate class="kpi-link block bg-(--color-surface) border border-(--color-border) rounded-xl p-4">
                <div class="flex items-center gap-2">
                    <span class="w-[7px] h-[7px] rounded-full" style="background:{{ $kpi['color'] }}"></span>
                    <span class="text-[13px] font-medium text-(--color-text-secondary)">{{ $kpi['label'] }}</span>
                </div>
                <div class="flex items-baseline gap-1.5 mt-2.5">
                    <span class="text-[26px] font-bold tracking-tight tabular-nums" data-count="{{ $kpi['value'] }}" data-count-key="group-{{ $loop->index }}">{{ $fcfa($kpi['value']) }}</span>
                    @if ($kpi['unit'])<span class="text-sm font-semibold text-(--color-text-muted)">{{ $kpi['unit'] }}</span>@endif
                </div>
                <div class="text-[11.5px] text-(--color-text-muted) mt-1 truncate">{{ $kpi['hint'] }}</div>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-5 mb-6 items-start">
        @include('livewire.group._revenue-chart', ['chart' => $chart])

        {{-- Alertes du groupe --}}
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-(--color-border)">
                <h2 class="font-semibold text-sm">À surveiller</h2>
                @if ($alerts !== [])
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-(--color-error-tint) text-(--color-error)">{{ count($alerts) }}</span>
                @endif
            </div>
            @forelse ($alerts as $alert)
                <a href="{{ $alert['url'] }}" class="flex items-start gap-3 px-5 py-3 border-b border-(--color-border) last:border-b-0 hover:bg-(--color-bg)">
                    <span class="mt-1 w-2 h-2 rounded-full flex-none" style="background:{{ $tones[$alert['tone']][1] }}"></span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">{{ $alert['pressing']->name }}</span>
                        <span class="block text-sm font-semibold">{{ $alert['title'] }}</span>
                        <span class="block text-xs text-(--color-text-muted)">{{ $alert['hint'] }}</span>
                    </span>
                    <span class="text-(--color-text-muted)" aria-hidden="true">›</span>
                </a>
            @empty
                <x-empty-state icon="check" tone="success" title="Rien à signaler">
                    Pas de retard, de signalement ni de commande oubliée dans vos pressings.
                </x-empty-state>
            @endforelse
        </div>
    </div>

    {{-- Comparatif des pressings --}}
    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-clip mb-8">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-(--color-border)">
            <div>
                <h2 class="font-semibold text-sm">Comparatif des pressings</h2>
                <p class="text-xs text-(--color-text-muted)">{{ ucfirst($periodLabel) }} · cliquez sur un en-tête pour trier</p>
            </div>
        </div>
        <div class="overflow-x-auto lg:overflow-visible">
            <table class="w-full min-w-[980px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="text-left bg-(--color-bg)">
                        <th class="{{ $th }}">Pressing</th>
                        @foreach (['orders' => 'Commandes', 'revenue' => 'Encaissé', 'average_basket' => 'Panier moyen', 'turnaround_hours' => 'Délai moyen', 'outstanding' => 'Reste dû', 'late' => 'Retards', 'ready_overdue' => 'Prêtes > '.\App\Models\Order::READY_REMINDER_DAYS.' j', 'open_issues' => 'Signalements'] as $key => $label)
                            <th class="{{ $th }} text-right">
                                <button type="button" wire:click="$set('groupSort', '{{ $key }}')" class="uppercase tracking-wide {{ $groupSort === $key ? 'text-(--color-text-primary)' : 'hover:text-(--color-text-secondary)' }}">
                                    {{ $label }}{{ $groupSort === $key ? ' ↓' : '' }}
                                </button>
                            </th>
                        @endforeach
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sortedRows as $row)
                        @php $p = $row['pressing']; @endphp
                        <tr wire:key="cmp-{{ $p->id }}" data-href="{{ route('pressings.switch', $p) }}" tabindex="0" role="link" class="border-t border-(--color-border)">
                            <td class="px-4 py-3">
                                <div class="font-semibold">{{ $p->name }}</div>
                                <div class="text-xs text-(--color-text-muted)">{{ $p->city ?? '—' }} · <span class="font-mono">{{ $p->code }}</span></div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ $row['orders'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <div class="font-semibold">{{ $fcfa($row['revenue']) }} F</div>
                                @if ($row['revenue_change_pct'] !== null)
                                    <div class="text-[11px] font-semibold {{ $row['revenue_change_pct'] >= 0 ? 'text-(--color-success-text)' : 'text-(--color-error)' }}" data-tooltip="Par rapport à la période précédente">{{ $row['revenue_change_pct'] >= 0 ? '↑' : '↓' }} {{ abs($row['revenue_change_pct']) }} %</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $fcfa($row['average_basket']) }} F</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                @if ($row['turnaround_hours'] === null)
                                    <span class="text-(--color-text-muted)">—</span>
                                @elseif ($row['turnaround_hours'] < 48)
                                    {{ $row['turnaround_hours'] }} h
                                @else
                                    {{ round($row['turnaround_hours'] / 24, 1) }} j
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums {{ $row['outstanding'] > 0 ? 'text-(--color-warning-text) font-semibold' : 'text-(--color-text-muted)' }}">{{ $fcfa($row['outstanding']) }} F</td>
                            @foreach (['late' => 'error', 'ready_overdue' => 'warning-text', 'open_issues' => 'error'] as $key => $tone)
                                <td class="px-4 py-3 text-right tabular-nums">
                                    @if ($row[$key] > 0)
                                        <span class="font-bold text-(--color-{{ $tone }})">{{ $row[$key] }}</span>
                                    @else
                                        <span class="text-(--color-text-muted)">0</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('pressings.switch', $p) }}" tabindex="-1" class="row-chevron text-(--color-text-muted)" aria-hidden="true">›</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-(--color-border) bg-(--color-bg) font-semibold">
                        <td class="px-4 py-3">Groupe</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $totals['orders'] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $fcfa($totals['revenue']) }} F</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $fcfa($totals['average_basket']) }} F</td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $fcfa($totals['outstanding']) }} F</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $totals['late'] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $totals['ready_overdue'] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $totals['open_issues'] }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
