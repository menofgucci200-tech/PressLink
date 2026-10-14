<div>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
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
                        $hasFilters = $status !== '' || $search !== '' || $dateFrom !== '' || $dateTo !== '';
                    @endphp
                    <div class="absolute right-0 mt-2 w-56 bg-(--color-surface) border border-(--color-border) rounded-lg shadow-lg overflow-hidden z-10 animate-step">
                        <div class="px-4 pt-3 pb-2 text-[11px] text-(--color-text-muted) border-b border-(--color-border)">
                            {{ $hasFilters ? 'Avec les filtres actuels' : 'Toutes les commandes' }}
                        </div>
                        <a href="{{ route('orders.export', ['format' => 'csv', ...$exportParams]) }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-(--color-bg)">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-none text-(--color-text-muted)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                            CSV
                        </a>
                        <a href="{{ route('orders.export', ['format' => 'xlsx', ...$exportParams]) }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-(--color-bg)">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-none text-(--color-success)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="13" x2="15" y2="19"></line><line x1="15" y1="13" x2="9" y2="19"></line></svg>
                            Excel (.xlsx)
                        </a>
                        <a href="{{ route('orders.export', ['format' => 'pdf', ...$exportParams]) }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-(--color-bg)">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-none text-(--color-error)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="10" y1="15" x2="10" y2="18"></line><line x1="13" y1="13" x2="13" y2="18"></line></svg>
                            PDF
                        </a>
                    </div>
                @endif
            </div>
            <a href="{{ route('orders.create') }}" wire:navigate class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nouvelle commande
            </a>
        </div>
    </div>

    <div class="flex items-end gap-3 mb-5 flex-wrap">
        <div class="relative w-full sm:w-auto sm:flex-1 sm:max-w-sm">
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Recherche</label>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 translate-y-[3px] text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="N°, client, téléphone…"
                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-(--color-surface)">
        </div>
        <div>
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Déposée à partir du</label>
            <input type="date" wire:model.live="dateFrom" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-(--color-surface)">
        </div>
        <div>
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Jusqu'au</label>
            <input type="date" wire:model.live="dateTo" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-(--color-surface)">
        </div>
        @if ($search !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== '')
            <button type="button" wire:click="resetFilters" class="h-10 px-3.5 rounded-lg text-[13px] font-medium text-(--color-text-secondary) hover:text-(--color-error)">
                Réinitialiser
            </button>
        @endif
    </div>

    {{-- Le nombre de chaque filtre suit la recherche et les dates. --}}
    <x-status-filter-chips :current="$status" :counts="$counts" :statuses="$statuses" class="mb-5" />

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @include('livewire.orders._table', ['orders' => $orders, 'showPhone' => true, 'statusFilterable' => true])
    </div>

    @if (is_countable($orders) && method_exists($orders, 'links'))
        <div class="mt-5">{{ $orders->links() }}</div>
    @endif
</div>
