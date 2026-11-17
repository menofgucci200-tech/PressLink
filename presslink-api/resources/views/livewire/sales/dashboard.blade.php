@php
    $tones = [
        'info' => 'bg-(--color-info-tint) text-(--color-info)',
        'warning' => 'bg-(--color-warning-tint) text-(--color-warning-text)',
        'success' => 'bg-(--color-success-tint) text-(--color-success-text)',
        'error' => 'bg-(--color-error-tint) text-(--color-error)',
    ];
@endphp
<div>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Bonjour {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
            <p class="text-sm text-(--color-text-muted)">Les pressings que vous avez apportés à PressLink.</p>
        </div>
        <a href="{{ route('sales.pressings.create') }}" wire:navigate class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Inscrire un pressing
        </a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach ($stats as $stat)
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4">
                <div class="text-xs text-(--color-text-muted) mb-1">{{ $stat['label'] }}</div>
                <div class="font-display text-2xl font-bold tabular-nums">{{ $stat['value'] }}</div>
            </div>
        @endforeach
    </div>

    <a href="{{ route('sales.earnings') }}" wire:navigate class="flex flex-wrap items-center gap-x-8 gap-y-2 bg-(--color-primary) text-white rounded-xl px-5 py-4 mb-6 hover:bg-(--color-primary-600)" data-testid="earnings">
        <div>
            <div class="text-xs text-white/80">Mes revenus ce mois-ci</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ number_format($earnings['thisMonth'], 0, ',', ' ') }} F</div>
        </div>
        <div>
            <div class="text-xs text-white/80">À recevoir</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ number_format($earnings['unpaid'], 0, ',', ' ') }} F</div>
        </div>
        <div class="ml-auto text-sm font-semibold">{{ $agent->commissionRate() }} % de l'abonnement de vos pressings →</div>
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_1.4fr] gap-4">
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5" x-data="{ copied: false }">
            <h2 class="font-display text-base font-bold mb-1">Votre code commercial</h2>
            <p class="text-sm text-(--color-text-muted) mb-4">Le pressing qui s'inscrit seul avec ce code ou ce lien vous est rattaché automatiquement.</p>
            <div class="font-mono text-2xl font-bold tracking-wider text-(--color-primary) mb-3" data-testid="sales-code">{{ $agent->code }}</div>
            <div class="flex items-center gap-2 mb-4">
                <input type="text" readonly value="{{ $agent->signupUrl() }}" class="flex-1 min-w-0 h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-xs">
                <button type="button" @click="navigator.clipboard?.writeText(@js($agent->signupUrl())); copied = true; setTimeout(() => copied = false, 2000)"
                        class="h-10 px-3 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-primary)">
                    <span x-show="! copied">Copier</span><span x-show="copied" x-cloak>Copié</span>
                </button>
            </div>
            <a href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener"
               class="inline-flex items-center justify-center w-full h-10 px-4 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D]">Envoyer le lien sur WhatsApp</a>
            @if ($pendingClaims > 0)
                <p class="mt-4 text-sm text-(--color-text-secondary)">{{ $pendingClaims }} signalement(s) en attente de validation. <a href="{{ route('sales.claims') }}" wire:navigate class="font-semibold text-(--color-primary) hover:underline">Voir</a></p>
            @endif
        </div>

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4">
                <h2 class="font-display text-base font-bold">Derniers pressings</h2>
                <a href="{{ route('sales.pressings.index') }}" wire:navigate class="text-sm font-semibold text-(--color-primary) hover:underline">Tous mes pressings</a>
            </div>
            @forelse ($recent as $pressing)
                @php $summary = $pressing->subscription?->summary(); @endphp
                <a href="{{ route('sales.pressings.show', $pressing) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 border-t border-(--color-border) hover:bg-(--color-bg)">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium truncate">{{ $pressing->name }}</div>
                        <div class="text-xs text-(--color-text-muted)">{{ $pressing->city }} · inscrit le {{ ($pressing->sales_agent_attached_at ?? $pressing->created_at)->format('d/m/Y') }}</div>
                    </div>
                    @if ($summary)
                        <span class="flex-none inline-flex items-center h-7 px-2.5 rounded-full text-xs font-semibold {{ $tones[$summary['tone']] }}">{{ $summary['label'] }}</span>
                    @endif
                </a>
            @empty
                <div class="px-5 py-10 border-t border-(--color-border) text-center text-sm text-(--color-text-muted)">
                    Pas encore de pressing. Inscrivez votre premier pressing ou partagez votre lien.
                </div>
            @endforelse
        </div>
    </div>
</div>
