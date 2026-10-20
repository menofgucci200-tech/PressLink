{{--
    Aperçu d'une commande à droite de la liste : l'essentiel et les actions
    courantes sans quitter la liste (filtres et position conservés). Échap
    ferme, ↑ / ↓ passent à la commande précédente / suivante de la page.
--}}
@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $pageIds = $orders->pluck('id')->values();
    $position = $pageIds->search($previewOrder->id);
    $previousId = $position !== false && $position > 0 ? $pageIds[$position - 1] : null;
    $nextId = $position !== false && $position < $pageIds->count() - 1 ? $pageIds[$position + 1] : null;
    $alert = $previewOrder->delayAlert();
    $balance = $previewOrder->balance_due_fcfa;
    // L'annulation demande un motif : elle se fait depuis la fiche (lien plus bas).
    // La remise n'est possible que soldée (bouton « Encaisser et remettre » sinon).
    $nextActions = array_values(array_filter($previewOrder->status->allowedTransitions(), fn ($action) => $action !== \App\Enums\OrderStatus::Annulee
        && ! ($action === \App\Enums\OrderStatus::Recuperee && $previewOrder->balance_due_fcfa > 0)));
    $previewCanCancel = \App\Actions\Orders\CancelOrderAction::canCancel(auth()->user(), $previewOrder);
    $canRecover = in_array(\App\Enums\OrderStatus::Recuperee, $nextActions, true);
@endphp
<div wire:key="preview-{{ $previewOrder->id }}"
     x-data
     @keydown.escape.window="$wire.closePreview()"
     @keydown.window="
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName) || $event.target.isContentEditable) return;
        if ($event.key === 'ArrowDown' && {{ $nextId ?? 'null' }}) { $event.preventDefault(); $wire.preview({{ $nextId ?? 0 }}) }
        if ($event.key === 'ArrowUp' && {{ $previousId ?? 'null' }}) { $event.preventDefault(); $wire.preview({{ $previousId ?? 0 }}) }
     ">
    <div class="fixed inset-0 z-40 bg-slate-900/30 lg:hidden" wire:click="closePreview" aria-hidden="true"></div>
    <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-[420px] bg-(--color-surface) border-l border-(--color-border) shadow-2xl flex flex-col animate-panel"
           role="dialog" aria-label="Aperçu de la commande {{ $previewOrder->order_number }}">
        <div class="flex items-start gap-3 px-5 py-4 border-b border-(--color-border)">
            <div class="flex-1 min-w-0">
                <div class="text-xs text-(--color-text-muted)">Commande</div>
                <div class="font-display text-lg font-bold tabular-nums flex items-center gap-1">{{ $previewOrder->order_number }} <x-copy-button :value="$previewOrder->order_number" label="Copier le n° de commande" /></div>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <x-status-badge :status="$previewOrder->status" />
                    @if ($alert)
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $alert['tone'] === 'danger' ? 'bg-(--color-error-tint) text-(--color-error)' : 'bg-(--color-warning-tint) text-(--color-warning-text)' }}">{{ $alert['label'] }}</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" @if ($previousId) wire:click="preview({{ $previousId }})" @else disabled @endif
                        data-tooltip="Précédente (↑)" data-tooltip-pos="bottom" aria-label="Commande précédente"
                        class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary) disabled:opacity-35">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
                </button>
                <button type="button" @if ($nextId) wire:click="preview({{ $nextId }})" @else disabled @endif
                        data-tooltip="Suivante (↓)" data-tooltip-pos="bottom" aria-label="Commande suivante"
                        class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary) disabled:opacity-35">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                </button>
                <button type="button" wire:click="closePreview" data-tooltip="Fermer (Échap)" data-tooltip-pos="bottom" aria-label="Fermer l'aperçu"
                        class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-5 space-y-5 text-sm">
            @if ($previewError)
                <div class="p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $previewError }}</div>
            @endif

            {{-- Client --}}
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 flex-none rounded-full bg-(--color-border) flex items-center justify-center text-xs font-bold">
                    {{ mb_strtoupper(mb_substr($previewOrder->customer->first_name, 0, 1).mb_substr($previewOrder->customer->last_name, 0, 1)) }}
                </span>
                <div class="flex-1 min-w-0">
                    <a href="{{ route('clients.show', $previewOrder->customer) }}" wire:navigate class="font-semibold hover:text-(--color-primary)">{{ $previewOrder->customer->fullName() }}</a>
                    <div class="text-xs text-(--color-text-muted) tabular-nums flex items-center gap-0.5">{{ $previewOrder->customer->phone }} <x-copy-button :value="$previewOrder->customer->phone" label="Copier le téléphone" /></div>
                </div>
                <a href="tel:{{ $previewOrder->customer->phone }}" data-tooltip="Appeler" aria-label="Appeler {{ $previewOrder->customer->fullName() }}"
                   class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"></path></svg>
                </a>
            </div>

            {{-- Articles --}}
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Articles</div>
                <div class="rounded-lg border border-(--color-border) divide-y divide-(--color-border)">
                    @forelse ($previewOrder->items as $item)
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <span class="min-w-0 truncate">{{ $item->name }} <span class="text-(--color-text-muted)">× {{ $item->quantity }}</span></span>
                            <span class="tabular-nums font-medium">{{ $fcfa($item->subtotal_fcfa) }} F</span>
                        </div>
                    @empty
                        <div class="px-3 py-3 text-center text-(--color-text-muted)">Aucun article.</div>
                    @endforelse
                    <div class="flex justify-between px-3 py-2.5 bg-(--color-bg) font-bold">
                        <span>Total</span><span class="tabular-nums">{{ $fcfa($previewOrder->total_fcfa) }} F</span>
                    </div>
                </div>
                @if ($previewOrder->notes)
                    <p class="mt-2 text-xs text-(--color-text-secondary)"><span class="font-semibold">Note :</span> {{ $previewOrder->notes }}</p>
                @endif
            </div>

            {{-- Paiement --}}
            @if ($previewOrder->status !== \App\Enums\OrderStatus::Annulee)
                <div class="rounded-lg border border-(--color-border) p-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Paiement</span>
                        <x-payment-badge :order="$previewOrder" />
                    </div>
                    <div class="mt-2 flex justify-between text-(--color-text-secondary)"><span>Déjà payé</span><span class="tabular-nums">{{ $fcfa($previewOrder->amount_paid_fcfa) }} F</span></div>
                    <div class="mt-1 flex justify-between font-semibold"><span>Reste à payer</span><span class="tabular-nums {{ $balance > 0 ? 'text-(--color-warning-text)' : 'text-(--color-success-text)' }}">{{ $fcfa($balance) }} F</span></div>
                </div>
            @endif

            {{-- Dates --}}
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="rounded-lg bg-(--color-bg) p-3">
                    <div class="text-(--color-text-muted)">Déposée</div>
                    <div class="font-medium text-sm mt-0.5" title="{{ $previewOrder->dropped_off_at->translatedFormat('l j F Y à H:i') }}">{{ $previewOrder->dropped_off_at->format('d/m') }} · {{ $previewOrder->dropped_off_at->diffForHumans(['short' => true]) }}</div>
                </div>
                <div class="rounded-lg bg-(--color-bg) p-3">
                    <div class="text-(--color-text-muted)">Retrait prévu</div>
                    <div class="font-medium text-sm mt-0.5">{{ $previewOrder->expected_at?->translatedFormat('D j M') ?? '—' }}</div>
                </div>
            </div>

            {{-- Actions --}}
            @if ($nextActions !== [])
                <div class="flex flex-col gap-2">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Actions</div>
                    @if ($canRecover && $balance > 0)
                        <div class="flex gap-2">
                            <select wire:model="previewPaymentMethod" aria-label="Moyen de paiement du solde"
                                    class="h-10 px-2.5 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="previewSettleAndRecover"
                                    data-confirm-title="Encaisser et remettre" data-confirm-label="Encaisser et remettre"
                                    wire:confirm="Encaisser {{ $fcfa($balance) }} F et marquer la commande {{ $previewOrder->order_number }} comme récupérée ?"
                                    wire:loading.attr="disabled" wire:target="previewSettleAndRecover"
                                    class="flex-1 h-10 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                                Encaisser {{ $fcfa($balance) }} F et remettre
                            </button>
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($nextActions as $action)
                            @php
                                $isMain = $action === \App\Enums\OrderStatus::Prete || ($action === \App\Enums\OrderStatus::Recuperee && $balance === 0);
                                $label = 'Marquer '.mb_strtolower($action->label());
                            @endphp
                            <button type="button" wire:click="previewTransition('{{ $action->value }}')"
                                    @if ($action === \App\Enums\OrderStatus::Annulee)
                                        data-confirm-title="Annuler la commande" data-confirm-label="Annuler la commande" data-confirm-cancel="Garder la commande" data-confirm-tone="danger"
                                        wire:confirm="Annuler la commande {{ $previewOrder->order_number }} ? Cette action est définitive.{{ $previewOrder->amount_paid_fcfa > 0 ? ' Le client a déjà payé '.$fcfa($previewOrder->amount_paid_fcfa).' F : pensez à le rembourser.' : '' }}"
                                    @elseif ($action === \App\Enums\OrderStatus::Recuperee)
                                        data-confirm-title="Remise au client" data-confirm-label="Confirmer la remise"
                                        wire:confirm="Confirmez-vous que {{ $previewOrder->customer->fullName() }} a récupéré la commande {{ $previewOrder->order_number }} ?"
                                    @endif
                                    wire:loading.attr="disabled" wire:target="previewTransition('{{ $action->value }}')"
                                    class="h-10 px-2 rounded-lg text-[13px] font-semibold disabled:opacity-60 {{ $isMain ? 'col-span-2 bg-(--color-primary) text-white hover:bg-(--color-primary-600)' : 'border border-(--color-border) hover:border-(--color-primary)' }} {{ $action === \App\Enums\OrderStatus::Annulee ? 'text-(--color-error)' : '' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    @if ($previewCanCancel)
                        <a href="{{ route('orders.show', ['order' => $previewOrder, 'annuler' => 1]) }}" wire:navigate class="mt-2 inline-block text-[13px] font-medium text-(--color-error) hover:underline">Annuler la commande…</a>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex gap-2 px-5 py-4 border-t border-(--color-border)">
            <a href="{{ route('orders.show', $previewOrder) }}" wire:navigate
               class="flex-1 h-10 rounded-lg border border-(--color-border) text-sm font-semibold inline-flex items-center justify-center gap-2 hover:border-(--color-primary) hover:text-(--color-primary)">
                Ouvrir la fiche
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </a>
            <a href="{{ route('orders.receipt', $previewOrder) }}" target="_blank" rel="noopener"
               class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold inline-flex items-center gap-2 hover:border-(--color-primary) hover:text-(--color-primary)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path></svg>
                Reçu
            </a>
        </div>
    </aside>
</div>
