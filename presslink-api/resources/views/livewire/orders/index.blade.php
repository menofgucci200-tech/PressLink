<div>
    <div class="flex items-start justify-between gap-6 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Commandes</h1>
            <p class="text-sm text-(--color-text-muted)">{{ is_countable($orders) ? $orders->total() : 0 }} commande(s)</p>
        </div>
        <a href="{{ route('orders.create') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Nouvelle commande
        </a>
    </div>

    <div class="flex items-center gap-3 mb-5">
        <div class="relative flex-1 max-w-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="N°, client, téléphone…"
                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-white">
        </div>
    </div>

    <div class="flex gap-2 mb-5 flex-wrap">
        <button wire:click="$set('status', '')"
                class="h-9 px-3.5 rounded-lg text-[13px] font-medium border {{ $status === '' ? 'bg-(--color-primary) text-white border-(--color-primary)' : 'border-(--color-border) text-(--color-text-secondary)' }}">
            Toutes <span class="tabular-nums opacity-80">{{ array_sum($counts) }}</span>
        </button>
        @foreach ($statuses as $s)
            <button wire:click="$set('status', '{{ $s->value }}')"
                    class="h-9 px-3.5 rounded-lg text-[13px] font-medium border {{ $status === $s->value ? 'bg-(--color-primary) text-white border-(--color-primary)' : 'border-(--color-border) text-(--color-text-secondary)' }}">
                {{ $s->label() }} <span class="tabular-nums opacity-80">{{ $counts[$s->value] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @include('livewire.orders._table', ['orders' => $orders, 'showPhone' => true])
    </div>

    @if (is_countable($orders) && method_exists($orders, 'links'))
        <div class="mt-5">{{ $orders->links() }}</div>
    @endif
</div>
