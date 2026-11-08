{{-- Dépôt rapide : le client, le nombre de pièces, puis les photos. Le détail et les prix viendront plus tard. --}}
@php $iconCamera = 'M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z'; @endphp
<div class="max-w-xl mx-auto pb-6" x-data data-skip-back>
    @if ($deposit)
        {{-- Le dépôt existe : le numéro à remettre, puis les photos --}}
        <div class="mb-5 p-5 rounded-xl bg-(--color-success-tint) border border-[color-mix(in_srgb,var(--color-success)_30%,transparent)] animate-step" data-testid="deposit-created">
            <div class="text-sm font-semibold text-(--color-success-text)">Dépôt enregistré</div>
            <div class="mt-1 font-display text-3xl font-extrabold tabular-nums flex items-center gap-1">{{ $deposit->order_number }} <x-copy-button :value="$deposit->order_number" label="Copier le n° de dépôt" /></div>
            <div class="mt-1 text-sm text-(--color-text-secondary)">
                {{ $deposit->customer->fullName() }} · {{ $deposit->declared_pieces }} pièce{{ $deposit->declared_pieces > 1 ? 's' : '' }}{{ $deposit->expected_at ? ' · prête le '.$deposit->expected_at->format('d/m') : '' }}
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <a href="{{ route('orders.receipt', ['order' => $deposit, 'imprimer' => 1]) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 h-11 sm:h-10 px-4 rounded-lg bg-(--color-success) text-white text-sm font-semibold hover:opacity-90">Imprimer le reçu</a>
                <x-receipt-actions label="le reçu de dépôt"
                                   :download="route('orders.receipt', ['order' => $deposit, 'format' => 'pdf'])"
                                   :share="['url' => \App\Support\ReceiptLinks::order($deposit), 'text' => \App\Support\ReceiptLinks::orderShareText($deposit), 'file' => \App\Support\ReceiptLinks::orderFileName($deposit), 'phone' => $deposit->customer->phone]" />
            </div>
        </div>

        @include('livewire.orders._photos')

        <div class="mt-5 flex flex-col sm:flex-row gap-2">
            <button type="button" wire:click="nextDeposit" data-testid="next-deposit"
                    class="sm:flex-1 h-12 rounded-xl bg-(--color-primary) text-white text-[15px] font-semibold hover:bg-(--color-primary-600)">Client suivant</button>
            <a href="{{ route('orders.complete', $deposit) }}" wire:navigate
               class="sm:flex-1 h-12 rounded-xl border border-(--color-border) text-[15px] font-semibold inline-flex items-center justify-center hover:border-(--color-primary) hover:text-(--color-primary)">Compléter maintenant</a>
        </div>
    @else
        <div class="mb-5">
            <h1 class="font-display text-2xl font-bold mb-1">Dépôt rapide</h1>
            <p class="text-sm text-(--color-text-muted)">Pour les grosses commandes : le client et le nombre de pièces suffisent. Le détail et les prix se saisissent plus tard.</p>
        </div>

        @if ($errorMessage)
            <div class="mb-5 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm" role="alert">{{ $errorMessage }}</div>
        @endif

        <section class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-5 mb-4">
            @if ($selectedCustomer)
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-(--color-primary) text-white flex items-center justify-center text-sm font-semibold flex-none">
                        {{ mb_strtoupper(mb_substr($selectedCustomer->first_name, 0, 1).mb_substr($selectedCustomer->last_name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Client</div>
                        <div class="text-sm font-semibold truncate">{{ $selectedCustomer->fullName() }} <span class="font-normal text-(--color-text-secondary) tabular-nums">· {{ \App\Support\PhoneNumber::format($selectedCustomer->phone) }}</span></div>
                    </div>
                    <button type="button" wire:click="clearCustomer" class="h-10 text-[13px] font-medium text-(--color-text-secondary) hover:text-(--color-primary) hover:underline">Changer</button>
                </div>
            @else
                @include('livewire.orders._client-picker')
                @error('clientSearch') <p class="text-xs text-(--color-error) mt-2">{{ $message }}</p> @enderror
            @endif
        </section>

        <form novalidate wire:submit="save" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-5 flex flex-col gap-4"
              x-data="{ pieces: $wire.$entangle('declaredPieces'), ajuster(pas) { this.pieces = String(Math.min({{ \App\Livewire\Orders\QuickDeposit::MAX_PIECES }}, Math.max(1, (parseInt(this.pieces, 10) || 0) + pas))); } }"
              @depot-pieces.window="$nextTick(() => $refs.pieces.focus())">
            <div>
                <label for="declared-pieces" class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">
                    <span class="w-5 h-5 rounded-full bg-(--color-primary) text-white text-[11px] flex items-center justify-center">2</span> Nombre de pièces
                </label>
                <div class="flex items-center gap-2">
                    <button type="button" @click="ajuster(-1)" aria-label="Une pièce de moins" class="w-14 h-14 flex-none rounded-xl border border-(--color-border) text-2xl font-semibold hover:border-(--color-primary)">−</button>
                    <input id="declared-pieces" x-ref="pieces" x-model="pieces" type="number" inputmode="numeric" min="1" max="{{ \App\Livewire\Orders\QuickDeposit::MAX_PIECES }}" placeholder="0" data-testid="declared-pieces"
                           class="min-w-0 flex-1 h-14 px-3 rounded-xl border-2 border-(--color-border) bg-(--color-surface) text-center font-display text-2xl font-bold tabular-nums focus:outline-none focus:border-(--color-primary)">
                    <button type="button" @click="ajuster(1)" aria-label="Une pièce de plus" class="w-14 h-14 flex-none rounded-xl border border-(--color-border) text-2xl font-semibold hover:border-(--color-primary)">+</button>
                </div>
                @error('declaredPieces') <p class="text-xs text-(--color-error) mt-1.5">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="deposit-expected" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Prête le</label>
                    <input id="deposit-expected" type="date" wire:model="expectedAt" min="{{ now()->format('Y-m-d') }}" class="w-full h-11 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                    @error('expectedAt') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="deposit-notes" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Remarque (facultatif)</label>
                    <input id="deposit-notes" type="text" wire:model="notes" maxlength="1000" placeholder="Ex. 2 sacs bleus" class="w-full h-11 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                    @error('notes') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit" @disabled(! $acceptsCustomers) wire:loading.attr="disabled" wire:target="save" data-testid="save-deposit"
                    class="h-12 rounded-xl bg-(--color-primary) text-white text-[15px] font-semibold inline-flex items-center justify-center gap-2 hover:bg-(--color-primary-600) disabled:opacity-60">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $iconCamera }}"/><circle cx="12" cy="13" r="3.5"/></svg>
                <span wire:loading.remove wire:target="save">{{ $acceptsCustomers ? 'Enregistrer, puis les photos' : 'Pressing fermé' }}</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-(--color-text-muted)">Une petite commande ? <a href="{{ route('orders.create') }}" wire:navigate class="font-semibold text-(--color-primary) hover:underline">Saisir le détail tout de suite</a></p>
    @endif
</div>
