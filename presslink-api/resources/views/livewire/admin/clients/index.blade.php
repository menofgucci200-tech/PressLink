<div>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Clients</h1>
        <p class="text-sm text-(--color-text-muted)">{{ $customers->total() }} client(s) sur la plateforme.</p>
    </div>

    <div class="relative max-w-sm mb-5">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Nom ou téléphone…"
               class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-white">
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($customers->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun client trouvé.</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Client</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Téléphone</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Pressings rejoints</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Commandes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg)">
                            <td class="px-5 py-3.5 font-medium">{{ $customer->fullName() }}</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary) tabular-nums">{{ $customer->phone }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $customer->pressings_count }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $customer->orders_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if (method_exists($customers, 'links'))
        <div class="mt-5">{{ $customers->links() }}</div>
    @endif
</div>
