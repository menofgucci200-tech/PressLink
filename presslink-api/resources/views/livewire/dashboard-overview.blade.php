<div>
    <div class="flex items-start justify-between gap-6 mb-7">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Vue d'ensemble</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $rows->count() }} pressing(s) · {{ now()->translatedFormat('l j F Y') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-7">
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-2 h-2 rounded-full" style="background:var(--color-info)"></span>
                <span class="text-[13px] font-medium text-(--color-text-secondary)">Commandes aujourd'hui (tous pressings)</span>
            </div>
            <div class="text-3xl font-bold tabular-nums">{{ $totals['today_count'] }}</div>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-2 h-2 rounded-full" style="background:var(--color-secondary)"></span>
                <span class="text-[13px] font-medium text-(--color-text-secondary)">Commandes en cours (tous pressings)</span>
            </div>
            <div class="text-3xl font-bold tabular-nums">{{ $totals['open_count'] }}</div>
        </div>
    </div>

    <div class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Vos pressings</div>
    <div class="grid grid-cols-2 gap-4 mb-7">
        @foreach ($rows as $row)
            <a href="{{ route('pressings.switch', $row['pressing']) }}"
               class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 hover:border-(--color-primary) transition-colors">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-semibold text-sm mb-0.5">{{ $row['pressing']->name }}</div>
                        @if ($row['pressing']->city)
                            <div class="text-xs text-(--color-text-muted)">{{ $row['pressing']->city }}</div>
                        @endif
                    </div>
                    <span class="text-xs font-medium text-(--color-primary)">Ouvrir →</span>
                </div>
                <div class="flex items-center gap-4 mt-4">
                    <div>
                        <div class="text-xl font-bold tabular-nums">{{ $row['today_count'] }}</div>
                        <div class="text-[11px] text-(--color-text-muted)">aujourd'hui</div>
                    </div>
                    <div>
                        <div class="text-xl font-bold tabular-nums">{{ $row['open_count'] }}</div>
                        <div class="text-[11px] text-(--color-text-muted)">en cours</div>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-(--color-border)">
            <h2 class="font-semibold text-sm">Commandes récentes (tous pressings)</h2>
        </div>
        @if ($recent->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucune commande pour le moment.</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">N°</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Pressing</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Client</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Statut</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recent as $order)
                        <tr class="border-t border-(--color-border)">
                            <td class="px-5 py-3.5 font-semibold tabular-nums">{{ $order->order_number }}</td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('pressings.switch', $order->pressing) }}" class="text-(--color-primary) hover:underline">
                                    {{ $order->pressing->name }}
                                </a>
                            </td>
                            <td class="px-5 py-3.5">{{ $order->customer->fullName() }}</td>
                            <td class="px-5 py-3.5"><x-status-badge :status="$order->status" /></td>
                            <td class="px-5 py-3.5 text-right font-semibold tabular-nums">{{ number_format($order->total_fcfa, 0, ',', ' ') }} F</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
