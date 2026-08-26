<div>
    <div class="flex items-start justify-between gap-6 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Vue globale</h1>
            <p class="text-sm text-(--color-text-muted)">{{ now()->translatedFormat('l j F Y') }}</p>
        </div>
        <a href="{{ route('admin.pressings.create') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Nouveau pressing
        </a>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-6">
        <select wire:model.live="period" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
            <option value="today">Aujourd'hui</option>
            <option value="7">7 derniers jours</option>
            <option value="30">30 derniers jours</option>
            <option value="month">Ce mois-ci</option>
            <option value="all">Toute la période</option>
        </select>
        <select wire:model.live="city" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
            <option value="">Toutes les villes</option>
            @foreach ($cities as $c)
                <option value="{{ $c }}">{{ $c }}</option>
            @endforeach
        </select>
        <select wire:model.live="administratorId" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
            <option value="">Tous les administrateurs</option>
            @foreach ($administrators as $admin)
                <option value="{{ $admin->id }}">{{ $admin->name }}</option>
            @endforeach
        </select>
        @if ($city !== '' || $administratorId !== '' || $period !== '30')
            <button wire:click="$set('period', '30'); $set('city', ''); $set('administratorId', '')"
                    class="text-xs font-medium text-(--color-text-muted) hover:text-(--color-primary) underline">
                Réinitialiser les filtres
            </button>
        @endif
    </div>

    <div class="grid grid-cols-4 gap-4 mb-7">
        @php
            $kpis = [
                ['label' => 'Pressings actifs', 'value' => $activePressings, 'bg' => 'var(--color-success-tint)', 'fg' => 'var(--color-success-text)', 'icon' => 'M20 6 9 17l-5-5'],
                ['label' => 'Pressings suspendus', 'value' => $suspendedPressings, 'bg' => 'var(--color-warning-tint)', 'fg' => 'var(--color-warning-text)', 'icon' => 'M12 9v4|M12 17h.01|M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z'],
                ['label' => 'Clients (plateforme)', 'value' => $totalClients, 'bg' => 'var(--color-info-tint)', 'fg' => 'var(--color-info)', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2|M9 11A4 4 0 1 0 9 3a4 4 0 0 0 0 8Z'],
                ['label' => 'Commandes (période)', 'value' => $totalOrders, 'bg' => 'var(--color-primary-tint)', 'fg' => 'var(--color-primary)', 'icon' => 'M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z'],
            ];
        @endphp
        @foreach ($kpis as $kpi)
            <div class="rounded-xl p-5" style="background:{{ $kpi['bg'] }}">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-4 bg-white/60">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="{{ $kpi['fg'] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        @foreach (explode('|', $kpi['icon']) as $path)
                            <path d="{{ $path }}"></path>
                        @endforeach
                    </svg>
                </div>
                <div class="text-[13px] font-medium mb-1" style="color:{{ $kpi['fg'] }}">{{ $kpi['label'] }}</div>
                <div class="text-3xl font-bold tabular-nums" style="color:{{ $kpi['fg'] }}">{{ $kpi['value'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-(--color-border)">
            <h2 class="font-semibold text-sm">Pressings récents</h2>
            <a href="{{ route('admin.pressings.index') }}" class="text-sm font-medium text-(--color-primary)">Voir tout →</a>
        </div>
        @if ($recentPressings->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun pressing pour ces filtres.</div>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($recentPressings as $pressing)
                        <tr class="border-t border-(--color-border) first:border-t-0 hover:bg-(--color-bg) cursor-pointer" onclick="window.location='{{ route('admin.pressings.show', $pressing) }}'">
                            <td class="px-5 py-3.5 font-medium">{{ $pressing->name }}</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">{{ $pressing->city }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-xs font-medium {{ $pressing->status->value === 'active' ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">
                                    {{ $pressing->status->label() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
