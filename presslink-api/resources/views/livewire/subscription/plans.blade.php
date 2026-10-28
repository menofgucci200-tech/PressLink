<div class="max-w-6xl">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div>
            <a href="{{ route('subscription.show') }}" wire:navigate class="text-sm text-(--color-text-secondary) hover:text-(--color-primary)">← Abonnement</a>
            <h1 class="font-display text-2xl font-bold mt-1 mb-1">Les formules PressLink</h1>
            <p class="text-sm text-(--color-text-muted)">Comparez ce que comprend chaque formule et ce que vous gagnez à passer au-dessus.</p>
        </div>
        @if ($subscription)
            <div class="inline-flex p-1 rounded-xl bg-(--color-bg) border border-(--color-border)" role="radiogroup" aria-label="Payer">
                @foreach ([\App\Models\SubscriptionRequest::MONTHLY => 'Payer au mois', \App\Models\SubscriptionRequest::ANNUAL => "Payer à l'année"] as $value => $label)
                    <button type="button" role="radio" aria-checked="{{ $cycle === $value ? 'true' : 'false' }}" wire:click="$set('cycle', '{{ $value }}')"
                            class="h-9 px-4 rounded-lg text-sm font-semibold transition-colors {{ $cycle === $value ? 'bg-(--color-surface) text-(--color-primary) shadow-sm' : 'text-(--color-text-secondary)' }}">{{ $label }}</button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($launchOffer = \App\Support\PlanCatalog::launchOffer())
        <div class="mb-6 rounded-xl border border-(--color-primary)/30 bg-(--color-primary-tint) text-(--color-primary) px-4 py-3 text-sm font-medium">
            Offre de lancement : {{ $launchOffer }}
        </div>
    @endif

    @if ($subscription?->isInTrial())
        <div class="mb-6 rounded-xl bg-(--color-info-tint) text-(--color-info) px-4 py-3 text-sm">
            Essai gratuit en cours{{ $subscription->trial_ends_at ? ' jusqu\'au '.$subscription->trial_ends_at->format('d/m/Y') : '' }} : toutes les fonctions sont ouvertes. Choisissez la formule à garder ensuite.
        </div>
    @endif

    {{-- Ce que le pressing gagne à passer à la formule supérieure --}}
    @if ($gains && ($gains['features'] !== [] || $gains['orders']))
        <section class="mb-8 rounded-2xl border border-(--color-primary)/30 bg-(--color-primary-tint) p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-(--color-primary)">En passant de {{ $current->label() }} à {{ $next->label() }}</p>
                    <h2 class="font-display text-xl font-bold mt-1">Ce que vous gagnez</h2>
                </div>
                @php $nextPrice = $cycle === \App\Models\SubscriptionRequest::ANNUAL ? $next->annualPriceFcfa() : $next->monthlyPriceFcfa(); @endphp
                @if ($nextPrice !== null)
                    <p class="text-sm text-(--color-text-secondary)"><span class="font-display text-2xl font-bold text-(--color-text-primary) tabular-nums">{{ \App\Support\PlanCatalog::fcfa($nextPrice) }} F</span> {{ $cycle === \App\Models\SubscriptionRequest::ANNUAL ? 'par an' : 'par mois' }}</p>
                @endif
            </div>
            <ul class="mt-4 grid sm:grid-cols-2 gap-3 text-sm">
                @if ($gains['orders'])
                    <li class="flex items-start gap-2.5 rounded-xl bg-(--color-surface) p-3"><svg class="w-4 h-4 flex-none mt-0.5 text-(--color-primary)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span class="font-semibold">{{ $gains['orders'] }}</span></li>
                @endif
                @foreach ($gains['features'] as $feature)
                    <li class="flex items-start gap-2.5 rounded-xl bg-(--color-surface) p-3">
                        <svg class="w-4 h-4 flex-none mt-0.5 text-(--color-primary)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        <span><span class="font-semibold">{{ $feature->label() }}</span><br><span class="text-(--color-text-secondary)">{{ $feature->description() }}</span></span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <x-plans.cards :plans="$plans" mode="espace" :current="$current" :pending-plans="$pendingPlans" class="mb-10" />

    <x-plans.compare :plans="$plans" :current="$current" class="mb-6" />

    <p class="text-sm text-(--color-text-muted)">
        @if (\App\Support\GeniusPay::isConfigured())
            Après votre demande, payez-la en ligne depuis la page <a href="{{ route('subscription.show') }}" wire:navigate class="font-semibold text-(--color-primary) hover:underline">Abonnement</a> (Wave, Orange Money, MTN ou carte) : votre nouvelle formule s'applique dès le paiement confirmé.
        @else
            Après votre demande, l'équipe PressLink vous envoie un lien de paiement Wave du montant de la formule. Votre nouvelle formule s'applique dès le paiement reçu.
        @endif
        Une seule fonction vous manque ? Ajoutez-la en option depuis la page <a href="{{ route('subscription.show') }}#options" wire:navigate class="font-semibold text-(--color-primary) hover:underline">Abonnement</a>.
    </p>
</div>
