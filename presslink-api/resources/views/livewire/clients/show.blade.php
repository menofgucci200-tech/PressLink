@php
    $whatsappNumber = ltrim($customer->phone, '+');
@endphp
<div class="max-w-4xl mx-auto">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 mb-4">
        <a href="{{ route('clients.index') }}" data-back aria-label="Retour" data-tooltip="Retour" data-tooltip-pos="bottom" class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center hover:border-(--color-primary)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
        </a>
        <div class="min-w-0 flex-1">
            <div class="font-semibold">
                {{ $customer->fullName() }}
                @unless ($customer->is_active)
                    <span class="ml-1.5 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-error-tint) text-(--color-error)" title="Compte désactivé par PressLink : le client ne peut plus se connecter à l'application.">Compte désactivé</span>
                @endunless
            </div>
            <div class="text-xs text-(--color-text-muted) tabular-nums whitespace-nowrap flex items-center gap-0.5">{{ \App\Support\PhoneNumber::format($customer->phone) }} <x-copy-button :value="$customer->phone" label="Copier le téléphone" /></div>
        </div>
        @if ($customer->hasAppAccount())
            <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-xs font-semibold bg-(--color-success-tint) text-(--color-success-text)"
                  data-tooltip="Le client suit ses commandes dans l'application PressLink.">
                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>Utilise l'application
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-xs font-semibold bg-(--color-bg) border border-(--color-border) text-(--color-text-secondary)"
                  data-tooltip="En installant PressLink avec ce numéro, il retrouvera toutes ses commandes.">
                <span class="w-1.5 h-1.5 rounded-full bg-current opacity-60"></span>Pas encore sur l'application
            </span>
        @endif
    </div>

    {{-- Les gestes du quotidien avec ce client, en un toucher --}}
    <div class="grid grid-cols-3 sm:flex sm:flex-wrap gap-2 mb-6">
        @if (auth()->user()->may(\App\Enums\Permission::OrdersCreate) && $customer->is_active)
            <a href="{{ route('orders.create', ['client' => $customer->id]) }}" wire:navigate
               class="min-w-0 inline-flex items-center justify-center gap-1.5 h-11 sm:h-10 px-2 sm:px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" class="flex-none"><path d="M12 5v14M5 12h14"/></svg>
                <span class="sm:hidden">Commande</span><span class="max-sm:hidden">Nouvelle commande</span>
            </a>
        @endif
        <a href="tel:{{ $customer->phone }}" class="min-w-0 inline-flex items-center justify-center gap-1.5 h-11 sm:h-10 px-2 sm:px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/></svg>
            Appeler
        </a>
        <a href="https://wa.me/{{ $whatsappNumber }}" target="_blank" rel="noopener" class="min-w-0 inline-flex items-center justify-center gap-1.5 h-11 sm:h-10 px-2 sm:px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-success) hover:text-(--color-success-text)">
            <img src="{{ asset('images/whatsapp.png') }}" alt="" width="18" height="18" class="w-[18px] h-[18px] rounded-full">
            WhatsApp
        </a>
    </div>

    <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-6">
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-3 sm:p-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px animate-step">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Commandes</div>
            <div class="font-display text-base sm:text-2xl font-bold tabular-nums">{{ $orders->count() }}</div>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-3 sm:p-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px animate-step">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Dépensé</div>
            <div class="font-display text-base sm:text-2xl font-bold tabular-nums">{{ number_format($totalSpentFcfa, 0, ',', ' ') }} F</div>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-3 sm:p-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px animate-step">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5 truncate">Client depuis</div>
            <div class="font-display text-base sm:text-2xl font-bold tabular-nums"><span class="sm:hidden">{{ $joinedAt?->format('d/m/y') ?? '—' }}</span><span class="max-sm:hidden">{{ $joinedAt?->format('d/m/Y') ?? '—' }}</span></div>
        </div>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-(--color-border) text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">
            Historique des commandes
        </div>
        @if ($orders->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucune commande pour le moment.</div>
        @else
            {{-- Téléphone : une ligne par commande, sans tableau à faire défiler --}}
            <ul class="sm:hidden divide-y divide-(--color-border)">
                @foreach ($orders as $order)
                    <li>
                        <a href="{{ route('orders.show', $order) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 active:bg-(--color-bg)">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium tabular-nums">{{ $order->order_number }}</span>
                                <span class="block text-xs text-(--color-text-muted)">{{ $order->dropped_off_at->format('d/m/Y') }}</span>
                            </span>
                            <span class="flex-none text-right">
                                <span class="block font-semibold tabular-nums">{{ number_format($order->total_fcfa, 0, ',', ' ') }} F</span>
                                <x-status-badge :status="$order->status" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="max-sm:hidden overflow-x-auto"><table class="w-full min-w-[640px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Commande</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Déposée le</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Total</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg) cursor-pointer" onclick="window.location='{{ route('orders.show', $order) }}'">
                            <td class="px-5 py-3.5 font-medium tabular-nums">{{ $order->order_number }}</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">{{ $order->dropped_off_at->format('d/m/Y') }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums font-medium">{{ number_format($order->total_fcfa, 0, ',', ' ') }} F</td>
                            <td class="px-5 py-3.5 text-right"><x-status-badge :status="$order->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
</div>
