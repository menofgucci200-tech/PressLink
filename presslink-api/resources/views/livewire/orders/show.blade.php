<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('orders.index') }}" class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center hover:border-(--color-primary)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
        </a>
        <div>
            <div class="font-semibold tabular-nums">Commande {{ $order->order_number }}</div>
            <div class="text-xs text-(--color-text-muted)">{{ $order->customer->fullName() }}</div>
        </div>
        <div class="flex-1"></div>
        <x-status-badge :status="$order->status" />
    </div>

    @if ($errorMessage)
        <div class="mb-5 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-3 gap-6">
        <div class="col-span-2 flex flex-col gap-6">
            @if ($order->issues->isNotEmpty())
                <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Problèmes signalés</div>
                    <div class="flex flex-col gap-3">
                        @foreach ($order->issues as $issue)
                            <div class="flex items-start justify-between gap-3 p-3 rounded-lg {{ $issue->status->value === 'open' ? 'bg-(--color-error-tint)' : 'bg-(--color-bg)' }}">
                                <div>
                                    <div class="text-sm font-semibold {{ $issue->status->value === 'open' ? 'text-(--color-error)' : 'text-(--color-text-secondary)' }}">
                                        {{ $issue->category->label() }}
                                    </div>
                                    @if ($issue->description)
                                        <div class="text-sm text-(--color-text-secondary) mt-1">{{ $issue->description }}</div>
                                    @endif
                                    <div class="text-xs text-(--color-text-muted) mt-1">{{ $issue->created_at->format('d/m/Y H:i') }} · {{ $issue->status->label() }}</div>
                                </div>
                                @if ($issue->status->value === 'open')
                                    <button wire:click="resolveIssue({{ $issue->id }})" class="flex-none h-8 px-3 rounded-lg border border-(--color-border) text-xs font-medium hover:border-(--color-primary)">
                                        Marquer résolu
                                    </button>
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
                <div class="text-sm text-(--color-text-secondary) tabular-nums">{{ $order->customer->phone }}</div>
            </div>

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
                    @foreach ($nextActions as $action)
                        <button wire:click="transitionTo('{{ $action->value }}')"
                                class="h-10 rounded-lg text-sm font-semibold {{ $action->value === 'recuperee' || $action->value === 'prete' ? 'bg-(--color-primary) text-white hover:bg-(--color-primary-600)' : 'border border-(--color-border) hover:border-(--color-primary)' }}">
                            Marquer {{ mb_strtolower($action->label()) }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
