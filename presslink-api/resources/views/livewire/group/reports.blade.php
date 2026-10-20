@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $btn = 'inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)';
@endphp
<div class="max-w-5xl">
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Rapports du groupe</h1>
            <p class="text-sm text-(--color-text-muted)">Bilan mensuel de vos {{ $rows->count() }} pressings, et exports pour votre comptable.</p>
        </div>
        <select wire:model.live="month" aria-label="Mois" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
            @foreach ($months as $m)
                <option value="{{ $m->format('Y-m') }}" @selected($m->format('Y-m') === $monthKey)>{{ ucfirst($m->translatedFormat('F Y')) }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6 loading-dim rounded-xl" wire:loading.delay.class="is-loading" wire:target="month">
        @foreach ([['Encaissé', $totals['revenue'], 'F'], ['Commandes déposées', $totals['orders'], null], ['Panier moyen', $totals['average_basket'], 'F'], ['Reste à encaisser', $totals['outstanding'], 'F']] as [$label, $value, $unit])
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4">
                <div class="text-[13px] font-medium text-(--color-text-secondary)">{{ $label }}</div>
                <div class="text-2xl font-bold tabular-nums mt-2">{{ $fcfa($value) }}@if ($unit) <span class="text-sm text-(--color-text-muted)">{{ $unit }}</span>@endif</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 flex flex-col gap-3">
            <h2 class="font-semibold text-sm">Rapport PDF</h2>
            <p class="text-xs text-(--color-text-muted)">Chiffres clés, comparatif des pressings, encaissé par semaine et points d'attention.</p>
            <a href="{{ route('group.report', ['mois' => $monthKey]) }}" class="{{ $btn }} mt-auto bg-(--color-primary)! text-white! border-transparent!">Télécharger le PDF</a>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 flex flex-col gap-3">
            <h2 class="font-semibold text-sm">Pour le comptable</h2>
            <p class="text-xs text-(--color-text-muted)">Tous les paiements du mois (date, pressing, moyen, montant) et toutes les commandes déposées.</p>
            <div class="mt-auto flex flex-col gap-2">
                @foreach (['paiements' => ['Paiements', ['mois' => $monthKey]], 'commandes' => ['Commandes', ['du' => $start->toDateString(), 'au' => $end->toDateString()]]] as $type => [$label, $params])
                    <div class="flex items-center gap-2">
                        <span class="flex-1 text-sm font-medium">{{ $label }}</span>
                        <a href="{{ route('group.export', ['type' => $type, 'format' => 'xlsx', ...$params]) }}" class="{{ $btn }}">Excel</a>
                        <a href="{{ route('group.export', ['type' => $type, 'format' => 'csv', ...$params]) }}" class="{{ $btn }}">CSV</a>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 flex flex-col gap-3">
            <h2 class="font-semibold text-sm">Envoi automatique</h2>
            <p class="text-xs text-(--color-text-muted)">Recevez le rapport PDF du mois écoulé le 1er de chaque mois{{ $user->email ? ', à '.$user->email : '' }}.</p>
            <button type="button" wire:click="toggleMonthlyEmail" role="switch" aria-checked="{{ $user->group_report_opt_in ? 'true' : 'false' }}"
                    class="mt-auto inline-flex items-center justify-between gap-3 h-10 px-4 rounded-lg border text-sm font-semibold {{ $user->group_report_opt_in ? 'border-(--color-success) bg-(--color-success-tint) text-(--color-success-text)' : 'border-(--color-border)' }}">
                {{ $user->group_report_opt_in ? 'Activé' : 'Désactivé' }}
                <span class="w-9 h-5 rounded-full relative transition-colors {{ $user->group_report_opt_in ? 'bg-(--color-success)' : 'bg-(--color-border)' }}"><span class="absolute top-0.5 w-4 h-4 rounded-full bg-white shadow transition-all {{ $user->group_report_opt_in ? 'left-[18px]' : 'left-0.5' }}"></span></span>
            </button>
            @unless ($user->email)
                <p class="text-xs text-(--color-warning-text)">Ajoutez une adresse e-mail dans <a href="{{ route('account.settings') }}" wire:navigate class="underline">Mon profil</a>.</p>
            @endunless
        </div>
    </div>

    @include('livewire.group._revenue-chart', ['chart' => $chart])
</div>
