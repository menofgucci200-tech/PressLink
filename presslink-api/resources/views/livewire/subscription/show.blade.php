@php
    $statusColors = $subscription ? match ($subscription->status) {
        \App\Enums\SubscriptionStatus::Trialing => ['bg' => 'var(--color-info-tint)', 'fg' => 'var(--color-info)'],
        \App\Enums\SubscriptionStatus::Active => ['bg' => 'var(--color-success-tint)', 'fg' => 'var(--color-success-text)'],
        \App\Enums\SubscriptionStatus::Expired, \App\Enums\SubscriptionStatus::Canceled => ['bg' => 'var(--color-error-tint)', 'fg' => 'var(--color-error)'],
    } : null;
    $nextReset = now()->addMonthNoOverflow()->startOfMonth()->format('d/m/Y');
    $pendingFeatures = $pendingRequests->where('kind', \App\Models\SubscriptionRequest::KIND_OPTION)->map(fn ($r) => $r->feature?->value)->filter()->all();
    $pendingPlans = $pendingRequests->where('kind', \App\Models\SubscriptionRequest::KIND_PLAN)->map(fn ($r) => $r->plan?->value)->filter()->all();
@endphp
<div class="max-w-3xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Abonnement</h1>
        <p class="text-sm text-(--color-text-muted)">Votre formule, vos commandes du mois et les options de votre pressing.</p>
    </div>

    @if (! $subscription)
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 text-sm text-(--color-text-secondary)">
            Aucun abonnement configuré pour ce pressing — création de commandes illimitée pendant la phase pilote.
        </div>
    @else
        @if (! $subscription->allowsNewOrder())
            <div class="mb-5 p-4 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">
                <p class="font-semibold mb-1">Création de commandes bloquée.</p>
                <p>
                    @if ($subscription->status === \App\Enums\SubscriptionStatus::Trialing && $subscription->trial_ends_at?->isPast())
                        Votre période d'essai s'est terminée le {{ $subscription->trial_ends_at->format('d/m/Y') }}.
                        Contactez PressLink pour choisir votre formule.
                    @elseif ($subscription->periodEnded())
                        Votre période payée s'est terminée le {{ $subscription->current_period_ends_at->format('d/m/Y') }}.
                        <a href="#renouveler" class="font-semibold underline">Renouveler mon abonnement</a>
                    @elseif ($subscription->hasReachedOrdersLimit())
                        Les {{ number_format($subscription->ordersLimitThisMonth(), 0, ',', ' ') }} commandes de ce mois sont utilisées. Le compteur repart à zéro le {{ $nextReset }}.
                        <a href="#commandes-en-plus" class="font-semibold underline">Ajouter des commandes ce mois-ci</a>
                    @else
                        Votre abonnement est {{ mb_strtolower($subscription->status->label()) }}. Contactez PressLink pour le renouveler.
                    @endif
                </p>
            </div>
        @endif

        @if ($subscription->periodEnded() && $subscription->allowsNewOrder())
            <div class="mb-5 p-4 rounded-lg bg-(--color-warning-tint) text-(--color-warning-text) text-sm">
                <span class="font-semibold">Votre période payée s'est terminée le {{ $subscription->current_period_ends_at->format('d/m/Y') }}.</span>
                Renouvelez votre abonnement pour continuer sans interruption.
                <a href="#renouveler" class="font-semibold underline">Renouveler</a>
            </div>
        @endif

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6">
            <div class="flex items-center justify-between mb-1">
                <div class="font-display text-xl font-bold">Formule {{ $subscription->plan->label() }}</div>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                      style="background:{{ $statusColors['bg'] }};color:{{ $statusColors['fg'] }}">
                    <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $statusColors['fg'] }}"></span>
                    {{ $subscription->status->label() }}
                </span>
            </div>
            <div class="text-sm text-(--color-text-secondary)">
                {{ $subscription->plan->monthlyPriceFcfa() !== null ? number_format($subscription->plan->monthlyPriceFcfa(), 0, ',', ' ').' FCFA / mois' : 'Sur devis' }}
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-4">
                <a href="{{ route('subscription.plans') }}" wire:navigate
                   class="inline-flex items-center h-9 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Comparer les formules</a>
                @if ($subscription->plan !== \App\Enums\SubscriptionPlan::Premium)
                    <span class="text-xs text-(--color-text-muted)">Voyez ce que vous gagnez à passer à la formule supérieure.</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px animate-step">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Commandes ce mois-ci</div>
                @php $limit = $subscription->ordersLimitThisMonth(); $used = $subscription->ordersUsedThisMonth(); @endphp
                @if ($limit === null)
                    <div class="font-display text-2xl font-bold tabular-nums">{{ $used }}</div>
                    <div class="text-xs text-(--color-text-muted) mt-1">Quota illimité</div>
                @else
                    @php $ratio = $limit > 0 ? min(1, $used / $limit) : 1; @endphp
                    <div class="font-display text-2xl font-bold tabular-nums mb-2">
                        {{ $used }} <span class="text-(--color-text-muted) text-base font-normal">/ {{ $limit }}</span>
                    </div>
                    <div class="text-xs text-(--color-text-muted) mb-2">
                        @if ($subscription->extraOrdersThisMonth() > 0)
                            Dont {{ $subscription->extraOrdersThisMonth() }} ajoutées ce mois-ci ·
                        @endif
                        Repart à zéro le {{ $nextReset }}
                    </div>
                    <div class="h-1.5 rounded-full bg-(--color-border) overflow-hidden">
                        <div class="h-full rounded-full transition-[width] duration-500 ease-out {{ $ratio >= 1 ? 'bg-(--color-error)' : 'bg-(--color-primary)' }}" style="width: {{ $ratio * 100 }}%"></div>
                    </div>
                @endif
            </div>

            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px animate-step">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">
                    {{ $subscription->status === \App\Enums\SubscriptionStatus::Trialing ? "Fin de l'essai" : 'Période en cours' }}
                </div>
                @if ($subscription->status === \App\Enums\SubscriptionStatus::Trialing)
                    <div class="font-display text-2xl font-bold">{{ $subscription->trial_ends_at?->format('d/m/Y') ?? '—' }}</div>
                @else
                    <div class="text-sm">
                        <div class="flex justify-between mb-1.5">
                            <span class="text-(--color-text-secondary)">Début</span>
                            <span class="font-medium">{{ $subscription->current_period_starts_at?->format('d/m/Y') ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-(--color-text-secondary)">Fin</span>
                            <span class="font-medium">{{ $subscription->current_period_ends_at?->format('d/m/Y') ?? '—' }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Renouveler : l'équipe PressLink envoie le lien de paiement Wave --}}
        @php
            $monthly = $subscription->plan->monthlyPriceFcfa();
            $annual = $subscription->plan->annualPriceFcfa();
            $renewalPending = $pendingRequests->contains('kind', \App\Models\SubscriptionRequest::KIND_RENEWAL);
        @endphp
        <div id="renouveler" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6 scroll-mt-20">
            <h2 class="font-display text-base font-bold mb-1">Payer ou renouveler mon abonnement</h2>
            <p class="text-sm text-(--color-text-muted) mb-4">
                Formule {{ $subscription->plan->label() }}. Vous recevez un lien de paiement Wave ; la période est prolongée dès le paiement reçu.
            </p>
            @if ($renewalPending)
                <span class="inline-flex items-center h-9 px-3 rounded-lg bg-(--color-info-tint) text-(--color-info) text-sm font-medium">Demande envoyée : voir « Demandes en cours » ci-dessous</span>
            @else
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="requestRenewal('monthly')" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
                        Un mois @if ($monthly !== null)<span class="font-normal text-(--color-text-secondary) tabular-nums">· {{ number_format($monthly, 0, ',', ' ') }} F</span>@endif
                    </button>
                    <button type="button" wire:click="requestRenewal('annual')" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
                        Un an @if ($annual !== null)<span class="font-normal text-(--color-text-secondary) tabular-nums">· {{ number_format($annual, 0, ',', ' ') }} F</span>@endif
                    </button>
                </div>
            @endif
        </div>

        @if ($limit !== null)
            <div id="commandes-en-plus" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6 scroll-mt-20">
                <h2 class="font-display text-base font-bold mb-1">Besoin de plus de commandes ce mois-ci ?</h2>
                <p class="text-sm text-(--color-text-muted) mb-4">Les commandes ajoutées valent jusqu'à la fin du mois, sans changer de formule.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($orderPacks as $pack)
                        <button type="button" wire:click="requestOrders({{ $pack['count'] }})" wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold tabular-nums hover:border-(--color-primary) hover:text-(--color-primary)">
                            + {{ number_format($pack['count'], 0, ',', ' ') }} commandes
                            @if ($pack['price'] !== null)
                                <span class="font-normal text-(--color-text-muted)">· {{ number_format($pack['price'], 0, ',', ' ') }} F</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div id="options" class="mb-6 scroll-mt-20">
            <h2 class="font-display text-lg font-bold mb-1">Fonctions et options</h2>
            <p class="text-sm text-(--color-text-muted) mb-4">
                @if ($subscription->isInTrial())
                    Pendant votre essai gratuit, toutes les fonctions sont ouvertes{{ $subscription->trial_ends_at ? ' jusqu\'au '.$subscription->trial_ends_at->format('d/m/Y') : '' }}. Ensuite, vous gardez celles de votre formule et celles prises en option.
                @else
                    Une fonction qui n'est pas dans votre formule peut être ajoutée seule, en option.
                @endif
            </p>
            <div class="space-y-3">
                @foreach ($features as $feature)
                    @php
                        $included = $subscription->plan->includes($feature);
                        $addon = ! $included && $subscription->hasAddon($feature);
                        $pending = in_array($feature->value, $pendingFeatures, true);
                        $openForTrial = ! $included && ! $addon && $subscription->isInTrial();
                        $highlighted = $option === $feature->value && ! $included && ! $addon;
                    @endphp
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 bg-(--color-surface) border rounded-xl p-4 {{ $highlighted ? 'border-(--color-primary) ring-2 ring-(--color-primary)/20' : 'border-(--color-border)' }}"
                         data-feature="{{ $feature->value }}" @if ($highlighted) x-init="$el.scrollIntoView({ block: 'center' })" @endif>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm">{{ $feature->label() }}</div>
                            <div class="text-sm text-(--color-text-muted)">{{ $feature->description() }}</div>
                            @if ($highlighted)
                                <div class="text-xs text-(--color-primary) font-medium mt-1">Cette page n'est pas incluse dans votre formule {{ $subscription->plan->label() }}.</div>
                            @endif
                        </div>
                        <div class="flex-none">
                            @if ($included)
                                <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-(--color-success-tint) text-(--color-success-text) text-xs font-semibold">✓ Incluse dans votre formule</span>
                            @elseif ($openForTrial)
                                <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-(--color-info-tint) text-(--color-info) text-xs font-semibold">Ouverte pendant l'essai</span>
                            @elseif ($addon)
                                <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-(--color-success-tint) text-(--color-success-text) text-xs font-semibold">✓ Option active</span>
                            @elseif ($pending)
                                <span class="inline-flex items-center h-8 px-3 rounded-full bg-(--color-info-tint) text-(--color-info) text-xs font-semibold">Demande envoyée</span>
                            @else
                                <div class="flex flex-col items-start sm:items-end gap-1">
                                    <button type="button" wire:click="requestOption('{{ $feature->value }}')" wire:loading.attr="disabled"
                                            class="inline-flex items-center h-9 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                                        Ajouter cette option
                                    </button>
                                    <span class="text-[11px] text-(--color-text-muted)">
                                        @if ($feature->priceFcfa() !== null) {{ number_format($feature->priceFcfa(), 0, ',', ' ') }} F / mois @endif
                                        @if ($feature->priceFcfa() !== null && $feature->includedFrom()) · @endif
                                        @if ($feature->includedFrom()) ou incluse dans {{ $feature->includedFrom()->label() }} @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($pendingRequests->isNotEmpty())
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6">
                <h2 class="font-display text-base font-bold mb-3">Demandes en cours</h2>
                <ul class="divide-y divide-(--color-border)">
                    @foreach ($pendingRequests as $request)
                        <li class="flex flex-wrap items-center gap-3 py-3 text-sm" data-request="{{ $request->id }}">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium">{{ $request->label() }}</div>
                                <div class="text-xs text-(--color-text-muted)">
                                    Demandé le {{ $request->created_at->format('d/m/Y') }}
                                    @if ($request->amount_fcfa !== null) · <span class="font-semibold text-(--color-text-primary) tabular-nums">{{ number_format($request->amount_fcfa, 0, ',', ' ') }} F à payer</span>@endif
                                    @if ($request->hasOnlinePaymentInProgress())
                                        · <span class="text-(--color-text-secondary)">paiement en ligne commencé le {{ $request->payment_started_at->format('d/m à H:i') }}</span>
                                    @elseif (! $request->payment_link && ! $request->canBePaidOnline())
                                        · lien de paiement à venir
                                    @endif
                                </div>
                            </div>
                            @if ($request->canBePaidOnline())
                                <button type="button" wire:click="payOnline({{ $request->id }})" wire:loading.attr="disabled" data-testid="pay-online"
                                        class="inline-flex flex-col items-center justify-center min-h-10 px-4 py-1 rounded-lg bg-(--color-primary) text-white text-sm font-semibold leading-tight hover:bg-(--color-primary-600) disabled:opacity-60">
                                    <span>{{ $request->hasOnlinePaymentInProgress() ? 'Reprendre le paiement' : 'Payer en ligne' }}</span>
                                    <span class="text-[11px] font-medium opacity-85">Wave, Orange Money, MTN, carte</span>
                                </button>
                            @elseif ($request->payment_link)
                                <a href="{{ $request->payment_link }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-[#1DC8FF] text-slate-900 text-sm font-semibold hover:brightness-95">
                                    Payer avec Wave
                                </a>
                            @endif
                            @unless ($request->hasOnlinePaymentInProgress())
                                <button type="button" wire:click="cancelRequest({{ $request->id }})" wire:confirm="Annuler cette demande ?" class="text-(--color-text-muted) hover:text-(--color-error) text-xs font-medium">Annuler</button>
                            @endunless
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-(--color-text-muted)">{{ \App\Support\GeniusPay::isConfigured() ? 'Payée en ligne, votre demande s\'active dès que le paiement est confirmé.' : 'Une fois le paiement fait, l\'équipe PressLink active votre demande.' }}</p>
            </div>
        @endif

        @if ($declinedRequests->isNotEmpty())
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6" data-testid="declined-requests">
                <h2 class="font-display text-base font-bold mb-3">Demandes refusées</h2>
                <ul class="divide-y divide-(--color-border)">
                    @foreach ($declinedRequests as $request)
                        <li class="py-3 text-sm">
                            <div class="font-medium">{{ $request->label() }}</div>
                            <div class="text-xs text-(--color-text-muted)">
                                Refusée le {{ $request->handled_at->format('d/m/Y') }}@if ($request->decline_reason) : <span class="text-(--color-text-secondary)">{{ $request->decline_reason }}</span>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-(--color-text-muted)">Une question ? Écrivez à l'équipe PressLink depuis la page Support, ou refaites la demande.</p>
            </div>
        @endif

        @if ($payments->isNotEmpty())
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6" data-testid="subscription-payments">
                <h2 class="font-display text-base font-bold mb-3">Mes paiements</h2>
                <ul class="divide-y divide-(--color-border)">
                    @foreach ($payments as $payment)
                        <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <span><span class="text-(--color-text-muted) tabular-nums">{{ $payment->handled_at?->format('d/m/Y') }}</span> · {{ $payment->label() }}@if ($payment->paid_online_at) <span class="text-xs text-(--color-text-muted)">· payé en ligne</span>@endif</span>
                            <span class="font-semibold tabular-nums whitespace-nowrap">{{ number_format($payment->amount_fcfa, 0, ',', ' ') }} F</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 text-sm text-(--color-text-muted)">
            @if (\App\Support\GeniusPay::isConfigured())
                Après votre demande, payez-la en ligne (Wave, Orange Money, MTN ou carte) : elle s'active dès que le paiement est confirmé.
            @else
                Après votre demande, l'équipe PressLink vous envoie un lien de paiement Wave, puis active la demande dès le paiement reçu.
            @endif
        </div>
    @endif
</div>
