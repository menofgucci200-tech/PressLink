@php
    $th = 'px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)';
    $field = 'h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $openStatuses = \App\Livewire\Admin\Orders\Index::OPEN_STATUSES;
@endphp

<div>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Commandes</h1>
            <p class="text-sm text-(--color-text-muted)">
                {{ $fcfa($orders->total()) }} commande(s){{ $hasFilters ? ' pour ces filtres' : ' sur la plateforme' }}
                · <span class="font-semibold text-(--color-text-secondary)">{{ $fcfa($volume) }} F</span> traités (hors annulées)
            </p>
        </div>
        <a href="{{ route('admin.export', ['type' => 'orders', ...array_filter($this->filters())]) }}"
           class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Exporter
        </a>
    </div>

    {{-- Compteurs par statut (cliquables) --}}
    <x-status-filter-chips :current="$status" :counts="$statusCounts" :statuses="$statuses" class="mb-5" />

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <div class="relative w-full sm:w-auto sm:flex-1 sm:max-w-xs">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="N° commande, client, téléphone…"
                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
        </div>
        <select wire:model.live="pressingId" class="{{ $field }} max-w-[220px]" aria-label="Pressing">
            <option value="">Tous les pressings</option>
            @foreach ($pressings as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <div class="flex items-center gap-2">
            <input type="date" wire:model.live="dateFrom" class="{{ $field }}" aria-label="Du">
            <span class="text-xs text-(--color-text-muted)">au</span>
            <input type="date" wire:model.live="dateTo" class="{{ $field }}" aria-label="Au">
        </div>
        <label class="inline-flex items-center gap-2 text-sm cursor-pointer select-none">
            <input type="checkbox" wire:model.live="withOpenIssues" class="w-4 h-4 accent-(--color-primary)"> Signalement ouvert
        </label>
        <label class="inline-flex items-center gap-2 text-sm cursor-pointer select-none">
            <input type="checkbox" wire:model.live="lateOnly" class="w-4 h-4 accent-(--color-primary)"> En retard
        </label>
        @if ($hasFilters)
            <button wire:click="resetFilters" class="text-xs font-medium text-(--color-text-muted) hover:text-(--color-primary) underline">Réinitialiser</button>
        @endif
        <span wire:loading.delay class="text-xs text-(--color-text-muted)">Chargement…</span>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($orders->isEmpty())
            @if ($hasFilters)
                <x-empty-state icon="search" title="Aucune commande trouvée pour ces filtres">
                    Modifiez la recherche ou retirez un filtre pour élargir la liste.
                    <x-slot:actions>
                        <button type="button" wire:click="resetFilters" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">Réinitialiser les filtres</button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <x-empty-state icon="box" title="Aucune commandee pour le moment" />
            @endif
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[920px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="{{ $th }}">N°</th>
                        <th class="{{ $th }}">Pressing</th>
                        <th class="{{ $th }}">Client</th>
                        <th class="{{ $th }}">Statut</th>
                        <th class="{{ $th }} text-right">Articles</th>
                        <th class="{{ $th }} text-right">Total</th>
                        <th class="{{ $th }}">Dépôt</th>
                        <th class="{{ $th }}">Prévue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        @php $isLate = in_array($order->status, $openStatuses, true) && $order->expected_at?->isPast(); @endphp
                        <tr wire:key="order-{{ $order->id }}" wire:click="showOrder({{ $order->id }})"
                            class="border-t border-(--color-border) cursor-pointer {{ $selectedOrderId === $order->id ? 'bg-(--color-primary-tint)' : 'hover:bg-(--color-bg)' }}">
                            <td class="px-5 py-3.5 font-semibold tabular-nums">
                                {{ $order->order_number }}
                                @if ($order->open_issues_count > 0)
                                    <span class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-(--color-error-tint) text-(--color-error) text-[11px] font-bold align-middle" title="Signalement ouvert">!</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <button type="button" wire:click.stop="$set('pressingId', '{{ $order->pressing_id }}')" title="Voir uniquement les commandes de {{ $order->pressing->name }}"
                                        class="hover:text-(--color-primary) hover:underline">{{ $order->pressing->name }}</button>
                            </td>
                            <td class="px-5 py-3.5">
                                <div>{{ $order->customer->fullName() }}</div>
                                <div class="text-xs text-(--color-text-muted) tabular-nums">{{ $order->customer->phone }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <button type="button" wire:click.stop="$set('status', '{{ $order->status->value }}')" title="Filtrer : {{ $order->status->label() }}" class="rounded-full hover:ring-2 hover:ring-(--color-border)">
                                    <x-status-badge :status="$order->status" />
                                </button>
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $order->items_count }}</td>
                            <td class="px-5 py-3.5 text-right font-semibold tabular-nums">{{ $fcfa($order->total_fcfa) }} F</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)" title="{{ $order->dropped_off_at?->translatedFormat('l j F Y à H:i') }}">
                                {{ $order->dropped_off_at?->format('d/m/Y') ?? '—' }}
                                @if ($order->dropped_off_at)
                                    <div class="text-xs text-(--color-text-muted)">{{ $order->dropped_off_at->diffForHumans(['short' => true]) }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 {{ $isLate ? 'text-(--color-error) font-semibold' : 'text-(--color-text-secondary)' }}">
                                {{ $order->expected_at?->format('d/m/Y') ?? '—' }}{{ $isLate ? ' · en retard' : '' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="mt-5">{{ $orders->links() }}</div>

    {{-- ===== Panneau de détail ===== --}}
    @if ($selectedOrder)
        <div wire:key="order-panel-{{ $selectedOrder->id }}" x-data @keydown.escape.window="$wire.closeOrder()">
            <div class="fixed inset-0 z-40 bg-slate-900/40" wire:click="closeOrder" aria-hidden="true"></div>
            <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-(--color-surface) shadow-2xl flex flex-col animate-step" role="dialog" aria-label="Détail de la commande">
                <div class="flex items-start gap-3 px-5 py-4 border-b border-(--color-border)">
                    <div class="flex-1 min-w-0">
                        <div class="text-xs text-(--color-text-muted)">Commande</div>
                        <div class="font-display text-lg font-bold tabular-nums flex items-center gap-1">{{ $selectedOrder->order_number }} <x-copy-button :value="$selectedOrder->order_number" label="Copier le n° de commande" /></div>
                    </div>
                    <x-status-badge :status="$selectedOrder->status" />
                    <button type="button" wire:click="closeOrder" aria-label="Fermer"
                            class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-5 space-y-6 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-(--color-bg) p-3">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Pressing</div>
                            <a href="{{ route('admin.pressings.show', $selectedOrder->pressing) }}" wire:navigate class="font-medium text-(--color-primary) hover:underline">{{ $selectedOrder->pressing->name }}</a>
                            <div class="text-xs text-(--color-text-muted) tabular-nums">{{ $selectedOrder->pressing->phone }}</div>
                        </div>
                        <div class="rounded-lg bg-(--color-bg) p-3">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Client</div>
                            <a href="{{ route('admin.clients.index', ['client' => $selectedOrder->customer_id]) }}" wire:navigate class="font-medium text-(--color-primary) hover:underline">{{ $selectedOrder->customer->fullName() }}</a>
                            <div class="text-xs text-(--color-text-muted) tabular-nums flex items-center gap-0.5">{{ $selectedOrder->customer->phone }} <x-copy-button :value="$selectedOrder->customer->phone" label="Copier le téléphone" /></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 text-xs">
                        <div><div class="text-(--color-text-muted)">Créée le</div><div class="font-medium text-sm">{{ $selectedOrder->created_at->format('d/m/Y H:i') }}</div></div>
                        <div><div class="text-(--color-text-muted)">Prévue le</div><div class="font-medium text-sm">{{ $selectedOrder->expected_at?->format('d/m/Y') ?? '—' }}</div></div>
                        <div><div class="text-(--color-text-muted)">Récupérée le</div><div class="font-medium text-sm">{{ $selectedOrder->recovered_at?->format('d/m/Y') ?? '—' }}</div></div>
                    </div>

                    <div>
                        <h3 class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Articles</h3>
                        <div class="rounded-lg border border-(--color-border) divide-y divide-(--color-border)">
                            @forelse ($selectedOrder->items as $item)
                                <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                                    <div class="min-w-0">
                                        <div class="font-medium truncate">{{ $item->name }}</div>
                                        <div class="text-xs text-(--color-text-muted)">{{ $item->quantity }} × {{ $fcfa($item->unit_price_fcfa) }} F{{ $item->color ? ' · '.$item->color : '' }}</div>
                                    </div>
                                    <div class="font-semibold tabular-nums">{{ $fcfa($item->subtotal_fcfa) }} F</div>
                                </div>
                            @empty
                                <div class="px-3 py-4 text-center text-(--color-text-muted)">Aucun article.</div>
                            @endforelse
                            <div class="flex justify-between px-3 py-2.5 bg-(--color-bg) font-bold">
                                <span>Total</span><span class="tabular-nums">{{ $fcfa($selectedOrder->total_fcfa) }} F</span>
                            </div>
                        </div>
                        @if ($selectedOrder->notes)
                            <p class="mt-2 text-xs text-(--color-text-secondary)"><span class="font-semibold">Note :</span> {{ $selectedOrder->notes }}</p>
                        @endif
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Paiement</h3>
                            <x-payment-badge :order="$selectedOrder" />
                        </div>
                        <div class="rounded-lg border border-(--color-border) divide-y divide-(--color-border)">
                            @foreach ($selectedOrder->payments as $payment)
                                <div class="flex items-center justify-between gap-3 px-3 py-2">
                                    <div class="text-xs text-(--color-text-secondary)">{{ $payment->paid_at->format('d/m/Y H:i') }} · {{ $payment->method->label() }}</div>
                                    <div class="font-medium tabular-nums">{{ $fcfa($payment->amount_fcfa) }} F</div>
                                </div>
                            @endforeach
                            <div class="flex justify-between px-3 py-2.5 bg-(--color-bg) font-bold">
                                <span>Reste à payer</span><span class="tabular-nums">{{ $fcfa($selectedOrder->balance_due_fcfa) }} F</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Historique</h3>
                        <ol class="relative border-l border-(--color-border) ml-1.5 space-y-3">
                            @forelse ($selectedOrder->statusHistory as $step)
                                <li class="pl-4 relative">
                                    <span class="absolute -left-[5px] top-1.5 w-2.5 h-2.5 rounded-full bg-(--color-primary)"></span>
                                    <div class="font-medium">{{ $step->status->label() }}</div>
                                    <div class="text-xs text-(--color-text-muted)">{{ $step->created_at->format('d/m/Y H:i') }}{{ $step->changedBy ? ' · '.$step->changedBy->name : '' }}</div>
                                </li>
                            @empty
                                <li class="pl-4 text-(--color-text-muted)">Aucun changement de statut.</li>
                            @endforelse
                        </ol>
                    </div>

                    @if ($selectedOrder->issues->isNotEmpty())
                        <div>
                            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Signalements</h3>
                            <div class="space-y-2">
                                @foreach ($selectedOrder->issues as $issue)
                                    <div class="rounded-lg border p-3 {{ $issue->status === \App\Enums\OrderIssueStatus::Open ? 'border-(--color-error)/30 bg-(--color-error-tint)' : 'border-(--color-border)' }}">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <span class="font-semibold">{{ $issue->category->label() }}</span>
                                            <span class="text-xs font-medium {{ $issue->status === \App\Enums\OrderIssueStatus::Open ? 'text-(--color-error)' : 'text-(--color-success-text)' }}">{{ $issue->status->label() }}</span>
                                        </div>
                                        <p class="text-(--color-text-secondary)">{{ $issue->description }}</p>
                                        <div class="text-xs text-(--color-text-muted) mt-1">Signalé le {{ $issue->created_at->format('d/m/Y H:i') }}</div>
                                        @if ($issue->resolution_note)
                                            <p class="text-xs mt-1.5"><span class="font-semibold">Réponse :</span> {{ $issue->resolution_note }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($selectedOrder->creator)
                        <p class="text-xs text-(--color-text-muted)">Créée par {{ $selectedOrder->creator->name }}.</p>
                    @endif
                </div>
            </aside>
        </div>
    @endif
</div>
