<div>
    <div class="flex items-start justify-between gap-6 mb-7">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Vue globale</h1>
            <p class="text-sm text-(--color-text-muted)">{{ now()->translatedFormat('l j F Y') }}</p>
        </div>
        <a href="{{ route('admin.pressings.create') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Nouveau pressing
        </a>
    </div>

    <div class="grid grid-cols-4 gap-4 mb-7">
        @php
            $kpis = [
                ['label' => 'Pressings actifs', 'value' => $activePressings, 'color' => 'var(--color-success)'],
                ['label' => 'Pressings suspendus', 'value' => $suspendedPressings, 'color' => 'var(--color-warning)'],
                ['label' => 'Clients (plateforme)', 'value' => $totalClients, 'color' => 'var(--color-info)'],
                ['label' => 'Commandes ce mois-ci', 'value' => $ordersThisMonth, 'color' => 'var(--color-primary)'],
            ];
        @endphp
        @foreach ($kpis as $kpi)
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-2 h-2 rounded-full" style="background:{{ $kpi['color'] }}"></span>
                    <span class="text-[13px] font-medium text-(--color-text-secondary)">{{ $kpi['label'] }}</span>
                </div>
                <div class="text-3xl font-bold tabular-nums">{{ $kpi['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-7">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Commandes totales (toute la plateforme)</div>
        <div class="font-display text-2xl font-bold tabular-nums">{{ $totalOrders }}</div>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-(--color-border)">
            <h2 class="font-semibold text-sm">Pressings récents</h2>
            <a href="{{ route('admin.pressings.index') }}" class="text-sm font-medium text-(--color-primary)">Voir tout →</a>
        </div>
        @if ($recentPressings->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun pressing pour le moment.</div>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($recentPressings as $pressing)
                        <tr class="border-t border-(--color-border) first:border-t-0 hover:bg-(--color-bg) cursor-pointer" onclick="window.location='{{ route('admin.pressings.show', $pressing) }}'">
                            <td class="px-5 py-3.5 font-medium">{{ $pressing->name }}</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">{{ $pressing->city }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-xs font-medium {{ $pressing->status->value === 'active' ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">
                                    {{ $pressing->status->label() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
