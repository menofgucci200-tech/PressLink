<div>
    <div class="flex items-start justify-between gap-6 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Pressings</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $pressings->total() }} pressing(s) sur la plateforme.</p>
        </div>
        <a href="{{ route('admin.pressings.create') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Nouveau pressing
        </a>
    </div>

    <div class="relative max-w-sm mb-5">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Nom, code ou ville…"
               class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-white">
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($pressings->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun pressing trouvé.</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Pressing</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Ville</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Équipe</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Clients</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Commandes</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Statut</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pressings as $pressing)
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg)">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('admin.pressings.show', $pressing) }}" class="font-medium hover:text-(--color-primary)">{{ $pressing->name }}</a>
                                <div class="text-xs text-(--color-text-muted) font-mono">{{ $pressing->code }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">{{ $pressing->city }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $pressing->staff_count }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $pressing->customers_count }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $pressing->orders_count }}</td>
                            <td class="px-5 py-3.5">
                                <span class="text-xs font-medium {{ $pressing->status->value === 'active' ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">
                                    {{ $pressing->status->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button wire:click="toggleStatus({{ $pressing->id }})"
                                        wire:loading.attr="disabled" wire:target="toggleStatus({{ $pressing->id }})"
                                        class="text-xs font-medium text-(--color-primary) disabled:opacity-60">
                                    {{ $pressing->status->value === 'active' ? 'Suspendre' : 'Réactiver' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if (method_exists($pressings, 'links'))
        <div class="mt-5">{{ $pressings->links() }}</div>
    @endif
</div>
