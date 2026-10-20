{{--
    Annulation d'une commande : motif obligatoire (note si « Autre ») et,
    si le client a déjà payé, ce que devient cet argent (rendu ou conservé).
--}}
@php $paid = (int) $order->amount_paid_fcfa; @endphp
<form wire:submit="cancelOrder" class="mt-4 p-4 rounded-xl border border-[color-mix(in_srgb,var(--color-error)_35%,transparent)] bg-(--color-error-tint) animate-step">
    <p class="font-semibold text-(--color-error) mb-3">Annuler la commande {{ $order->order_number }}</p>

    <div class="text-xs font-medium text-(--color-text-secondary) mb-1.5">Motif</div>
    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Motif de l'annulation">
        @foreach ($cancellationReasons as $reason)
            <button type="button" role="radio" aria-checked="{{ $cancelReason === $reason->value ? 'true' : 'false' }}" wire:click="$set('cancelReason', '{{ $reason->value }}')" wire:key="reason-{{ $reason->value }}"
                    class="h-9 px-3.5 rounded-full border text-[13px] font-medium transition-colors {{ $cancelReason === $reason->value ? 'border-(--color-error) bg-(--color-error) text-white' : 'border-(--color-border) bg-(--color-surface) hover:border-(--color-error)' }}">
                {{ $reason->label() }}
            </button>
        @endforeach
    </div>
    @error('cancelReason') <p class="text-xs text-(--color-error) mt-1.5">{{ $message }}</p> @enderror

    <label for="cancel-note" class="block text-xs font-medium text-(--color-text-secondary) mt-3 mb-1.5">
        Note {{ $cancelReason === \App\Enums\CancellationReason::Other->value ? '(obligatoire)' : '(facultative, visible seulement par le pressing)' }}
    </label>
    <textarea id="cancel-note" wire:model="cancelNote" rows="2" maxlength="500" placeholder="Ex. le client a apporté ses pièces dans un autre pressing"
              class="w-full px-3 py-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm"></textarea>
    @error('cancelNote') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror

    @if ($paid > 0)
        <div class="text-xs font-medium text-(--color-text-secondary) mt-3 mb-1.5">Le client a déjà payé {{ number_format($paid, 0, ',', ' ') }} F. Cet argent est :</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" role="radiogroup" aria-label="Argent déjà versé">
            @foreach ([\App\Actions\Orders\CancelOrderAction::REFUND => ['Rendu au client', 'Enregistré comme une sortie de caisse'], \App\Actions\Orders\CancelOrderAction::KEEP => ['Conservé', 'Par exemple, le travail avait commencé']] as $value => [$title, $hint])
                <button type="button" role="radio" aria-checked="{{ $cancelMoney === $value ? 'true' : 'false' }}" wire:click="$set('cancelMoney', '{{ $value }}')" wire:key="money-{{ $value }}"
                        class="text-left p-3 rounded-xl border transition-colors {{ $cancelMoney === $value ? 'border-(--color-error) bg-(--color-surface) shadow-[0_0_0_1px_var(--color-error)]' : 'border-(--color-border) bg-(--color-surface) hover:border-(--color-text-muted)' }}">
                    <span class="block text-sm font-semibold">{{ $title }}</span>
                    <span class="block text-xs text-(--color-text-muted)">{{ $hint }}</span>
                </button>
            @endforeach
        </div>
        @error('cancelMoney') <p class="text-xs text-(--color-error) mt-1.5">{{ $message }}</p> @enderror

        @if ($cancelMoney === \App\Actions\Orders\CancelOrderAction::REFUND)
            <div class="text-xs font-medium text-(--color-text-secondary) mt-3 mb-1.5">Rendu en</div>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($paymentMethods as $method)
                    <button type="button" wire:key="refund-{{ $method->value }}" wire:click="$set('refundMethod', '{{ $method->value }}')"
                            class="h-8 px-3 rounded-full border text-xs font-medium transition-colors {{ $refundMethod === $method->value ? 'border-(--color-text-primary) bg-(--color-text-primary) text-(--color-surface)' : 'border-(--color-border) bg-(--color-surface) hover:border-(--color-text-muted)' }}">
                        {{ $method->label() }}
                    </button>
                @endforeach
            </div>
        @endif
    @endif

    <div class="flex flex-wrap justify-end gap-2 mt-4">
        <button type="button" wire:click="closeCancelForm" class="h-10 px-4 rounded-lg text-sm font-medium text-(--color-text-secondary) hover:underline">Garder la commande</button>
        <button type="submit" wire:loading.attr="disabled" wire:target="cancelOrder"
                class="h-10 px-5 rounded-lg bg-(--color-error) text-white text-sm font-semibold hover:opacity-90 disabled:opacity-60">
            <span wire:loading.remove wire:target="cancelOrder">Confirmer l'annulation</span>
            <span wire:loading wire:target="cancelOrder">Annulation…</span>
        </button>
    </div>
</form>
