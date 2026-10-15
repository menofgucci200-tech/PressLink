<div>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Journal d'activité</h1>
        <p class="text-sm text-(--color-text-muted)">Annulations, paiements, remises sans encaissement et clôtures de caisse dans vos pressings.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <select wire:model.live="pressingId" aria-label="Pressing" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
            <option value="">Tous les pressings</option>
            @foreach ($pressings as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="action" aria-label="Action" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
            <option value="">Toutes les actions</option>
            @foreach ($actions as $a)
                <option value="{{ $a->value }}">{{ $a->label() }}</option>
            @endforeach
        </select>
        <label class="inline-flex items-center gap-2 text-sm select-none">
            <input type="checkbox" wire:model.live="sensitiveOnly" class="w-4 h-4 accent-(--color-primary)"> Actions sensibles seulement
        </label>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden list-loading-target" wire:loading.delay.class="is-loading" wire:target="pressingId, action, sensitiveOnly, gotoPage, nextPage, previousPage">
        @forelse ($logs as $log)
            <div wire:key="log-{{ $log->id }}" class="flex items-start gap-3 px-5 py-3 border-b border-(--color-border) last:border-b-0 loading-rows">
                <span class="mt-1.5 w-2 h-2 rounded-full flex-none {{ $log->action->isSensitive() ? 'bg-(--color-error)' : 'bg-(--color-border)' }}"></span>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <span class="text-sm font-semibold">{{ $log->action->label() }}</span>
                        <span class="text-xs text-(--color-text-muted)">{{ $log->pressing->name }}</span>
                    </div>
                    <div class="text-sm text-(--color-text-secondary)">
                        @if ($log->order)
                            <a href="{{ route('group.open-order', $log->order) }}" class="hover:text-(--color-primary) hover:underline">{{ $log->description }}</a>
                        @else
                            {{ $log->description }}
                        @endif
                    </div>
                </div>
                <div class="text-right text-xs text-(--color-text-muted) flex-none">
                    <div title="{{ $log->created_at->translatedFormat('l j F Y à H:i') }}">{{ $log->created_at->format('d/m H:i') }}</div>
                    <div>{{ $log->user?->name ?? 'Système' }}</div>
                </div>
            </div>
        @empty
            <x-empty-state icon="check" tone="success" title="Aucune activité pour ces filtres" />
        @endforelse
    </div>
    <div class="mt-5">{{ $logs->links() }}</div>
</div>
