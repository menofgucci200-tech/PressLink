<div>
    <div class="flex items-start justify-between gap-6 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Commandes</h1>
            <p class="text-sm text-(--color-text-muted)">{{ is_countable($orders) ? $orders->total() : 0 }} commande(s)</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="relative">
                <button type="button" wire:click="$toggle('showExportMenu')"
                        class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Exporter
                </button>
                @if ($showExportMenu)
                    @php
                        $exportParams = ['status' => $status, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo];
                    @endphp
                    <div class="absolute right-0 mt-2 w-44 bg-(--color-surface) border border-(--color-border) rounded-lg shadow-lg overflow-hidden z-10">
                        <a href="{{ route('orders.export', ['format' => 'csv', ...$exportParams]) }}" class="block px-4 py-2.5 text-sm hover:bg-(--color-bg)">CSV</a>
                        <a href="{{ route('orders.export', ['format' => 'xlsx', ...$exportParams]) }}" class="block px-4 py-2.5 text-sm hover:bg-(--color-bg)">Excel (.xlsx)</a>
                        <a href="{{ route('orders.export', ['format' => 'pdf', ...$exportParams]) }}" class="block px-4 py-2.5 text-sm hover:bg-(--color-bg)">PDF</a>
                    </div>
                @endif
            </div>
            <a href="{{ route('orders.create') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nouvelle commande
            </a>
        </div>
    </div>

    <div class="flex items-end gap-3 mb-5 flex-wrap">
        <div class="relative flex-1 max-w-sm">
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Recherche</label>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 translate-y-[3px] text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="N°, client, téléphone…"
                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-white">
        </div>
        <div>
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Déposée à partir du</label>
            <input type="date" wire:model.live="dateFrom" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-white">
        </div>
        <div>
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Jusqu'au</label>
            <input type="date" wire:model.live="dateTo" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-white">
        </div>
        @if ($search !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== '')
            <button type="button" wire:click="resetFilters" class="h-10 px-3.5 rounded-lg text-[13px] font-medium text-(--color-text-secondary) hover:text-(--color-error)">
                Réinitialiser
            </button>
        @endif
    </div>

    <div class="flex gap-2 mb-5 flex-wrap">
        <button wire:click="$set('status', '')"
                class="h-9 px-3.5 rounded-lg text-[13px] font-medium border {{ $status === '' ? 'bg-(--color-primary) text-white border-(--color-primary)' : 'border-(--color-border) text-(--color-text-secondary)' }}">
            Toutes <span class="tabular-nums opacity-80">{{ array_sum($counts) }}</span>
        </button>
        @foreach ($statuses as $s)
            <button wire:click="$set('status', '{{ $s->value }}')"
                    class="h-9 px-3.5 rounded-lg text-[13px] font-medium border {{ $status === $s->value ? 'bg-(--color-primary) text-white border-(--color-primary)' : 'border-(--color-border) text-(--color-text-secondary)' }}">
                {{ $s->label() }} <span class="tabular-nums opacity-80">{{ $counts[$s->value] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @include('livewire.orders._table', ['orders' => $orders, 'showPhone' => true])
    </div>

    @if (is_countable($orders) && method_exists($orders, 'links'))
        <div class="mt-5">{{ $orders->links() }}</div>
    @endif
</div>
