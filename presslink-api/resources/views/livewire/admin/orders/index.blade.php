<div>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Commandes</h1>
        <p class="text-sm text-(--color-text-muted)">{{ $orders->total() }} commande(s) sur la plateforme.</p>
    </div>

    <div class="flex items-center gap-3 mb-5">
        <div class="relative max-w-sm flex-1">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="N° commande, client ou pressing…"
                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-white">
        </div>
        <select wire:model.live="status" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-white">
            <option value="">Tous les statuts</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($orders->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucune commande trouvée.</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">N°</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Pressing</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Client</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Statut</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Total</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Dépôt</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg) cursor-pointer"
                            onclick="window.location='{{ route('admin.pressings.show', $order->pressing) }}'">
                            <td class="px-5 py-3.5 font-semibold tabular-nums">{{ $order->order_number }}</td>
                            <td class="px-5 py-3.5">{{ $order->pressing->name }}</td>
                            <td class="px-5 py-3.5">{{ $order->customer->fullName() }}</td>
                            <td class="px-5 py-3.5"><x-status-badge :status="$order->status" /></td>
                            <td class="px-5 py-3.5 text-right font-semibold tabular-nums">{{ number_format($order->total_fcfa, 0, ',', ' ') }} F</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">{{ $order->dropped_off_at->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if (method_exists($orders, 'links'))
        <div class="mt-5">{{ $orders->links() }}</div>
    @endif
</div>
