<div>
    @if (! $pressing)
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-8 text-center text-(--color-text-secondary)">
            Votre compte n'est rattaché à aucun pressing pour le moment.
        </div>
    @else
        <div class="flex items-start justify-between gap-6 mb-7">
            <div>
                <h1 class="font-display text-2xl font-bold mb-1">Bonjour, {{ $pressing->name }}</h1>
                <p class="text-sm text-(--color-text-muted)">{{ now()->translatedFormat('l j F Y') }} @if($pressing->city) · {{ $pressing->city }} @endif</p>
            </div>
            <a href="{{ route('orders.create') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nouvelle commande
            </a>
        </div>

        <div class="grid grid-cols-4 gap-4 mb-7">
            @php
                $kpis = [
                    ['label' => 'Nouvelles', 'value' => $counts['recue'], 'color' => 'var(--color-info)'],
                    ['label' => 'En traitement', 'value' => $counts['traitement'], 'color' => 'var(--color-secondary)'],
                    ['label' => 'Prêtes', 'value' => $counts['prete'], 'color' => 'var(--color-success)'],
                    ['label' => 'Récupérées', 'value' => $counts['recuperee'], 'color' => 'var(--color-text-muted)'],
                ];
            @endphp
            @foreach ($kpis as $kpi)
                <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-2 h-2 rounded-full" style="background:{{ $kpi['color'] }}"></span>
                        <span class="text-[13px] font-medium text-(--color-text-secondary)">{{ $kpi['label'] }}</span>
                    </div>
                    <div class="text-3xl font-bold tabular-nums">{{ $kpi['value'] }}</div>
                    <div class="text-xs text-(--color-text-muted) mt-1">aujourd'hui</div>
                </div>
            @endforeach
        </div>

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-(--color-border)">
                <h2 class="font-semibold text-sm">Commandes récentes</h2>
                <a href="{{ route('orders.index') }}" class="text-sm font-medium text-(--color-primary)">Voir tout →</a>
            </div>
            @include('livewire.orders._table', ['orders' => $recent, 'showPhone' => false])
        </div>
    @endif
</div>
