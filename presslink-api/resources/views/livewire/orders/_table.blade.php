@if ($orders->isEmpty())
    <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">
        Aucune commande pour le moment.
    </div>
@else
    <div class="overflow-x-auto"><table class="w-full min-w-[640px] whitespace-nowrap text-sm">
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
                @php $alert = $order->delayAlert(); @endphp
                {{-- Toute la ligne ouvre la commande (cf. [data-href] dans app.js) ; le
                     badge de statut, lui, filtre la liste sur ce statut. --}}
                <tr wire:key="order-row-{{ $order->id }}" data-href="{{ route('orders.show', $order) }}" tabindex="0" role="link"
                    aria-label="Ouvrir la commande {{ $order->order_number }}"
                    class="group border-t border-(--color-border) {{ $alert ? ($alert['tone'] === 'danger' ? 'shadow-[inset_3px_0_0_var(--color-error)]' : 'shadow-[inset_3px_0_0_var(--color-warning)]') : '' }}">
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
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if ($statusFilterable ?? false)
                                <button type="button" wire:click="$set('status', '{{ $order->status->value }}')"
                                        title="Afficher seulement les commandes « {{ $order->status->label() }} »"
                                        class="rounded-full hover:ring-2 hover:ring-(--color-primary)/25 transition-shadow">
                                    <x-status-badge :status="$order->status" />
                                </button>
                            @else
                                <x-status-badge :status="$order->status" />
                            @endif
                            @if ($alert)
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $alert['tone'] === 'danger' ? 'bg-(--color-error-tint) text-(--color-error)' : 'bg-(--color-warning-tint) text-(--color-warning-text)' }}">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                                    {{ $alert['label'] }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="font-semibold tabular-nums">{{ number_format($order->total_fcfa, 0, ',', ' ') }} F</div>
                        @if ($order->status !== \App\Enums\OrderStatus::Annulee)
                            @if ($paymentFilterable ?? false)
                                <button type="button" wire:click="$set('payment', '{{ $order->payment_status }}')" title="Afficher seulement les commandes « {{ $order->payment_status_label }} »" class="mt-0.5 rounded-full hover:ring-2 hover:ring-(--color-border)">
                                    <x-payment-badge :order="$order" compact />
                                </button>
                            @else
                                <x-payment-badge :order="$order" compact class="mt-0.5" />
                            @endif
                        @endif
                    </td>
                    <td class="px-5 py-3.5" title="Déposée le {{ $order->dropped_off_at->translatedFormat('j F Y à H:i') }}">
                        <div class="text-(--color-text-secondary) tabular-nums">{{ $order->dropped_off_at->format('d/m') }}</div>
                        <div class="text-[11px] text-(--color-text-muted)">{{ $order->dropped_off_at->diffForHumans(['short' => true]) }}</div>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <a href="{{ route('orders.show', $order) }}" wire:navigate tabindex="-1" aria-hidden="true" class="row-chevron inline-block text-(--color-text-muted)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"></path></svg>
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table></div>
@endif
