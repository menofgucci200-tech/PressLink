@php
    $tones = [
        'orders' => ['bg' => 'bg-(--color-primary-tint)', 'fg' => 'text-(--color-primary)', 'icon' => 'M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z|m3.3 7 8.7 5 8.7-5|M12 22V12'],
        'payments' => ['bg' => 'bg-(--color-success-tint)', 'fg' => 'text-(--color-success-text)', 'icon' => 'M2 7h20v12H2Z|M12 10a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z'],
        'customers' => ['bg' => 'bg-[color-mix(in_srgb,#8b5cf6_14%,transparent)]', 'fg' => 'text-[#7c3aed]', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2|M9 11A4 4 0 1 0 9 3a4 4 0 0 0 0 8Z'],
        'cash' => ['bg' => 'bg-(--color-warning-tint)', 'fg' => 'text-(--color-warning-text)', 'icon' => 'M2 7h20v12H2Z|M16 13h.01|M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2'],
        'settings' => ['bg' => 'bg-(--color-bg)', 'fg' => 'text-(--color-text-secondary)', 'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z|M19 12h2M3 12h2M12 3v2M12 19v2M17 7l1.5-1.5M5.5 18.5 7 17M17 17l1.5 1.5M5.5 5.5 7 7'],
        'team' => ['bg' => 'bg-(--color-bg)', 'fg' => 'text-(--color-text-secondary)', 'icon' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2|M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z|M23 21v-2a4 4 0 0 0-3-3.87'],
    ];
    $dayLabel = fn ($date) => $date->isToday() ? 'Aujourd\'hui' : ($date->isYesterday() ? 'Hier' : ucfirst($date->translatedFormat('l j F Y')));
    $currentDay = null;
@endphp
<div class="max-w-5xl">
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Journal d'activité</h1>
            <p class="text-sm text-(--color-text-muted)">Qui a fait quoi, et quand : commandes, remises, encaissements, clients, caisse, prix, équipe et connexions.</p>
        </div>
        <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export"
                class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) disabled:opacity-60">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Exporter (Excel)
        </button>
    </div>

    {{-- Filtres --}}
    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 mb-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1.2fr_1fr_1fr_auto_auto] gap-3 items-end">
        <div class="relative sm:col-span-2 lg:col-span-1">
            <label for="activity-search" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Rechercher</label>
            <input id="activity-search" type="search" wire:model.live.debounce.300ms="search" placeholder="N° de commande, client, montant…"
                   class="w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
        </div>
        <div>
            <label for="activity-member" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Membre</label>
            <select id="activity-member" wire:model.live="userId" class="w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                <option value="">Toute l'équipe</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                @endforeach
                <option value="systeme">Clients et système</option>
            </select>
        </div>
        <div>
            <label for="activity-category" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Type</label>
            <select id="activity-category" wire:model.live="category" class="w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                <option value="">Toutes les actions</option>
                @foreach ($categories as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Période</label>
            <div class="flex items-center gap-1.5">
                <input type="date" wire:model.live="from" aria-label="Du" max="{{ today()->toDateString() }}" class="h-10 px-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                <span class="text-(--color-text-muted)">→</span>
                <input type="date" wire:model.live="to" aria-label="Au" max="{{ today()->toDateString() }}" class="h-10 px-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
            </div>
        </div>
        <label class="inline-flex items-center gap-2 h-10 text-sm select-none whitespace-nowrap">
            <input type="checkbox" wire:model.live="sensitiveOnly" class="w-4 h-4 accent-(--color-primary)"> Sensibles
        </label>
    </div>

    @if ($hasFilters)
        <div class="mb-3 flex items-center gap-3 text-sm">
            <span class="text-(--color-text-secondary)">{{ $logs->total() }} action(s) trouvée(s)</span>
            <button type="button" wire:click="resetFilters" class="font-medium text-(--color-primary) hover:underline">Effacer les filtres</button>
        </div>
    @endif

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden list-loading-target" wire:loading.delay.class="is-loading" wire:target="userId, category, from, to, search, sensitiveOnly, gotoPage, nextPage, previousPage">
        @forelse ($logs as $log)
            @php
                $tone = $tones[$log->action->category()] ?? $tones['settings'];
                $day = $log->created_at->toDateString();
            @endphp
            @if ($day !== $currentDay)
                @php $currentDay = $day; @endphp
                <div class="px-5 py-2 bg-(--color-bg) border-b border-(--color-border) text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) sticky top-0">{{ $dayLabel($log->created_at) }}</div>
            @endif
            <div wire:key="log-{{ $log->id }}" class="flex items-start gap-3 px-5 py-3 border-b border-(--color-border) last:border-b-0 loading-rows">
                <span class="w-12 flex-none pt-1.5 text-xs font-semibold tabular-nums text-(--color-text-secondary)" title="{{ $log->created_at->format('d/m/Y à H:i:s') }}">{{ $log->created_at->format('H:i') }}</span>
                <span class="relative w-8 h-8 flex-none rounded-lg flex items-center justify-center {{ $tone['bg'] }} {{ $tone['fg'] }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        @foreach (explode('|', $tone['icon']) as $path)<path d="{{ $path }}"/>@endforeach
                    </svg>
                    @if ($log->action->isSensitive())
                        <span class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full bg-(--color-error) ring-2 ring-(--color-surface)" title="Action sensible"></span>
                    @endif
                </span>
                <div class="flex-1 min-w-0">
                    <div class="text-sm">
                        <span class="font-semibold">{{ $log->action->label() }}</span>
                        <span class="text-(--color-text-muted)">·</span>
                        <span class="text-(--color-text-secondary)">{{ $log->user?->name ?? 'Client / système' }}{{ $log->authorRole() ? ' ('.$log->authorRole().')' : '' }}</span>
                    </div>
                    <div class="text-sm text-(--color-text-primary) break-words">
                        @if ($log->order)
                            <a href="{{ route('orders.show', $log->order) }}" wire:navigate class="hover:text-(--color-primary) hover:underline">{{ $log->description }}</a>
                        @else
                            {{ $log->description }}
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <x-empty-state icon="check" title="Aucune activité">{{ $hasFilters ? 'Aucune action ne correspond à ces filtres.' : 'Les actions de l\'équipe apparaîtront ici au fil de la journée.' }}</x-empty-state>
        @endforelse
    </div>
    <div class="mt-5">{{ $logs->links() }}</div>
</div>
