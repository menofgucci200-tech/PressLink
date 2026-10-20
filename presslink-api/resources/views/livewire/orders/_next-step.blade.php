{{--
    Prochaine étape de la commande : un seul gros bouton pour l'action attendue
    (démarrer, marquer prête, remettre au client), en haut de la fiche ; les
    actions rares (mettre en attente, annuler) restent discrètes en dessous.
--}}
@use('App\Enums\OrderStatus')
@php
    $status = $order->status;
    $balance = $order->balance_due_fcfa;
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $steps = [OrderStatus::Recue, OrderStatus::Traitement, OrderStatus::Prete, OrderStatus::Recuperee];
    $reached = match ($status) {
        OrderStatus::Recue, OrderStatus::Attente => 0,
        OrderStatus::Traitement => 1,
        OrderStatus::Prete => 2,
        OrderStatus::Recuperee => 3,
        OrderStatus::Annulee => -1,
    };
    $primary = match ($status) {
        OrderStatus::Recue => ['action' => OrderStatus::Traitement, 'label' => 'Démarrer le traitement', 'hint' => 'Les pièces partent à l\'atelier.'],
        OrderStatus::Attente => ['action' => OrderStatus::Traitement, 'label' => 'Reprendre le traitement', 'hint' => 'La commande était en attente.'],
        OrderStatus::Traitement => ['action' => OrderStatus::Prete, 'label' => 'Marquer prête', 'hint' => 'Le client est prévenu dans l\'application.'],
        OrderStatus::Prete => ['action' => OrderStatus::Recuperee, 'label' => $balance > 0 ? 'Encaisser '.$fcfa($balance).' F et remettre' : 'Remettre au client', 'hint' => $balance > 0 ? 'Le client règle le reste en récupérant ses pièces.' : 'La commande est déjà payée.'],
        default => null,
    };
    // Accès du rôle : remise (et encaissement) d'un côté, atelier de l'autre.
    $allowedAction = fn (OrderStatus $action) => $action === OrderStatus::Recuperee ? $canRecordPayments : $canWorkshop;
    $primaryAllowed = $primary !== null && $allowedAction($primary['action']);
    $secondary = collect($nextActions)->reject(fn ($action) => ($primary !== null && $action === $primary['action']) || $action === OrderStatus::Annulee
        || ($action === OrderStatus::Recuperee && ! $acceptsCustomers) || ! $allowedAction($action));
    $cancellableByAdminOnly = $acceptsCustomers && in_array(OrderStatus::Annulee, $nextActions, true) && ! $canCancel;
    // Pressing fermé : la remise au client attend l'ouverture (l'atelier continue).
    $handOverClosed = ! $acceptsCustomers && $status === OrderStatus::Prete;
@endphp

@if ($status === OrderStatus::Annulee)
    @php
        $cancelledEntry = $order->statusHistory->where('status', OrderStatus::Annulee)->last();
        $refunds = $order->payments->filter->isRefund();
    @endphp
    <div class="mb-6 p-4 rounded-xl bg-(--color-error-tint) border border-[color-mix(in_srgb,var(--color-error)_25%,transparent)] text-sm">
        <div class="font-semibold text-(--color-error)">
            Commande annulée{{ $cancelledEntry ? ' le '.$cancelledEntry->created_at->format('d/m/Y à H:i') : '' }}{{ $cancelledEntry?->changedBy ? ' par '.$cancelledEntry->changedBy->name : '' }}
        </div>
        @if ($order->cancellation_reason)
            <div class="mt-1 text-(--color-text-primary)"><span class="text-(--color-text-secondary)">Motif :</span> {{ $order->cancellation_reason->label() }}{{ $order->cancellation_note ? ' — '.$order->cancellation_note : '' }}</div>
        @endif
        @if ($refunds->isNotEmpty())
            <div class="mt-1 text-(--color-text-secondary)">Remboursé au client : {{ $fcfa(abs((int) $refunds->sum('amount_fcfa'))) }} F ({{ $refunds->map(fn ($r) => $r->method->label())->unique()->implode(', ') }}).</div>
        @elseif ($order->amount_paid_fcfa > 0)
            <div class="mt-1 text-(--color-text-secondary)">Montant versé conservé : {{ $fcfa($order->amount_paid_fcfa) }} F.</div>
        @endif
    </div>
@else
    <div class="mb-6 bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-5">
        {{-- Où en est la commande --}}
        <ol class="flex items-center mb-4" aria-label="Avancement">
            @foreach ($steps as $i => $step)
                @php $done = $i < $reached || ($i === $reached && $status === OrderStatus::Recuperee); $current = $i === $reached && $status !== OrderStatus::Recuperee; @endphp
                <li class="flex items-center {{ $loop->last ? '' : 'flex-1' }}" @if ($current) aria-current="step" @endif>
                    <span class="flex items-center gap-2 flex-none">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold
                            {{ $done ? 'bg-(--color-success) text-white' : ($current ? 'bg-(--color-primary) text-white ring-4 ring-(--color-primary-tint)' : 'bg-(--color-border) text-(--color-text-muted)') }}">
                            @if ($done)
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
                            @else
                                {{ $i + 1 }}
                            @endif
                        </span>
                        <span class="text-[13px] font-medium {{ $current ? 'text-(--color-text-primary)' : 'text-(--color-text-muted)' }} {{ $current ? '' : 'max-sm:hidden' }}">
                            {{ $step === OrderStatus::Recue && $status === OrderStatus::Attente ? 'En attente' : $step->label() }}
                        </span>
                    </span>
                    @unless ($loop->last)
                        <span class="flex-1 h-0.5 mx-2 sm:mx-3 rounded {{ $i < $reached ? 'bg-(--color-success)' : 'bg-(--color-border)' }}"></span>
                    @endunless
                </li>
            @endforeach
        </ol>

        @if ($primary === null)
            <p class="text-sm text-(--color-success-text) font-medium">Commande terminée : le client a récupéré ses pièces.</p>
        @else
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-1 min-w-0">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Prochaine étape</div>
                    <div class="text-sm text-(--color-text-secondary)">{{ $handOverClosed ? $order->pressing->closedMessage() : $primary['hint'] }}</div>
                    @if ($status === OrderStatus::Prete && $balance > 0 && ! $handOverClosed && $primaryAllowed)
                        <div class="flex flex-wrap gap-1.5 mt-2" role="radiogroup" aria-label="Payé en">
                            @foreach ($paymentMethods as $method)
                                <button type="button" wire:key="settle-{{ $method->value }}" wire:click="$set('paymentMethod', '{{ $method->value }}')" role="radio" aria-checked="{{ $paymentMethod === $method->value ? 'true' : 'false' }}"
                                        class="h-8 px-3 rounded-full border text-xs font-medium transition-colors {{ $paymentMethod === $method->value ? 'border-(--color-primary) bg-(--color-primary) text-white' : 'border-(--color-border) hover:border-(--color-text-muted)' }}">
                                    {{ $method->label() }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if (! $primaryAllowed)
                    <span class="h-12 px-5 rounded-xl bg-(--color-bg) text-(--color-text-muted) text-sm font-semibold flex items-center justify-center text-center">
                        {{ $primary['action'] === OrderStatus::Recuperee ? 'Remise faite par un collègue habilité' : 'Statut changé par l\'atelier' }}
                    </span>
                @elseif ($handOverClosed)
                    <span class="h-12 px-6 rounded-xl bg-(--color-bg) text-(--color-text-muted) text-[15px] font-semibold flex items-center justify-center gap-2 cursor-not-allowed" title="{{ $order->pressing->closedMessage() }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        Remise à l'ouverture
                    </span>
                @elseif ($status === OrderStatus::Prete && $balance > 0)
                    <button type="button" wire:click="settleAndRecover"
                            data-confirm-title="Encaisser et remettre" data-confirm-label="Encaisser et remettre"
                            wire:confirm="Encaisser {{ $fcfa($balance) }} F et marquer la commande {{ $order->order_number }} comme récupérée par {{ $order->customer->fullName() }} ?"
                            wire:loading.attr="disabled" wire:target="settleAndRecover"
                            class="h-12 px-6 rounded-xl bg-(--color-primary) text-white text-[15px] font-semibold shadow-sm hover:bg-(--color-primary-600) disabled:opacity-60 flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="settleAndRecover">{{ $primary['label'] }}</span>
                        <span wire:loading wire:target="settleAndRecover">Mise à jour…</span>
                    </button>
                @else
                    @php $target = $primary['action']->value; @endphp
                    <button type="button" wire:click="transitionTo('{{ $target }}')"
                            @if ($target === 'recuperee')
                                data-confirm-title="Remise au client" data-confirm-label="Confirmer la remise"
                                wire:confirm="Confirmez-vous que {{ $order->customer->fullName() }} a récupéré la commande {{ $order->order_number }} ({{ $fcfa($order->total_fcfa) }} F, déjà payée) ?"
                            @endif
                            wire:loading.attr="disabled" wire:target="transitionTo('{{ $target }}')"
                            class="h-12 px-6 rounded-xl bg-(--color-primary) text-white text-[15px] font-semibold shadow-sm hover:bg-(--color-primary-600) disabled:opacity-60 flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="transitionTo('{{ $target }}')" class="flex items-center gap-2">
                            {{ $primary['label'] }}
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                        </span>
                        <span wire:loading wire:target="transitionTo('{{ $target }}')">Mise à jour…</span>
                    </button>
                @endif
            </div>

            @if ($secondary->isNotEmpty() || $canCancel || $cancellableByAdminOnly)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 pt-3 border-t border-(--color-border) text-[13px]">
                    <span class="text-(--color-text-muted)">Autres actions :</span>
                    @foreach ($secondary as $action)
                        <button type="button" wire:click="transitionTo('{{ $action->value }}')"
                                @if ($action === OrderStatus::Recuperee && $balance > 0)
                                    data-confirm-title="Remettre sans encaisser" data-confirm-label="Remettre sans encaisser" data-confirm-tone="warning"
                                    wire:confirm="{{ $order->customer->fullName() }} récupère la commande {{ $order->order_number }} sans régler les {{ $fcfa($balance) }} F restants : ce montant restera dû (à encaisser plus tard dans Paiement)."
                                @endif
                                wire:loading.attr="disabled" wire:target="transitionTo('{{ $action->value }}')"
                                class="font-medium hover:underline disabled:opacity-60 text-(--color-text-secondary) hover:text-(--color-primary)">
                            {{ match ($action) {
                                OrderStatus::Attente => 'Mettre en attente',
                                OrderStatus::Recuperee => 'Remettre sans encaisser',
                                OrderStatus::Traitement => 'Démarrer le traitement',
                                default => 'Marquer '.mb_strtolower($action->label()),
                            } }}
                        </button>
                    @endforeach
                    @if ($canCancel && ! $showCancelForm)
                        <button type="button" wire:click="openCancelForm" class="font-medium text-(--color-error) hover:underline">Annuler la commande…</button>
                    @elseif ($cancellableByAdminOnly)
                        <span class="text-(--color-text-muted)">Annulation : réservée au propriétaire et au gérant (le travail a commencé).</span>
                    @endif
                </div>
            @endif

            @if ($showCancelForm && $canCancel)
                @include('livewire.orders._cancel-form')
            @endif
        @endif
    </div>
@endif
