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
    <p class="text-sm text-(--color-text-muted) mb-6">Vérifiez votre choix, puis choisissez comment payer.</p>

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
    </div>

    @if ($payable)
        @php
            // Logos des opérateurs (public/images/paiement) ; la carte est dessinée (Visa, Mastercard).
            $logos = [
                'wave' => 'images/paiement/wave.webp',
                'orange_money' => 'images/paiement/orange-money.webp',
                'mtn_money' => 'images/paiement/mtn-momo.webp',
                'moov_money' => 'images/paiement/moov-money.webp',
            ];
            $methodLabel = \App\Models\SubscriptionRequest::ONLINE_METHODS[$method] ?? 'Wave';
            $needsPhone = in_array($method, \App\Models\SubscriptionRequest::PHONE_METHODS, true);
        @endphp
        <fieldset class="mb-4">
            <legend class="text-sm font-semibold mb-2">Payer avec</legend>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" role="radiogroup">
                @foreach (\App\Models\SubscriptionRequest::ONLINE_METHODS as $code => $label)
                    <label wire:key="method-{{ $code }}" data-testid="method-{{ $code }}"
                           class="flex items-center gap-2.5 min-h-12 px-3 py-2 rounded-xl border bg-(--color-surface) cursor-pointer text-sm font-semibold transition-colors {{ $method === $code ? 'border-(--color-primary) ring-2 ring-(--color-primary)/20' : 'border-(--color-border) hover:border-(--color-primary)/50' }}">
                        <input type="radio" wire:model.live="method" value="{{ $code }}" class="sr-only">
                        @if (isset($logos[$code]))
                            <img src="{{ asset($logos[$code]) }}" alt="" width="40" height="40" loading="lazy"
                                 class="flex-none w-10 h-10 rounded-lg object-contain {{ $code === 'orange_money' ? 'bg-white ring-1 ring-black/10' : '' }}">
                        @else
                            <span class="flex-none inline-flex flex-col items-center justify-center gap-0.5 w-10 h-10 rounded-lg bg-white ring-1 ring-black/10" aria-hidden="true">
                                <svg viewBox="0 0 40 13" class="w-7"><text x="20" y="11" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="13" font-weight="900" font-style="italic" fill="#1A1F71">VISA</text></svg>
                                <svg viewBox="0 0 30 18" class="w-5"><circle cx="11" cy="9" r="8" fill="#EB001B"/><circle cx="19" cy="9" r="8" fill="#F79E1B"/><path d="M15 2.07a8 8 0 0 1 0 13.86 8 8 0 0 1 0-13.86z" fill="#FF5F00"/></svg>
                            </span>
                        @endif
                        <span class="min-w-0 leading-tight">{{ $label }}@if ($code === 'card')<span class="block text-[11px] font-medium text-(--color-text-muted)">Visa, Mastercard</span>@endif</span>
                    </label>
                @endforeach
            </div>
            @error('method') <p class="text-xs text-(--color-error) mt-1.5">{{ $message }}</p> @enderror
        </fieldset>

        @if ($needsPhone)
            <div class="mb-4">
                <label for="checkout-phone" class="block text-sm font-semibold mb-1.5">Numéro {{ $methodLabel }} qui paie</label>
                <input id="checkout-phone" type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" placeholder="07 00 00 00 00"
                       class="w-full h-12 px-3 rounded-xl border border-(--color-border) bg-(--color-surface) text-base tabular-nums focus:outline-none focus:border-(--color-primary)">
                <p class="text-xs text-(--color-text-muted) mt-1">Vous validerez le paiement sur ce téléphone.</p>
                @error('phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
        @endif

    @endif

    <div class="bg-(--color-surface) border border-(--color-border) rounded-2xl overflow-hidden mb-4" data-testid="checkout-total">
        @if ($credit > 0 || $fee > 0)
            <dl class="px-5 sm:px-6 py-3 text-sm space-y-1.5">
                @if ($credit > 0)
                    <div class="flex justify-between gap-3">
                        <dt class="text-(--color-text-secondary)">Formule {{ $request->plan?->label() }} ({{ $request->cycle === \App\Models\SubscriptionRequest::ANNUAL ? '1 an' : '1 mois' }})</dt>
                        <dd class="tabular-nums">{{ $fcfa($planPrice) }} F</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-(--color-text-secondary)">Jours {{ $subscription->plan->label() }} non utilisés</dt>
                        <dd class="tabular-nums text-(--color-success-text)">− {{ $fcfa($credit) }} F</dd>
                    </div>
                @else
                    <div class="flex justify-between gap-3">
                        <dt class="text-(--color-text-secondary)">{{ $request->label() }}</dt>
                        <dd class="tabular-nums">{{ $fcfa($request->amount_fcfa) }} F</dd>
                    </div>
                @endif
                @if ($fee > 0)
                    <div class="flex justify-between gap-3" data-testid="checkout-fee">
                        <dt class="text-(--color-text-secondary)">Frais de l'opérateur {{ \App\Models\SubscriptionRequest::ONLINE_METHODS[$method] ?? '' }} ({{ rtrim(rtrim(number_format($feeRate, 2, ',', ' '), '0'), ',') }} %)</dt>
                        <dd class="tabular-nums">+ {{ $fcfa($fee) }} F</dd>
                    </div>
                @endif
            </dl>
        @endif
        <div class="flex items-center justify-between gap-3 px-5 sm:px-6 py-4 bg-(--color-bg) {{ $credit > 0 || $fee > 0 ? 'border-t border-(--color-border)' : '' }}">
            <span class="text-sm font-semibold">À payer</span>
            <span class="font-display text-2xl font-extrabold tabular-nums" data-testid="checkout-amount">
                {{ $request->amount_fcfa !== null ? $fcfa($request->amount_fcfa + $fee).' F' : 'Montant à confirmer' }}
            </span>
        </div>
    </div>

    @if ($payable)
        @if ($inProgress)
            <p class="mb-3 text-sm text-(--color-text-secondary)">Paiement commencé le {{ $request->payment_started_at->format('d/m à H:i') }}{{ $request->payment_method && isset(\App\Models\SubscriptionRequest::ONLINE_METHODS[$request->payment_method]) ? ' avec '.\App\Models\SubscriptionRequest::ONLINE_METHODS[$request->payment_method] : '' }} : vous pouvez le reprendre.</p>
        @endif
        <button type="button" wire:click="pay" wire:loading.attr="disabled" data-testid="checkout-pay"
                class="w-full min-h-13 px-6 py-2 rounded-xl bg-(--color-primary) text-white font-bold hover:bg-(--color-primary-600) disabled:opacity-60 flex flex-col items-center justify-center leading-tight">
            <span wire:loading.remove wire:target="pay">{{ $inProgress && $request->payment_method === $method ? 'Reprendre le paiement' : 'Payer '.$fcfa($request->amount_fcfa + $fee).' F avec '.$methodLabel }}</span>
            <span wire:loading wire:target="pay">Ouverture du paiement {{ $methodLabel }}…</span>
        </button>
        <p class="mt-3 text-center text-xs text-(--color-text-muted)">
            {{ $method === 'card' ? 'Vous saisissez votre carte sur la page sécurisée de notre prestataire de paiement, GeniusPay.' : 'Vous validez le paiement chez '.$methodLabel.', par l\'intermédiaire de notre prestataire GeniusPay.' }}
            Votre demande s'active dès que le paiement est confirmé.
            @if ($fee > 0) Les frais de l'opérateur sont à la charge du payeur. @endif
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
