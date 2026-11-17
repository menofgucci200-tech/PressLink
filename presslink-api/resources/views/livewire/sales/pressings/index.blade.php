@php
    $tones = [
        'info' => 'bg-(--color-info-tint) text-(--color-info)',
        'warning' => 'bg-(--color-warning-tint) text-(--color-warning-text)',
        'success' => 'bg-(--color-success-tint) text-(--color-success-text)',
        'error' => 'bg-(--color-error-tint) text-(--color-error)',
    ];
    $th = 'px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)';
@endphp
<div>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Mes pressings</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $pressings->total() }} pressing(s) apporté(s) à PressLink.</p>
        </div>
        <a href="{{ route('sales.pressings.create') }}" wire:navigate class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Inscrire un pressing</a>
    </div>

    <div class="relative max-w-sm mb-5">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Nom, commune ou téléphone…"
               class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-(--color-surface)">
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($pressings->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun pressing pour l'instant.</div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[640px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="{{ $th }}">Pressing</th>
                        <th class="{{ $th }}">Inscrit le</th>
                        <th class="{{ $th }}">Abonnement</th>
                        <th class="{{ $th }} text-right">Commandes ce mois</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pressings as $pressing)
                        @php $summary = $pressing->subscription?->summary(); @endphp
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg)">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('sales.pressings.show', $pressing) }}" wire:navigate class="font-medium text-(--color-primary) hover:underline">{{ $pressing->name }}</a>
                                <div class="text-xs text-(--color-text-muted)">{{ $pressing->city }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary) tabular-nums">{{ ($pressing->sales_agent_attached_at ?? $pressing->created_at)->format('d/m/Y') }}</td>
                            <td class="px-5 py-3.5">
                                @if ($summary)
                                    <span class="inline-flex items-center h-7 px-2.5 rounded-full text-xs font-semibold {{ $tones[$summary['tone']] }}">{{ $summary['label'] }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $pressing->orders_this_month }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="mt-5">{{ $pressings->links() }}</div>
</div>
