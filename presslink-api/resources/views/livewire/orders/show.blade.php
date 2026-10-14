<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('orders.index') }}" data-back aria-label="Retour" data-tooltip="Retour à la liste" data-tooltip-pos="bottom" class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center hover:border-(--color-primary)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
        </a>
        <div>
            <div class="font-semibold tabular-nums flex items-center gap-1">Commande {{ $order->order_number }} <x-copy-button :value="$order->order_number" label="Copier le n° de commande" /></div>
            <div class="text-xs text-(--color-text-muted)">{{ $order->customer->fullName() }}</div>
        </div>
        <div class="flex-1"></div>
        <x-status-badge :status="$order->status" />
        <a href="{{ route('orders.receipt', $order) }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path></svg>
            Reçu
        </a>
    </div>

    @if (session('order_created'))
        <div class="mb-5 flex flex-wrap items-center gap-3 p-4 rounded-xl bg-(--color-success-tint) border border-[color-mix(in_srgb,var(--color-success)_30%,transparent)] animate-step">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-success-text)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="M20 6 9 17l-5-5"></path></svg>
            <div class="flex-1 min-w-0 text-sm">
                <span class="font-semibold text-(--color-success-text)">Commande enregistrée.</span>
                <span class="text-(--color-text-secondary)">Remettez le reçu au client : il y retrouve son numéro, ses articles et ce qu'il lui reste à payer.</span>
            </div>
            <a href="{{ route('orders.receipt', ['order' => $order, 'imprimer' => 1]) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                Imprimer le reçu
            </a>
        </div>
    @endif

    @if ($errorMessage)
        <div class="mb-5 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 flex flex-col gap-6">
            @if ($order->issues->isNotEmpty())
                <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Problèmes signalés</div>
                    <div class="flex flex-col gap-3">
                        @foreach ($order->issues as $issue)
                            <div class="p-3 rounded-lg {{ $issue->status->value === 'open' ? 'bg-(--color-error-tint)' : 'bg-(--color-bg)' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold {{ $issue->status->value === 'open' ? 'text-(--color-error)' : 'text-(--color-text-secondary)' }}">
                                            {{ $issue->category->label() }}
                                        </div>
                                        @if ($issue->description)
                                            <div class="text-sm text-(--color-text-secondary) mt-1">{{ $issue->description }}</div>
                                        @endif
                                        <div class="text-xs text-(--color-text-muted) mt-1">{{ $issue->created_at->format('d/m/Y H:i') }} · {{ $issue->status->label() }}</div>
                                        @if ($issue->resolution_note)
                                            <div class="text-sm text-(--color-text-secondary) mt-2 italic">« {{ $issue->resolution_note }} »</div>
                                        @endif
                                    </div>
                                    @if ($issue->status->value === 'open' && $resolvingIssueId !== $issue->id)
                                        <button wire:click="startResolving({{ $issue->id }})"
                                                class="flex-none h-8 px-3 rounded-lg border border-(--color-border) text-xs font-medium hover:border-(--color-primary)">
                                            Marquer résolu
                                        </button>
                                    @endif
                                </div>

                                @if ($resolvingIssueId === $issue->id)
                                    <div class="mt-3 pt-3 border-t border-(--color-border)">
                                        <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">
                                            Comment ce problème a-t-il été résolu ? (ex. "Chemise retrouvée et remise au client")
                                        </label>
                                        <textarea wire:model="resolutionNote" rows="2" autofocus
                                            placeholder="Note de résolution (facultatif)"
                                            class="w-full px-3 py-2 rounded-lg border border-(--color-border) text-sm bg-(--color-surface) focus:outline-none focus:border-(--color-primary)"></textarea>
                                        <div class="flex gap-2 mt-2">
                                            <button wire:click="confirmResolve"
                                                wire:loading.attr="disabled" wire:target="confirmResolve"
                                                class="h-8 px-3.5 rounded-lg bg-(--color-primary) text-white text-xs font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                                                Confirmer la résolution
                                            </button>
                                            <button wire:click="cancelResolving"
                                                class="h-8 px-3.5 rounded-lg border border-(--color-border) text-xs font-medium text-(--color-text-secondary) hover:bg-(--color-surface)">
                                                Annuler
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Articles</div>
                @foreach ($order->items as $item)
                    <div class="flex justify-between text-sm py-2 border-b border-(--color-border) last:border-b-0">
                        <span>{{ $item->name }} × {{ $item->quantity }}</span>
                        <span class="tabular-nums font-medium">{{ number_format($item->subtotal_fcfa, 0, ',', ' ') }} F</span>
                    </div>
                @endforeach
                <div class="flex justify-between pt-3 mt-1">
                    <span class="text-sm font-semibold">Total</span>
                    <span class="font-display text-lg font-bold tabular-nums">{{ number_format($order->total_fcfa, 0, ',', ' ') }} FCFA</span>
                </div>
            </div>

            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Historique</div>
                <div class="flex flex-col">
                    @foreach ($order->statusHistory as $entry)
                        <div class="flex items-center justify-between text-sm py-2 border-b border-(--color-border) last:border-b-0">
                            <span class="flex items-center gap-2">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                {{ $entry->status->label() }}
                            </span>
                            <span class="text-(--color-text-muted) tabular-nums">{{ $entry->created_at->format('d/m H:i') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Client</div>
                <div class="text-sm font-medium mb-1">{{ $order->customer->fullName() }}</div>
                <div class="text-sm text-(--color-text-secondary) tabular-nums flex items-center gap-1">
                    <a href="tel:{{ $order->customer->phone }}" class="hover:text-(--color-primary)">{{ $order->customer->phone }}</a>
                    <x-copy-button :value="$order->customer->phone" label="Copier le téléphone" />
                </div>
            </div>

            @include('livewire.orders._payment-card')

            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Dates</div>
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-(--color-text-secondary)">Déposée le</span>
                    <span class="font-medium">{{ $order->dropped_off_at->format('d/m/Y') }}</span>
                </div>
                @if ($order->expected_at)
                    <div class="flex justify-between text-sm">
                        <span class="text-(--color-text-secondary)">Retrait prévu</span>
                        <span class="font-medium">{{ $order->expected_at->format('d/m/Y') }}</span>
                    </div>
                @endif
            </div>

            @if ($nextActions !== [])
                <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 flex flex-col gap-2">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Actions</div>
                    @php $balance = $order->balance_due_fcfa; @endphp
                    @if ($balance > 0 && in_array(\App\Enums\OrderStatus::Recuperee, $nextActions, true))
                        {{-- Remise avec un solde : encaisser et remettre en un geste --}}
                        <div class="rounded-lg bg-(--color-warning-tint) p-3 mb-1">
                            <div class="text-xs font-semibold text-(--color-warning-text)">Reste à payer au retrait</div>
                            <div class="font-display text-xl font-bold tabular-nums text-(--color-warning-text)">{{ number_format($balance, 0, ',', ' ') }} F</div>
                            <label for="settle-method" class="block text-[11px] font-medium text-(--color-text-secondary) mt-2 mb-1">Payé en</label>
                            <select id="settle-method" wire:model="paymentMethod" class="w-full h-9 px-2.5 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button wire:click="settleAndRecover"
                                data-confirm-title="Encaisser et remettre" data-confirm-label="Encaisser et remettre"
                                wire:confirm="Encaisser {{ number_format($balance, 0, ',', ' ') }} F et marquer la commande {{ $order->order_number }} comme récupérée par {{ $order->customer->fullName() }} ?"
                                wire:loading.attr="disabled" wire:target="settleAndRecover"
                                class="h-10 rounded-lg text-sm font-semibold bg-(--color-primary) text-white hover:bg-(--color-primary-600) disabled:opacity-60">
                            <span wire:loading.remove wire:target="settleAndRecover">Encaisser {{ number_format($balance, 0, ',', ' ') }} F et remettre</span>
                            <span wire:loading wire:target="settleAndRecover">Mise à jour…</span>
                        </button>
                    @endif
                    @foreach ($nextActions as $action)
                        <button wire:click="transitionTo('{{ $action->value }}')"
                                @if ($action->value === 'annulee')
                                    data-confirm-title="Annuler la commande" data-confirm-label="Annuler la commande" data-confirm-cancel="Garder la commande" data-confirm-tone="danger"
                                    wire:confirm="Annuler la commande {{ $order->order_number }} ? Cette action est définitive : la commande ne pourra plus changer de statut.{{ $order->amount_paid_fcfa > 0 ? ' Le client a déjà payé '.number_format($order->amount_paid_fcfa, 0, ',', ' ').' F : pensez à le rembourser.' : '' }}"
                                @elseif ($action->value === 'recuperee' && $balance > 0)
                                    data-confirm-title="Remettre sans encaisser" data-confirm-label="Remettre sans encaisser" data-confirm-tone="warning"
                                    wire:confirm="{{ $order->customer->fullName() }} récupère la commande {{ $order->order_number }} sans régler les {{ number_format($balance, 0, ',', ' ') }} F restants : ce montant restera dû (à encaisser plus tard dans Paiement)."
                                @elseif ($action->value === 'recuperee')
                                    data-confirm-title="Remise au client" data-confirm-label="Confirmer la remise"
                                    wire:confirm="Confirmez-vous que {{ $order->customer->fullName() }} a récupéré la commande {{ $order->order_number }} ({{ number_format($order->total_fcfa, 0, ',', ' ') }} F, déjà payée) ?"
                                @endif
                                wire:loading.attr="disabled" wire:target="transitionTo('{{ $action->value }}')"
                                class="h-10 rounded-lg text-sm font-semibold disabled:opacity-60 {{ ($action->value === 'recuperee' && $balance === 0) || $action->value === 'prete' ? 'bg-(--color-primary) text-white hover:bg-(--color-primary-600)' : 'border border-(--color-border) hover:border-(--color-primary)' }}">
                            <span wire:loading.remove wire:target="transitionTo('{{ $action->value }}')">{{ $action->value === 'recuperee' && $balance > 0 ? 'Remettre sans encaisser' : 'Marquer '.mb_strtolower($action->label()) }}</span>
                            <span wire:loading wire:target="transitionTo('{{ $action->value }}')">Mise à jour…</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
