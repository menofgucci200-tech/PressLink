@if ($orders->isEmpty())
    <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">
        Aucune commande pour le moment.
    </div>
@else
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-(--color-bg) text-left">
                <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">N°</th>
                <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Client</th>
                @if ($showPhone ?? false)
                    <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Téléphone</th>
                @endif
                <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Statut</th>
                <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Total</th>
                <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Dépôt</th>
                <th class="px-5 py-2.5"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orders as $order)
                <tr class="border-t border-(--color-border) hover:bg-(--color-bg)">
                    <td class="px-5 py-3.5 font-semibold tabular-nums">{{ $order->order_number }}</td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-full bg-(--color-border) flex items-center justify-center text-[11px] font-semibold">
                                {{ mb_strtoupper(mb_substr($order->customer->first_name, 0, 1).mb_substr($order->customer->last_name, 0, 1)) }}
                            </span>
                            {{ $order->customer->fullName() }}
                        </div>
                    </td>
                    @if ($showPhone ?? false)
                        <td class="px-5 py-3.5 text-(--color-text-secondary) tabular-nums">{{ $order->customer->phone }}</td>
                    @endif
                    <td class="px-5 py-3.5"><x-status-badge :status="$order->status" /></td>
                    <td class="px-5 py-3.5 text-right font-semibold tabular-nums">{{ number_format($order->total_fcfa, 0, ',', ' ') }} F</td>
                    <td class="px-5 py-3.5 text-(--color-text-secondary)">{{ $order->dropped_off_at->format('d/m') }}</td>
                    <td class="px-5 py-3.5 text-right">
                        <a href="{{ route('orders.show', $order) }}" class="text-(--color-text-muted) hover:text-(--color-primary)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"></path></svg>
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
