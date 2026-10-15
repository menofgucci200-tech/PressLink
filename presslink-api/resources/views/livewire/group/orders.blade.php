@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $field = 'h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $th = 'px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) lg:sticky lg:top-16 lg:z-[5] bg-(--color-bg)';
@endphp
<div wire:poll.30s.visible>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Commandes du groupe</h1>
            <p class="text-sm text-(--color-text-muted)">
                {{ $fcfa($summary['count']) }} commande(s){{ $hasFilters ? ' pour ces filtres' : '' }} · {{ $pressings->count() }} pressings
                · <span class="font-semibold text-(--color-text-secondary)">{{ $fcfa($summary['volume']) }} F</span> hors annulées
                @if ($summary['due'] > 0) · <span class="font-semibold text-(--color-warning-text)">{{ $fcfa($summary['due']) }} F</span> à encaisser @endif
            </p>
        </div>
        <a href="{{ route('group.export', ['type' => 'commandes', 'format' => 'xlsx', ...array_filter($this->filters())]) }}"
           class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Exporter (Excel)
        </a>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <div class="relative w-full sm:w-auto sm:flex-1 sm:max-w-xs">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="N°, client, téléphone…" aria-label="Rechercher"
                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
        </div>
        <select wire:model.live="pressingId" class="{{ $field }}" aria-label="Pressing">
            <option value="">Tous les pressings</option>
            @foreach ($pressings as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" class="{{ $field }}" aria-label="Statut">
            <option value="">Tous les statuts</option>
            <option value="{{ \App\Livewire\Group\Orders::IN_PROGRESS }}">En cours (reçues, traitement, attente)</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="payment" class="{{ $field }}" aria-label="Paiement">
            <option value="">Tout paiement</option>
            <option value="{{ $unsettled }}">Non soldées</option>
            @foreach (\App\Enums\PaymentStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
        <div class="flex items-center gap-2">
            <input type="date" wire:model.live="dateFrom" class="{{ $field }}" aria-label="Déposée à partir du">
            <span class="text-xs text-(--color-text-muted)">au</span>
            <input type="date" wire:model.live="dateTo" class="{{ $field }}" aria-label="Jusqu'au">
        </div>
        <label class="inline-flex items-center gap-2 text-sm select-none">
            <input type="checkbox" wire:model.live="lateOnly" class="w-4 h-4 accent-(--color-primary)"> En retard
        </label>
        @if ($hasFilters)
            <button type="button" wire:click="resetFilters" class="text-xs font-medium text-(--color-text-muted) hover:text-(--color-primary) underline">Réinitialiser</button>
        @endif
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-clip list-loading-target"
         wire:loading.delay.class="is-loading" wire:target="search, pressingId, status, payment, dateFrom, dateTo, lateOnly, resetFilters, gotoPage, nextPage, previousPage">
        @if ($orders->isEmpty())
            @if ($hasFilters)
                <x-empty-state icon="search" title="Aucune commande ne correspond à ces filtres">
                    Élargissez la période ou retirez un filtre.
                    <x-slot:actions>
                        <button type="button" wire:click="resetFilters" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary)">Réinitialiser les filtres</button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <x-empty-state icon="box" title="Aucune commande dans le groupe pour le moment" />
            @endif
        @else
            <div class="overflow-x-auto lg:overflow-visible"><table class="w-full min-w-[900px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="{{ $th }}">N°</th>
                        <th class="{{ $th }}">Pressing</th>
                        <th class="{{ $th }}">Client</th>
                        <th class="{{ $th }}">Statut</th>
                        <th class="{{ $th }} text-right">Total</th>
                        <th class="{{ $th }}">Dépôt</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        @php $alert = $order->delayAlert(); @endphp
                        <tr wire:key="go-{{ $order->id }}" data-href="{{ route('group.open-order', $order) }}" tabindex="0" role="link"
                            aria-label="Ouvrir la commande {{ $order->order_number }}"
                            class="border-t border-(--color-border) {{ $alert ? ($alert['tone'] === 'danger' ? 'shadow-[inset_3px_0_0_var(--color-error)]' : 'shadow-[inset_3px_0_0_var(--color-warning)]') : '' }}">
                            <td class="px-5 py-3.5 font-semibold tabular-nums">
                                {{ $order->order_number }}
                                <x-copy-button :value="$order->order_number" label="Copier le n°" class="copy-btn--reveal ml-0.5" />
                            </td>
                            <td class="px-5 py-3.5">
                                <button type="button" wire:click="$set('pressingId', '{{ $order->pressing_id }}')" class="hover:text-(--color-primary) hover:underline" data-tooltip="Voir seulement ce pressing">{{ $order->pressing->name }}</button>
                            </td>
                            <td class="px-5 py-3.5">
                                <div>{{ $order->customer->fullName() }}</div>
                                <div class="text-xs text-(--color-text-muted) tabular-nums">{{ $order->customer->phone }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <x-status-badge :status="$order->status" />
                                    @if ($alert)
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $alert['tone'] === 'danger' ? 'bg-(--color-error-tint) text-(--color-error)' : 'bg-(--color-warning-tint) text-(--color-warning-text)' }}">{{ $alert['label'] }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="font-semibold tabular-nums">{{ $fcfa($order->total_fcfa) }} F</div>
                                @if ($order->status !== \App\Enums\OrderStatus::Annulee)
                                    <x-payment-badge :order="$order" compact class="mt-0.5" />
                                @endif
                            </td>
                            <td class="px-5 py-3.5" title="{{ $order->dropped_off_at->translatedFormat('l j F Y à H:i') }}">
                                <div class="text-(--color-text-secondary) tabular-nums">{{ $order->dropped_off_at->format('d/m') }}</div>
                                <div class="text-[11px] text-(--color-text-muted)">{{ $order->dropped_off_at->diffForHumans(['short' => true]) }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('group.open-order', $order) }}" tabindex="-1" aria-hidden="true" class="row-chevron inline-block text-(--color-text-muted)">›</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="mt-5">{{ $orders->links() }}</div>
</div>
