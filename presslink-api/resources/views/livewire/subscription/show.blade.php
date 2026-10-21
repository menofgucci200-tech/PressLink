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
                    @elseif ($subscription->hasReachedOrdersLimit())
                        Les {{ number_format($subscription->ordersLimitThisMonth(), 0, ',', ' ') }} commandes de ce mois sont utilisées. Le compteur repart à zéro le {{ $nextReset }}.
                        <a href="#commandes-en-plus" class="font-semibold underline">Ajouter des commandes ce mois-ci</a>
                    @else
                        Votre abonnement est {{ mb_strtolower($subscription->status->label()) }}. Contactez PressLink pour le renouveler.
                    @endif
                </p>
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
            @php $higherPlans = array_slice($plans, array_search($subscription->plan, $plans, true) + 1); @endphp
            @if ($higherPlans)
                <div class="flex flex-wrap gap-2 mt-4">
                    @foreach ($higherPlans as $plan)
                        @if (in_array($plan->value, $pendingPlans, true))
                            <span class="inline-flex items-center h-9 px-3 rounded-lg bg-(--color-info-tint) text-(--color-info) text-sm font-medium">Passage à {{ $plan->label() }} demandé</span>
                        @else
                            <button type="button" wire:click="requestPlan('{{ $plan->value }}')" wire:loading.attr="disabled"
                                    class="inline-flex items-center h-9 px-3 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
                                Passer à {{ $plan->label() }}
                            </button>
                        @endif
                    @endforeach
                </div>
            @endif
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

        @if ($limit !== null)
            <div id="commandes-en-plus" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6 scroll-mt-20">
                <h2 class="font-display text-base font-bold mb-1">Besoin de plus de commandes ce mois-ci ?</h2>
                <p class="text-sm text-(--color-text-muted) mb-4">Les commandes ajoutées valent jusqu'à la fin du mois, sans changer de formule.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($orderPacks as $pack)
                        <button type="button" wire:click="requestOrders({{ $pack }})" wire:loading.attr="disabled"
                                class="inline-flex items-center h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold tabular-nums hover:border-(--color-primary) hover:text-(--color-primary)">
                            + {{ number_format($pack, 0, ',', ' ') }} commandes
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div id="options" class="mb-6 scroll-mt-20">
            <h2 class="font-display text-lg font-bold mb-1">Fonctions et options</h2>
            <p class="text-sm text-(--color-text-muted) mb-4">Une fonction qui n'est pas dans votre formule peut être ajoutée seule, en option.</p>
            <div class="space-y-3">
                @foreach ($features as $feature)
                    @php
                        $included = $subscription->plan->includes($feature);
                        $addon = ! $included && $subscription->hasAddon($feature);
                        $pending = in_array($feature->value, $pendingFeatures, true);
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
                                    <span class="text-[11px] text-(--color-text-muted)">ou incluse dans {{ $feature->includedFrom()->label() }}</span>
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
                        <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <span>{{ $request->label() }} <span class="text-(--color-text-muted)">· le {{ $request->created_at->format('d/m/Y') }}</span></span>
                            <button type="button" wire:click="cancelRequest({{ $request->id }})" class="text-(--color-text-muted) hover:text-(--color-error) text-xs font-medium">Annuler</button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 text-sm text-(--color-text-muted)">
            Après votre demande, l'équipe PressLink vous appelle pour le paiement, puis active l'option sur votre pressing.
        </div>
    @endif
</div>
