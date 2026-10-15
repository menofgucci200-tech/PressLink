@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $th = 'px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) bg-(--color-bg)';
@endphp
<div>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Caisse du groupe</h1>
        <p class="text-sm text-(--color-text-muted)">
            Sur 30 jours : {{ $monthClosings }} clôture(s), {{ $monthWithDifference }} avec écart
            · cumul des écarts <span class="font-semibold {{ $monthDifference === 0 ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">{{ ($monthDifference > 0 ? '+' : '').$fcfa($monthDifference) }} F</span>
        </p>
    </div>

    <h2 class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Aujourd'hui</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
        @foreach ($today as $row)
            @php $p = $row['pressing']; $s = $row['summary']; $c = $row['closing']; @endphp
            <a href="{{ route('pressings.switch', ['pressing' => $p, 'vers' => '/caisse']) }}" wire:key="ct-{{ $p->id }}" class="kpi-link block bg-(--color-surface) border border-(--color-border) rounded-xl p-4">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-semibold text-sm truncate">{{ $p->name }}</span>
                    @if ($c)
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $c->difference_fcfa === 0 ? 'bg-(--color-success-tint) text-(--color-success-text)' : 'bg-(--color-warning-tint) text-(--color-warning-text)' }}">
                            {{ $c->difference_fcfa === 0 ? 'Clôturée' : 'Écart '.($c->difference_fcfa > 0 ? '+' : '').$fcfa($c->difference_fcfa).' F' }}
                        </span>
                    @else
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-(--color-bg) text-(--color-text-secondary)">Non clôturée</span>
                    @endif
                </div>
                <div class="text-2xl font-bold tabular-nums mt-2">{{ $fcfa($s['total']) }} F</div>
                <div class="text-xs text-(--color-text-muted) mt-1">
                    {{ $s['count'] }} paiement(s)@foreach ($s['by_method'] as $m) · {{ $m['label'] }} {{ $fcfa($m['total']) }} F @endforeach
                </div>
            </a>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <h2 class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mr-auto">Historique des clôtures</h2>
        <select wire:model.live="pressingId" aria-label="Pressing" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
            <option value="">Tous les pressings</option>
            @foreach ($pressings as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <label class="inline-flex items-center gap-2 text-sm select-none">
            <input type="checkbox" wire:model.live="differencesOnly" class="w-4 h-4 accent-(--color-primary)"> Avec écart seulement
        </label>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($closings->isEmpty())
            <x-empty-state icon="box" title="Aucune clôture de caisse">
                Chaque soir, le gérant compte les espèces et clôture la journée depuis le menu Caisse du pressing.
            </x-empty-state>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[820px] whitespace-nowrap text-sm">
                <thead><tr class="text-left">
                    <th class="{{ $th }}">Jour</th><th class="{{ $th }}">Pressing</th><th class="{{ $th }} text-right">Encaissé</th>
                    <th class="{{ $th }} text-right">Espèces attendues</th><th class="{{ $th }} text-right">Comptées</th><th class="{{ $th }} text-right">Écart</th><th class="{{ $th }}">Par</th>
                </tr></thead>
                <tbody>
                    @foreach ($closings as $c)
                        <tr wire:key="gc-{{ $c->id }}" class="border-t border-(--color-border)">
                            <td class="px-5 py-3">{{ $c->business_date->translatedFormat('D j M Y') }}</td>
                            <td class="px-5 py-3">{{ $c->pressing->name }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $fcfa($c->total_fcfa) }} F</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $fcfa($c->expected_cash_fcfa) }} F</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $fcfa($c->declared_cash_fcfa) }} F</td>
                            <td class="px-5 py-3 text-right tabular-nums font-semibold {{ $c->difference_fcfa === 0 ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">{{ $c->difference_fcfa === 0 ? '—' : ($c->difference_fcfa > 0 ? '+' : '').$fcfa($c->difference_fcfa).' F' }}</td>
                            <td class="px-5 py-3 text-(--color-text-secondary)">
                                {{ $c->closer?->name ?? '—' }}
                                @if ($c->note)<span class="ml-1 cursor-help" data-tooltip="{{ \Illuminate\Support\Str::limit($c->note, 60) }}">📝</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-5">{{ $closings->links() }}</div>
</div>
