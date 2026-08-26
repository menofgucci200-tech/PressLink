@php
    $statusColors = $subscription ? match ($subscription->status) {
        \App\Enums\SubscriptionStatus::Trialing => ['bg' => 'var(--color-info-tint)', 'fg' => 'var(--color-info)'],
        \App\Enums\SubscriptionStatus::Active => ['bg' => 'var(--color-success-tint)', 'fg' => 'var(--color-success-text)'],
        \App\Enums\SubscriptionStatus::Expired, \App\Enums\SubscriptionStatus::Canceled => ['bg' => 'var(--color-error-tint)', 'fg' => 'var(--color-error)'],
    } : null;
@endphp
<div class="max-w-3xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Abonnement</h1>
        <p class="text-sm text-(--color-text-muted)">Plan, quota et validité de votre abonnement PressLink.</p>
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
                    @elseif ($subscription->orders_limit !== null && $subscription->orders_used >= $subscription->orders_limit)
                        Le quota de {{ $subscription->orders_limit }} commandes de votre plan {{ $subscription->plan->label() }} est atteint.
                    @else
                        Votre abonnement est {{ mb_strtolower($subscription->status->label()) }}.
                    @endif
                    Contactez PressLink pour renouveler ou changer de plan.
                </p>
            </div>
        @endif

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6">
            <div class="flex items-center justify-between mb-1">
                <div class="font-display text-xl font-bold">Plan {{ $subscription->plan->label() }}</div>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
                      style="background:{{ $statusColors['bg'] }};color:{{ $statusColors['fg'] }}">
                    <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $statusColors['fg'] }}"></span>
                    {{ $subscription->status->label() }}
                </span>
            </div>
            <div class="text-sm text-(--color-text-secondary)">
                {{ $subscription->plan->monthlyPriceFcfa() !== null ? number_format($subscription->plan->monthlyPriceFcfa(), 0, ',', ' ').' FCFA / mois' : 'Sur devis' }}
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Commandes ce mois-ci</div>
                @if ($subscription->orders_limit === null)
                    <div class="font-display text-2xl font-bold tabular-nums">{{ $subscription->orders_used }}</div>
                    <div class="text-xs text-(--color-text-muted) mt-1">Quota illimité</div>
                @else
                    @php $ratio = $subscription->orders_limit > 0 ? min(1, $subscription->orders_used / $subscription->orders_limit) : 1; @endphp
                    <div class="font-display text-2xl font-bold tabular-nums mb-2">
                        {{ $subscription->orders_used }} <span class="text-(--color-text-muted) text-base font-normal">/ {{ $subscription->orders_limit }}</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-(--color-border) overflow-hidden">
                        <div class="h-full rounded-full {{ $ratio >= 1 ? 'bg-(--color-error)' : 'bg-(--color-primary)' }}" style="width: {{ $ratio * 100 }}%"></div>
                    </div>
                @endif
            </div>

            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
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

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 text-sm text-(--color-text-muted)">
            Le changement de plan et le paiement se font manuellement avec l'équipe PressLink au MVP.
        </div>
    @endif
</div>
