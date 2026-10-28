@php
    $fcfa = fn (?int $amount) => number_format((int) $amount, 0, ',', ' ');
    $inProgress = $request->hasOnlinePaymentInProgress();
    $payable = $request->canBePaidOnline();
@endphp
<div class="max-w-xl">
    <a href="{{ route('subscription.show') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-medium text-(--color-text-secondary) hover:text-(--color-primary) mb-4">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Abonnement
    </a>

    <h1 class="font-display text-2xl font-bold mb-1">Récapitulatif du paiement</h1>
    <p class="text-sm text-(--color-text-muted) mb-6">Vérifiez votre choix, puis payez sur la page sécurisée de GeniusPay.</p>

    @if ($echec)
        <div class="mb-5 p-4 rounded-xl bg-(--color-warning-tint) text-(--color-warning-text) text-sm" role="status" data-testid="checkout-not-confirmed">
            <p class="font-semibold mb-0.5">Paiement non confirmé pour le moment.</p>
            <p>S'il a abouti, votre demande s'activera d'elle-même dans quelques minutes. Sinon, vous pouvez réessayer ci-dessous.</p>
        </div>
    @endif

    <div class="bg-(--color-surface) border border-(--color-border) rounded-2xl overflow-hidden mb-4">
        <div class="p-5 sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Vous payez</p>
            <p class="font-display text-lg font-bold mb-3" data-testid="checkout-label">{{ $request->label() }}</p>

            <ul class="space-y-2 text-sm text-(--color-text-secondary)">
                @if ($period)
                    @if ($period['after_trial'])
                        <li>Votre essai gratuit continue jusqu'au <span class="font-semibold text-(--color-text-primary)">{{ $period['starts_at']->format('d/m/Y') }}</span>.</li>
                        <li>La formule payée prend le relais jusqu'au <span class="font-semibold text-(--color-text-primary)" data-testid="checkout-period-end">{{ $period['ends_at']->format('d/m/Y') }}</span>.</li>
                    @elseif ($request->kind === \App\Models\SubscriptionRequest::KIND_RENEWAL && $period['starts_at']->isPast() && $subscription?->current_period_ends_at?->isFuture())
                        <li>Votre période en cours est prolongée jusqu'au <span class="font-semibold text-(--color-text-primary)" data-testid="checkout-period-end">{{ $period['ends_at']->format('d/m/Y') }}</span>.</li>
                    @else
                        <li>Période payée : du <span class="font-semibold text-(--color-text-primary)">{{ $period['starts_at']->format('d/m/Y') }}</span> au <span class="font-semibold text-(--color-text-primary)" data-testid="checkout-period-end">{{ $period['ends_at']->format('d/m/Y') }}</span>.</li>
                    @endif
                    @if ($request->kind === \App\Models\SubscriptionRequest::KIND_PLAN)
                        <li>La formule {{ $request->plan?->label() }} s'applique dès le paiement confirmé.</li>
                        @if ($credit > 0)
                            <li data-testid="checkout-credit">Les jours non utilisés de votre formule {{ $subscription->plan->label() }} (payée jusqu'au {{ $subscription->current_period_ends_at->format('d/m/Y') }}) sont déduits du prix.</li>
                            @if ($creditCapped)
                                <li class="text-(--color-warning-text)">Vos jours restants valent plus qu'un mois de {{ $request->plan?->label() }} : choisissez le paiement à l'année pour en profiter entièrement.</li>
                            @endif
                        @elseif (! $upgrade && ! $period['after_trial'] && $subscription?->status === \App\Enums\SubscriptionStatus::Active && $subscription->current_period_ends_at?->isPast() === false && $period['starts_at']->isFuture())
                            <li data-testid="checkout-downgrade">Vous ne perdez aucun jour payé : votre nouvelle période commence le {{ $period['starts_at']->format('d/m/Y') }}, à la fin de votre période {{ $subscription->plan->label() }}.</li>
                        @endif
                    @endif
                @elseif ($request->kind === \App\Models\SubscriptionRequest::KIND_OPTION)
                    <li>L'option est ajoutée à votre formule dès le paiement confirmé.</li>
                @elseif ($request->kind === \App\Models\SubscriptionRequest::KIND_ORDERS)
                    <li>Les commandes s'ajoutent à votre quota dès le paiement confirmé, jusqu'au {{ now()->endOfMonth()->format('d/m/Y') }}.</li>
                @endif
            </ul>
        </div>
        @if ($credit > 0)
            <dl class="px-5 sm:px-6 py-3 border-t border-(--color-border) text-sm space-y-1.5">
                <div class="flex justify-between gap-3">
                    <dt class="text-(--color-text-secondary)">Formule {{ $request->plan?->label() }} ({{ $request->cycle === \App\Models\SubscriptionRequest::ANNUAL ? '1 an' : '1 mois' }})</dt>
                    <dd class="tabular-nums">{{ $fcfa($planPrice) }} F</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-(--color-text-secondary)">Jours {{ $subscription->plan->label() }} non utilisés</dt>
                    <dd class="tabular-nums text-(--color-success-text)">− {{ $fcfa($credit) }} F</dd>
                </div>
            </dl>
        @endif
        <div class="flex items-center justify-between gap-3 px-5 sm:px-6 py-4 bg-(--color-bg) border-t border-(--color-border)">
            <span class="text-sm font-semibold">À payer</span>
            <span class="font-display text-2xl font-extrabold tabular-nums" data-testid="checkout-amount">
                {{ $request->amount_fcfa !== null ? $fcfa($request->amount_fcfa).' F' : 'Montant à confirmer' }}
            </span>
        </div>
    </div>

    @if ($payable)
        @if ($inProgress)
            <p class="mb-3 text-sm text-(--color-text-secondary)">Paiement commencé le {{ $request->payment_started_at->format('d/m à H:i') }} : vous pouvez le reprendre.</p>
        @endif
        <button type="button" wire:click="pay" wire:loading.attr="disabled" data-testid="checkout-pay"
                class="w-full min-h-13 px-6 py-2 rounded-xl bg-(--color-primary) text-white font-bold hover:bg-(--color-primary-600) disabled:opacity-60 flex flex-col items-center justify-center leading-tight">
            <span wire:loading.remove wire:target="pay">{{ $inProgress ? 'Reprendre le paiement' : 'Payer '.$fcfa($request->amount_fcfa).' F' }}</span>
            <span wire:loading wire:target="pay">Ouverture de la page de paiement…</span>
        </button>
        <p class="mt-3 text-center text-xs text-(--color-text-muted)">
            Wave, Orange Money, MTN Mobile Money ou carte bancaire, sur la page sécurisée de GeniusPay.
            Votre demande s'active dès que le paiement est confirmé.
        </p>
    @else
        <div class="p-4 rounded-xl bg-(--color-info-tint) text-(--color-info) text-sm">
            Le montant de cette demande sera confirmé par l'équipe PressLink, qui vous enverra un lien de paiement.
        </div>
    @endif

    @unless ($inProgress)
        <div class="mt-6 text-center">
            <button type="button" wire:click="cancel" wire:confirm="Annuler cette demande ?" class="text-sm font-medium text-(--color-text-muted) hover:text-(--color-error)">
                Annuler cette demande
            </button>
        </div>
    @endunless
</div>
