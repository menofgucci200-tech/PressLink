@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $isToday = $selectedDay->isToday();
@endphp
<div class="max-w-5xl">
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Caisse</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $pressing->name }} · {{ ucfirst($selectedDay->translatedFormat('l j F Y')) }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="$set('day', '{{ $selectedDay->copy()->subDay()->toDateString() }}')" aria-label="Jour précédent" data-tooltip="Jour précédent"
                    class="w-10 h-10 rounded-lg border border-(--color-border) bg-(--color-surface) flex items-center justify-center hover:border-(--color-primary)">‹</button>
            <input type="date" wire:change="$set('day', $event.target.value)" max="{{ today()->toDateString() }}" value="{{ $selectedDay->toDateString() }}" aria-label="Journée"
                   class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
            @unless ($isToday)
                <button type="button" wire:click="$set('day', '{{ $selectedDay->copy()->addDay()->toDateString() }}')" aria-label="Jour suivant" data-tooltip="Jour suivant"
                        class="w-10 h-10 rounded-lg border border-(--color-border) bg-(--color-surface) flex items-center justify-center hover:border-(--color-primary)">›</button>
                <button type="button" wire:click="$set('day', '')" class="h-10 px-3 rounded-lg text-sm font-medium text-(--color-primary) hover:underline">Aujourd'hui</button>
            @endunless
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-5 items-start">
        <div class="flex flex-col gap-5">
            {{-- Totaux par moyen de paiement --}}
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <div class="flex items-baseline justify-between mb-4">
                    <h2 class="font-semibold text-sm">Encaissé ce jour</h2>
                    <span class="font-display text-2xl font-bold tabular-nums">{{ $fcfa($summary['total']) }} F</span>
                </div>
                @if ($summary['by_method'] === [])
                    <p class="text-sm text-(--color-text-muted)">Aucun paiement enregistré ce jour.</p>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach ($summary['by_method'] as $method => $row)
                            <div class="rounded-lg border border-(--color-border) px-3.5 py-3 {{ $method === 'especes' ? 'bg-(--color-bg)' : '' }}">
                                <div class="text-xs text-(--color-text-secondary)">{{ $row['label'] }} · {{ $row['count'] }}</div>
                                <div class="text-lg font-bold tabular-nums mt-0.5">{{ $fcfa($row['total']) }} F</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Détail des paiements --}}
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-(--color-border)"><h2 class="font-semibold text-sm">Paiements ({{ $summary['count'] }})</h2></div>
                @forelse ($summary['payments'] as $payment)
                    <div wire:key="cp-{{ $payment->id }}" class="flex items-center gap-3 px-5 py-3 border-b border-(--color-border) last:border-b-0 text-sm">
                        <span class="w-12 text-(--color-text-muted) tabular-nums">{{ $payment->paid_at->format('H:i') }}</span>
                        <a href="{{ route('orders.show', $payment->order_id) }}" wire:navigate class="font-semibold hover:text-(--color-primary)">{{ $payment->order?->order_number }}</a>
                        <span class="flex-1 min-w-0 truncate text-(--color-text-secondary)">{{ $payment->order?->customer?->fullName() }}{{ $payment->recorder ? ' · par '.$payment->recorder->name : '' }}</span>
                        <span class="text-xs text-(--color-text-muted)">{{ $payment->method->label() }}</span>
                        <span class="font-semibold tabular-nums {{ $payment->isRefund() ? 'text-(--color-error)' : '' }}">{{ $payment->isRefund() ? 'Remboursement ' : '' }}{{ $fcfa($payment->amount_fcfa) }} F</span>
                    </div>
                @empty
                    <x-empty-state icon="box" title="Aucun paiement ce jour">Les paiements encaissés (au dépôt, acomptes, soldes) apparaîtront ici.</x-empty-state>
                @endforelse
            </div>
        </div>

        <div class="flex flex-col gap-5">
            {{-- Clôture --}}
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <h2 class="font-semibold text-sm mb-3">Clôture de caisse</h2>
                @if ($closing)
                    @php $diff = $closing->difference_fcfa; @endphp
                    <div class="rounded-lg p-3 mb-3 {{ $diff === 0 ? 'bg-(--color-success-tint) text-(--color-success-text)' : 'bg-(--color-warning-tint) text-(--color-warning-text)' }}">
                        <div class="text-xs font-semibold uppercase tracking-wide">Journée clôturée</div>
                        <div class="text-lg font-bold tabular-nums">{{ $diff === 0 ? 'Aucun écart' : 'Écart '.($diff > 0 ? '+' : '').$fcfa($diff).' F' }}</div>
                    </div>
                    <dl class="text-sm space-y-1.5">
                        <div class="flex justify-between"><dt class="text-(--color-text-secondary)">Espèces attendues</dt><dd class="tabular-nums">{{ $fcfa($closing->expected_cash_fcfa) }} F</dd></div>
                        <div class="flex justify-between"><dt class="text-(--color-text-secondary)">Espèces comptées</dt><dd class="tabular-nums font-semibold">{{ $fcfa($closing->declared_cash_fcfa) }} F</dd></div>
                        <div class="flex justify-between"><dt class="text-(--color-text-secondary)">Clôturée par</dt><dd>{{ $closing->closer?->name ?? '—' }} · {{ $closing->created_at->isSameDay($selectedDay) ? $closing->created_at->format('H:i') : $closing->created_at->format('d/m H:i') }}</dd></div>
                    </dl>
                    @if ($closing->note)
                        <p class="mt-3 text-xs text-(--color-text-secondary)"><span class="font-semibold">Note :</span> {{ $closing->note }}</p>
                    @endif
                    @if ($canReopen)
                        <button type="button" wire:click="reopen" data-confirm-title="Rouvrir la journée" data-confirm-label="Rouvrir" data-confirm-tone="warning"
                                wire:confirm="Annuler la clôture du {{ $selectedDay->format('d/m/Y') }} ? Elle restera tracée dans le journal d'activité."
                                class="mt-4 text-xs font-medium text-(--color-text-muted) hover:text-(--color-error) underline">Rouvrir la journée</button>
                    @endif
                @else
                    <form wire:submit="close" class="flex flex-col gap-3">
                        <div class="flex justify-between text-sm"><span class="text-(--color-text-secondary)">Espèces attendues</span><span class="font-semibold tabular-nums">{{ $fcfa($summary['cash']) }} F</span></div>
                        <div>
                            <label for="declared-cash" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Espèces comptées dans la caisse</label>
                            <div class="relative">
                                <input id="declared-cash" type="number" inputmode="numeric" min="0" step="25" wire:model.live.debounce.300ms="declaredCash"
                                       class="w-full h-10 pl-3 pr-12 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">FCFA</span>
                            </div>
                            @error('declaredCash') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                            @if ($declaredCash !== '' && is_numeric($declaredCash))
                                @php $live = (int) $declaredCash - $summary['cash']; @endphp
                                <p class="text-xs mt-1.5 font-semibold {{ $live === 0 ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">{{ $live === 0 ? 'Aucun écart' : 'Écart : '.($live > 0 ? '+' : '').$fcfa($live).' F' }}</p>
                            @endif
                        </div>
                        <div>
                            <label for="cash-note" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Note (facultatif)</label>
                            <textarea id="cash-note" wire:model="note" rows="2" placeholder="Ex. 500 F rendus en monnaie à un client" class="w-full px-3 py-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm"></textarea>
                        </div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="close"
                                class="h-10 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">Clôturer la caisse</button>
                        <p class="text-xs text-(--color-text-muted)">Le propriétaire voit les clôtures et les écarts de tous ses pressings.</p>
                    </form>
                @endif
            </div>

            {{-- Historique --}}
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-(--color-border)"><h2 class="font-semibold text-sm">Dernières clôtures</h2></div>
                @forelse ($history as $past)
                    <button type="button" wire:key="ch-{{ $past->id }}" wire:click="$set('day', '{{ $past->business_date->toDateString() }}')"
                            class="w-full flex items-center justify-between gap-3 px-5 py-2.5 border-b border-(--color-border) last:border-b-0 text-sm text-left hover:bg-(--color-bg)">
                        <span>{{ $past->business_date->translatedFormat('D j M') }}</span>
                        <span class="tabular-nums text-(--color-text-secondary)">{{ $fcfa($past->total_fcfa) }} F</span>
                        <span class="tabular-nums text-xs font-semibold {{ $past->difference_fcfa === 0 ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">{{ $past->difference_fcfa === 0 ? '✓' : ($past->difference_fcfa > 0 ? '+' : '').$fcfa($past->difference_fcfa) }}</span>
                    </button>
                @empty
                    <p class="px-5 py-6 text-sm text-(--color-text-muted)">Aucune clôture pour le moment.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
