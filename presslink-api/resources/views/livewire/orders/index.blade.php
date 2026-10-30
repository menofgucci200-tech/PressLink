{{-- Toutes les 30 s (onglet visible) : statuts à jour et nouvelles commandes annoncées. --}}
<div wire:poll.30s.visible="checkForNewOrders">
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Commandes</h1>
            <p class="text-sm text-(--color-text-muted)">{{ is_countable($orders) ? $orders->total() : 0 }} commande(s)</p>
        </div>
        <div class="flex items-center gap-2">
            @if (auth()->user()->may(\App\Enums\Permission::ReportsExport))
            <div class="relative">
                <button type="button" wire:click="$toggle('showExportMenu')"
                        class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Exporter
                </button>
                @if ($showExportMenu)
                    @php
                        $exportParams = ['status' => $status, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'payment' => $payment];
                        $hasFilters = $status !== '' || $search !== '' || $dateFrom !== '' || $dateTo !== '' || $payment !== '';
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
            @else
            <x-locked-export />
            @endif
            @if (auth()->user()->may(\App\Enums\Permission::OrdersCreate))
            <a href="{{ route('orders.create') }}" wire:navigate class="max-lg:hidden inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nouvelle commande
            </a>
            @endif
        </div>
    </div>

    {{-- Téléphone : la recherche reste visible, dates et paiement se déplient d'un toucher --}}
    <div class="flex items-end gap-3 mb-5 flex-wrap" x-data="{ filtres: @js($dateFrom !== '' || $dateTo !== '' || $payment !== '') }">
        <div class="relative flex-1 min-w-0 sm:flex-1 sm:max-w-sm">
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Recherche</label>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 translate-y-[3px] text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="N°, client, téléphone…"
                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-(--color-surface)">
        </div>
        <button type="button" @click="filtres = !filtres" :aria-expanded="filtres"
                class="sm:hidden h-10 px-3 rounded-lg border border-(--color-border) text-sm font-semibold inline-flex items-center gap-1.5"
                :class="filtres ? 'border-(--color-primary) text-(--color-primary)' : ''">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
            Filtres
            @if ($dateFrom !== '' || $dateTo !== '' || $payment !== '')
                <span class="w-2 h-2 rounded-full bg-(--color-primary)"></span>
            @endif
        </button>
        <div class="max-sm:basis-[calc(50%-0.375rem)]" :class="filtres ? '' : 'max-sm:hidden'">
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Déposée à partir du</label>
            <input type="date" wire:model.live="dateFrom" class="max-sm:w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-(--color-surface)">
        </div>
        <div class="max-sm:basis-[calc(50%-0.375rem)]" :class="filtres ? '' : 'max-sm:hidden'">
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Jusqu'au</label>
            <input type="date" wire:model.live="dateTo" class="max-sm:w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-(--color-surface)">
        </div>
        <div class="max-sm:w-full" :class="filtres ? '' : 'max-sm:hidden'">
            <label for="payment-filter" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Paiement</label>
            <select id="payment-filter" wire:model.live="payment" class="max-sm:w-full h-10 pl-3 pr-8 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-(--color-surface)">
                <option value="">Tous</option>
                <option value="{{ \App\Models\Pressing::UNSETTLED_FILTER }}">Non soldées</option>
                @foreach (\App\Enums\PaymentStatus::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>
        @if ($search !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== '' || $payment !== '' || $sort !== 'recent')
            <button type="button" wire:click="resetFilters" class="h-10 px-3.5 rounded-lg text-[13px] font-medium text-(--color-text-secondary) hover:text-(--color-error)">
                Réinitialiser
            </button>
        @endif
    </div>

    {{-- Le nombre de chaque filtre suit la recherche et les dates. --}}
    <x-status-filter-chips :current="$status" :counts="$counts" :statuses="$statuses" class="mb-5" />

    @if ($newOrdersCount > 0)
        <div class="sticky top-[76px] z-[6] flex justify-center -mb-2 pointer-events-none">
            <button type="button" wire:click="showNewOrders"
                    class="pointer-events-auto mb-3 inline-flex items-center gap-2 h-9 px-4 rounded-full bg-(--color-primary) text-white text-[13px] font-semibold shadow-[0_10px_24px_-10px_var(--color-primary)] hover:bg-(--color-primary-600) animate-step">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"></path><path d="m5 12 7-7 7 7"></path></svg>
                {{ $newOrdersCount }} nouvelle{{ $newOrdersCount > 1 ? 's' : '' }} commande{{ $newOrdersCount > 1 ? 's' : '' }} · Afficher
            </button>
        </div>
    @endif

    {{-- Pendant un filtre, un tri ou un changement de page : lignes estompées
         et reflet animé plutôt qu'un écran figé. --}}
    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-clip list-loading-target"
         wire:loading.delay.class="is-loading"
         wire:target="search, status, dateFrom, dateTo, payment, sortBy, resetFilters, showNewOrders, gotoPage, nextPage, previousPage">
        @include('livewire.orders._table', [
            'orders' => $orders,
            'showPhone' => true,
            'statusFilterable' => true,
            'paymentFilterable' => true,
            'sortable' => true,
            'previewable' => true,
            'previewId' => $previewId,
            'selectable' => true,
            'selected' => $selected,
            'hasFilters' => $search !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== '' || $payment !== '',
        ])
    </div>

    {{-- Actions groupées sur les commandes cochées --}}
    @if ($selected !== [])
        @php $selectedCount = count($selected); @endphp
        <div class="fixed bottom-[calc(5rem+env(safe-area-inset-bottom))] lg:bottom-5 inset-x-4 lg:left-[calc(239px+2rem)] lg:right-8 z-30 flex justify-center pointer-events-none">
            <div class="pointer-events-auto flex flex-wrap items-center gap-2 rounded-2xl bg-slate-900 text-white shadow-2xl px-4 py-3 animate-step dark:ring-1 dark:ring-white/10"
                 role="region" aria-label="Actions sur la sélection">
                <span class="text-sm font-semibold mr-1 tabular-nums">{{ $selectedCount }} sélectionnée{{ $selectedCount > 1 ? 's' : '' }}</span>
                @foreach ([\App\Enums\OrderStatus::Traitement, \App\Enums\OrderStatus::Prete, \App\Enums\OrderStatus::Recuperee] as $bulkStatus)
                    <button type="button" wire:click="bulkTransition('{{ $bulkStatus->value }}')"
                            data-confirm-title="Changer le statut" data-confirm-label="Marquer {{ mb_strtolower($bulkStatus->label()) }}"
                            wire:confirm="Marquer {{ $selectedCount }} commande{{ $selectedCount > 1 ? 's' : '' }} « {{ $bulkStatus->label() }} » ? Celles dont le statut ne le permet pas{{ $bulkStatus === \App\Enums\OrderStatus::Recuperee ? ', ou qui ont un reste à payer,' : '' }} seront laissées de côté.{{ $bulkStatus === \App\Enums\OrderStatus::Prete ? ' Les clients seront prévenus.' : '' }}"
                            wire:loading.attr="disabled" wire:target="bulkTransition"
                            class="h-9 px-3.5 rounded-lg bg-white/10 hover:bg-white/20 text-[13px] font-semibold disabled:opacity-50">
                        {{ $bulkStatus === \App\Enums\OrderStatus::Recuperee ? 'Remettre (soldées)' : 'Marquer '.mb_strtolower($bulkStatus->label()) }}
                    </button>
                @endforeach
                <a href="{{ route('orders.receipts', ['ids' => implode(',', $selected)]) }}" target="_blank" rel="noopener"
                   class="h-9 px-3.5 rounded-lg bg-white/10 hover:bg-white/20 text-[13px] font-semibold inline-flex items-center gap-1.5">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path></svg>
                    Imprimer les reçus
                </a>
                <button type="button" wire:click="clearSelection" aria-label="Annuler la sélection" data-tooltip="Annuler la sélection"
                        class="w-9 h-9 rounded-lg hover:bg-white/15 flex items-center justify-center">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>
    @endif

    @if (is_countable($orders) && method_exists($orders, 'links'))
        <div class="mt-5">{{ $orders->links() }}</div>
    @endif

    @if ($previewOrder)
        @include('livewire.orders._preview-panel')
    @endif
</div>
