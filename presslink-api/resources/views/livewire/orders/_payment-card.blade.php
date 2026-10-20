{{-- Paiement de la commande : total, payé, reste à payer, paiements et encaissement. --}}
@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $balance = $order->balance_due_fcfa;
    $paidPct = $order->total_fcfa > 0 ? min(100, round($order->amount_paid_fcfa / $order->total_fcfa * 100)) : 100;
    $isCancelled = $order->status === \App\Enums\OrderStatus::Annulee;
@endphp
<div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5" id="paiement">
    <div class="flex items-center justify-between gap-2 mb-3">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Paiement</div>
        <x-payment-badge :order="$order" />
    </div>

    <div class="flex flex-col gap-1.5 text-sm">
        <div class="flex justify-between"><span class="text-(--color-text-secondary)">Total</span><span class="tabular-nums font-medium">{{ $fcfa($order->total_fcfa) }} F</span></div>
        <div class="flex justify-between"><span class="text-(--color-text-secondary)">Déjà payé</span><span class="tabular-nums font-medium text-(--color-success-text)">{{ $fcfa($order->amount_paid_fcfa) }} F</span></div>
    </div>
    <div class="h-1.5 rounded-full bg-(--color-border) overflow-hidden my-3">
        <div class="h-full rounded-full bg-(--color-success) transition-all duration-500" style="width:{{ $paidPct }}%"></div>
    </div>
    <div class="flex justify-between items-baseline">
        <span class="text-sm font-semibold">Reste à payer</span>
        @if ($isCancelled)
            <span class="text-sm font-semibold text-(--color-text-muted)">Rien (commande annulée)</span>
        @else
            <span class="font-display text-lg font-bold tabular-nums {{ $balance > 0 ? 'text-(--color-warning-text)' : 'text-(--color-success-text)' }}">{{ $balance > 0 ? $fcfa($balance).' F' : 'Soldée' }}</span>
        @endif
    </div>

    @if ($order->payments->isNotEmpty())
        <div class="mt-4 pt-3 border-t border-(--color-border) flex flex-col gap-2">
            @foreach ($order->payments as $payment)
                <div wire:key="payment-{{ $payment->id }}" class="flex items-start gap-2 text-[13px]">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium {{ $payment->isRefund() ? 'text-(--color-error)' : '' }}">{{ $payment->isRefund() ? 'Remboursement · ' : '' }}{{ $fcfa($payment->amount_fcfa) }} F · {{ $payment->method->label() }}</div>
                        <div class="text-xs text-(--color-text-muted)">
                            {{ $payment->paid_at->format('d/m/Y H:i') }}{{ $payment->recorder ? ' · '.$payment->recorder->name : '' }}{{ $payment->note ? ' · '.$payment->note : '' }}
                        </div>
                    </div>
                    @if ($canRemovePayments)
                        <button type="button" wire:click="removePayment({{ $payment->id }})"
                                data-confirm-title="Supprimer ce paiement" data-confirm-label="Supprimer" data-confirm-tone="danger"
                                wire:confirm="Supprimer le paiement de {{ $fcfa($payment->amount_fcfa) }} F du {{ $payment->paid_at->format('d/m/Y') }} ? À utiliser uniquement pour corriger une erreur de saisie."
                                data-tooltip="Supprimer (erreur de saisie)" aria-label="Supprimer ce paiement"
                                class="flex-none w-7 h-7 rounded-md flex items-center justify-center text-(--color-text-muted) hover:text-(--color-error) hover:bg-(--color-error-tint)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($balance > 0 && ! $isCancelled)
        @if ($showPaymentForm)
            <form wire:submit="recordPayment" class="mt-4 pt-4 border-t border-(--color-border) flex flex-col gap-3 animate-step">
                <div>
                    <label for="payment-amount" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Montant encaissé</label>
                    <div class="relative">
                        <input id="payment-amount" type="number" inputmode="numeric" min="1" max="{{ $balance }}" step="50" wire:model="paymentAmount" autofocus
                               class="w-full h-10 pl-3 pr-12 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">FCFA</span>
                    </div>
                    @error('paymentAmount') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="payment-method" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Moyen de paiement</label>
                    <select id="payment-method" wire:model="paymentMethod" class="w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="recordPayment"
                            class="flex-1 h-10 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                        <span wire:loading.remove wire:target="recordPayment">Enregistrer</span>
                        <span wire:loading wire:target="recordPayment">Enregistrement…</span>
                    </button>
                    <button type="button" wire:click="cancelPayment" class="h-10 px-3.5 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-text-muted)">Annuler</button>
                </div>
            </form>
        @else
            <button type="button" wire:click="openPaymentForm"
                    class="mt-4 w-full h-10 rounded-lg border border-(--color-border) text-sm font-semibold inline-flex items-center justify-center gap-2 hover:border-(--color-primary) hover:text-(--color-primary)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="2.5"></circle></svg>
                Encaisser un paiement
            </button>
        @endif
    @endif

    @if ($isCancelled && $order->amount_paid_fcfa > 0)
        @if ($order->cancellation_reason)
            <p class="mt-3 text-xs text-(--color-text-secondary)">Commande annulée : les {{ $fcfa($order->amount_paid_fcfa) }} F versés ont été conservés par le pressing.</p>
        @else
            <p class="mt-3 text-xs text-(--color-error)">Commande annulée : {{ $fcfa($order->amount_paid_fcfa) }} F avaient été payés, à rembourser au client.</p>
        @endif
    @endif
</div>
