@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $isToday = $selectedDay->isToday();
    $input = 'h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm';
@endphp
<div class="max-w-5xl">
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Caisse</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $pressing->name }} · {{ ucfirst($selectedDay->translatedFormat('l j F Y')) }}</p>
        </div>
        @if ($canHistory)
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
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
        <div class="flex flex-col gap-5">
            {{-- Totaux par moyen de paiement (propriétaire) --}}
            @if ($canHistory)
                <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                    <div class="flex items-baseline justify-between mb-4">
                        <h2 class="font-semibold text-sm">Encaissé ce jour</h2>
                        <span class="font-display text-2xl font-bold tabular-nums">{{ $fcfa($summary['total']) }} F</span>
                    </div>
                    @if ($summary['by_method'] === [])
                        <p class="text-sm text-(--color-text-muted)">Aucun encaissement ce jour.</p>
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
            @endif

            {{-- Dépenses de la journée : sorties d'espèces de la caisse --}}
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-(--color-border) flex items-baseline justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-sm">Dépenses de la journée</h2>
                        <p class="text-xs text-(--color-text-muted)">Argent sorti de la caisse : produits, transport, monnaie rendue…</p>
                    </div>
                    <span class="font-semibold tabular-nums {{ $summary['expenses_total'] > 0 ? 'text-(--color-error)' : 'text-(--color-text-muted)' }}">{{ $summary['expenses_total'] > 0 ? '− '.$fcfa($summary['expenses_total']).' F' : '0 F' }}</span>
                </div>
                @forelse ($summary['expenses'] as $expense)
                    <div wire:key="exp-{{ $expense->id }}" class="flex items-center gap-3 px-5 py-3 border-b border-(--color-border) text-sm">
                        <span class="w-12 text-(--color-text-muted) tabular-nums">{{ $expense->created_at->format('H:i') }}</span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-medium truncate">{{ $expense->label }}</span>
                            <span class="block text-xs text-(--color-text-muted)">{{ $expense->recorder?->name ?? '—' }}</span>
                        </span>
                        <span class="font-semibold tabular-nums">{{ $fcfa($expense->amount_fcfa) }} F</span>
                        @if ($canExpenses && ! $closing)
                            <button type="button" wire:click="removeExpense({{ $expense->id }})" aria-label="Supprimer cette dépense"
                                    data-confirm-title="Supprimer la dépense" data-confirm-label="Supprimer" data-confirm-tone="danger"
                                    wire:confirm="Supprimer la dépense « {{ $expense->label }} » de {{ $fcfa($expense->amount_fcfa) }} F ? Ce sera inscrit au journal."
                                    class="w-7 h-7 rounded-md flex items-center justify-center text-(--color-text-muted) hover:text-(--color-error) hover:bg-(--color-error-tint)">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-4 text-sm text-(--color-text-muted) border-b border-(--color-border)">Aucune dépense ce jour.</p>
                @endforelse

                @if ($canExpenses && ! $closing)
                    <form wire:submit="addExpense" class="px-5 py-4 flex flex-wrap items-start gap-2">
                        <div class="flex-1 min-w-48">
                            <input type="text" wire:model="expenseLabel" maxlength="150" placeholder="Motif (ex. achat de lessive)" aria-label="Motif de la dépense" class="{{ $input }} w-full">
                            @error('expenseLabel') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <div class="relative">
                                <input type="number" inputmode="numeric" min="1" wire:model="expenseAmount" placeholder="Montant" aria-label="Montant de la dépense" class="{{ $input }} w-36 pr-10 tabular-nums">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">F</span>
                            </div>
                            @error('expenseAmount') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="addExpense"
                                class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary) disabled:opacity-60">+ Ajouter la dépense</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-5">
            {{-- Clôture : ce qui doit être dans la caisse, les dépenses, ce qui a été compté --}}
            @if ($canClose)
                @php
                    $received = $closing ? $closing->cash_received_fcfa : $summary['cash'];
                    $expensesTotal = $closing ? $closing->expenses_fcfa : $summary['expenses_total'];
                    $expected = $closing ? $closing->expected_cash_fcfa : $summary['expected_cash'];
                @endphp
                <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                    <h2 class="font-semibold text-sm mb-3">Clôture de caisse</h2>

                    <dl class="text-sm space-y-2 mb-3">
                        <div class="flex justify-between"><dt class="text-(--color-text-secondary)">Espèces encaissées</dt><dd class="tabular-nums">{{ $fcfa($received) }} F</dd></div>
                        <div class="flex justify-between"><dt class="text-(--color-text-secondary)">Dépenses de la journée</dt><dd class="tabular-nums {{ $expensesTotal > 0 ? 'text-(--color-error)' : '' }}">{{ $expensesTotal > 0 ? '− ' : '' }}{{ $fcfa($expensesTotal) }} F</dd></div>
                        <div class="flex justify-between pt-2 border-t border-dashed border-(--color-border) font-semibold"><dt>Doit être dans la caisse</dt><dd class="tabular-nums">{{ $fcfa($expected) }} F</dd></div>
                    </dl>

                    @if ($closing)
                        @php $diff = $closing->difference_fcfa; @endphp
                        <dl class="text-sm space-y-2">
                            <div class="flex justify-between"><dt class="text-(--color-text-secondary)">Compté dans la caisse</dt><dd class="tabular-nums font-semibold">{{ $fcfa($closing->declared_cash_fcfa) }} F</dd></div>
                        </dl>
                        <div class="rounded-lg p-3 mt-3 {{ $diff === 0 ? 'bg-(--color-success-tint) text-(--color-success-text)' : 'bg-(--color-warning-tint) text-(--color-warning-text)' }}">
                            <div class="text-xs font-semibold uppercase tracking-wide">Journée clôturée</div>
                            <div class="text-lg font-bold tabular-nums">{{ $diff === 0 ? 'Aucun écart' : 'Écart '.($diff > 0 ? '+' : '').$fcfa($diff).' F' }}</div>
                        </div>
                        <p class="mt-3 text-xs text-(--color-text-muted)">Clôturée par {{ $closing->closer?->name ?? '—' }} · {{ $closing->created_at->isSameDay($selectedDay) ? $closing->created_at->format('H:i') : $closing->created_at->format('d/m H:i') }}</p>
                        @if ($closing->note)
                            <p class="mt-2 text-xs text-(--color-text-secondary)"><span class="font-semibold">Note :</span> {{ $closing->note }}</p>
                        @endif
                        {{-- Espèces encaissées après la clôture : rien ne doit disparaître du contrôle. --}}
                        @php $afterClosing = $summary['cash'] - $closing->cash_received_fcfa; @endphp
                        @if ($afterClosing !== 0)
                            <div class="mt-3 p-3 rounded-lg bg-(--color-warning-tint) text-(--color-warning-text) text-sm" data-testid="after-closing">
                                <span class="font-semibold">{{ ($afterClosing > 0 ? '+' : '').$fcfa($afterClosing) }} F en espèces enregistrés après la clôture.</span>
                                Ils ne sont pas dans le comptage ci-dessus{{ $canReopen ? ' : rouvrez la journée puis clôturez-la de nouveau.' : ' : prévenez le propriétaire.' }}
                            </div>
                        @endif
                        @if ($canReopen)
                            <button type="button" wire:click="reopen" data-confirm-title="Rouvrir la journée" data-confirm-label="Rouvrir" data-confirm-tone="warning"
                                    wire:confirm="Annuler la clôture du {{ $selectedDay->format('d/m/Y') }} ? Elle restera tracée dans le journal d'activité."
                                    class="mt-4 text-xs font-medium text-(--color-text-muted) hover:text-(--color-error) underline">Rouvrir la journée</button>
                        @endif
                    @else
                        <form wire:submit="close" class="flex flex-col gap-3">
                            <div>
                                <label for="declared-cash" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Compté dans la caisse</label>
                                <div class="relative">
                                    <input id="declared-cash" type="number" inputmode="numeric" min="0" step="25" wire:model.live.debounce.300ms="declaredCash"
                                           class="w-full h-11 pl-3 pr-12 rounded-lg border border-(--color-border) bg-(--color-surface) text-base font-semibold tabular-nums">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">FCFA</span>
                                </div>
                                @error('declaredCash') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                                @if ($declaredCash !== '' && is_numeric($declaredCash))
                                    @php $live = (int) $declaredCash - $expected; @endphp
                                    <p class="text-sm mt-1.5 font-semibold {{ $live === 0 ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">{{ $live === 0 ? 'Aucun écart' : 'Écart : '.($live > 0 ? '+' : '').$fcfa($live).' F' }}</p>
                                @endif
                            </div>
                            <div>
                                <label for="cash-note" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Note (facultatif)</label>
                                <textarea id="cash-note" wire:model="note" rows="2" placeholder="Ex. 500 F rendus en monnaie à un client" class="w-full px-3 py-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm"></textarea>
                            </div>
                            <button type="submit" wire:loading.attr="disabled" wire:target="close"
                                    class="h-11 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">Clôturer la caisse</button>
                            <p class="text-xs text-(--color-text-muted)">Le propriétaire voit les clôtures et les écarts de tous ses pressings.</p>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Historique (propriétaire) --}}
            @if ($canHistory)
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
            @endif
        </div>
    </div>
</div>
