@php
    $field = 'h-9 px-2.5 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $card = 'rounded-xl border border-(--color-border) bg-(--color-surface) p-4 shadow-sm';
    $statusTone = [
        \App\Models\PaymentAttempt::PAID => 'bg-(--color-success-tint) text-(--color-success-text)',
        \App\Models\PaymentAttempt::PENDING => 'bg-(--color-info-tint) text-(--color-info)',
    ];
    $chips = ['' => 'Tous', 'paye' => 'Payés', 'echoue' => 'Échoués', 'en-cours' => 'En cours'];
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Paiements</h1>
        <p class="text-sm text-(--color-text-muted)">Chaque paiement reçu ou tenté, réussi ou échoué, avec sa référence PressLink.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="{{ $card }}">
            <p class="text-sm text-(--color-text-muted)">Encaissé</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fcfa($summary['collected']) }} F CFA</p>
            <p class="text-xs text-(--color-text-muted)">{{ $fcfa($summary['paye']) }} paiement{{ $summary['paye'] > 1 ? 's' : '' }} réussi{{ $summary['paye'] > 1 ? 's' : '' }}</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-sm text-(--color-text-muted)">Taux de réussite</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $summary['rate'] !== null ? number_format($summary['rate'], 1, ',', ' ').' %' : '—' }}</p>
            <p class="text-xs text-(--color-text-muted)">{{ $fcfa($summary['paye']) }} payés sur {{ $fcfa($summary['paye'] + $summary['echoue']) }} tentatives</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-sm text-(--color-text-muted)">Paiements échoués</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fcfa($summary['echoue']) }}</p>
            <p class="text-xs text-(--color-text-muted)">refusés, abandonnés ou expirés</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-sm text-(--color-text-muted)">En cours</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fcfa($summary['en-cours']) }}</p>
            <p class="text-xs text-(--color-text-muted)">page de paiement ouverte, sans réponse</p>
        </div>
    </div>

    <div class="{{ $card }}">
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <div class="inline-flex max-w-full overflow-x-auto rounded-lg border border-(--color-border) p-0.5" role="group" aria-label="Statut">
                @foreach ($chips as $value => $label)
                    <button type="button" wire:click="$set('status', '{{ $value }}')" aria-pressed="{{ $status === $value ? 'true' : 'false' }}"
                            @class(['rounded-md px-3 py-1 text-sm whitespace-nowrap', 'bg-(--color-primary) text-white' => $status === $value, 'hover:bg-(--color-bg)' => $status !== $value])>
                        {{ $label }} <span class="tabular-nums opacity-75">{{ $fcfa($value === '' ? $summary['all'] : $summary[$value]) }}</span>
                    </button>
                @endforeach
            </div>
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Référence, pressing…" aria-label="Rechercher"
                   class="{{ $field }} w-full sm:w-56">
            <select wire:model.live="method" class="{{ $field }}" aria-label="Moyen de paiement">
                <option value="">Tous les moyens</option>
                @foreach ($methods as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="pressingId" class="{{ $field }} max-w-[220px]" aria-label="Pressing">
                <option value="">Tous les pressings</option>
                @foreach ($pressings as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
            <span class="flex items-center gap-1.5">
                <input type="date" wire:model.live="dateFrom" class="{{ $field }}" aria-label="Du">
                <span class="text-xs text-(--color-text-muted)">au</span>
                <input type="date" wire:model.live="dateTo" class="{{ $field }}" aria-label="Au">
            </span>
            @if ($hasFilters)
                <button wire:click="resetFilters" class="text-xs font-medium text-(--color-text-muted) hover:text-(--color-primary) underline">Réinitialiser</button>
            @endif
            <span wire:loading.delay class="text-xs text-(--color-text-muted)">Chargement…</span>
        </div>

        @if ($payments->isEmpty())
            <p class="py-10 text-center text-sm text-(--color-text-muted)">{{ $hasFilters ? 'Aucun paiement pour ces filtres.' : 'Aucun paiement pour le moment.' }}</p>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[960px] text-sm">
                <thead>
                    <tr class="text-left text-(--color-text-muted)">
                        <th class="py-1 pr-4 font-semibold">Référence</th>
                        <th class="py-1 pr-4 font-semibold">Date</th>
                        <th class="py-1 pr-4 font-semibold">Pressing</th>
                        <th class="py-1 pr-4 font-semibold">Objet</th>
                        <th class="py-1 pr-4 font-semibold">Moyen</th>
                        <th class="py-1 pr-4 font-semibold text-right">Montant</th>
                        <th class="py-1 pr-4 font-semibold text-right">Net reçu</th>
                        <th class="py-1 font-semibold">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr wire:key="payment-{{ $payment->id }}" class="border-t border-(--color-border) align-top">
                            <td class="py-2 pr-4">
                                <span class="font-mono font-semibold whitespace-nowrap">{{ $payment->reference }}</span>
                                @if ($payment->gateway_reference)
                                    <span class="block text-xs text-(--color-text-muted) font-mono whitespace-nowrap" title="Référence GeniusPay">{{ $payment->gateway_reference }}</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 whitespace-nowrap tabular-nums text-(--color-text-secondary)">{{ $payment->started_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4">
                                @if ($payment->pressing_id)
                                    <a href="{{ route('admin.pressings.show', $payment->pressing_id) }}" wire:navigate class="font-medium hover:underline">{{ $payment->pressing_name }}</a>
                                @else
                                    <span class="font-medium">{{ $payment->pressing_name }}</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-(--color-text-secondary)">{{ $payment->label ?? $payment->subscriptionRequest?->label() ?? '—' }}</td>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $payment->methodLabel() }}</td>
                            <td class="py-2 pr-4 text-right whitespace-nowrap tabular-nums font-medium">{{ $fcfa($payment->collectedFcfa()) }} F</td>
                            <td class="py-2 pr-4 text-right whitespace-nowrap tabular-nums">{{ $payment->status === \App\Models\PaymentAttempt::PAID ? $fcfa($payment->netFcfa()).' F' : '—' }}</td>
                            <td class="py-2">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap {{ $statusTone[$payment->status] ?? 'bg-(--color-error-tint) text-(--color-error)' }}">{{ $payment->statusLabel() }}</span>
                                @if ($payment->failure_reason)
                                    <span class="block mt-0.5 text-xs text-(--color-text-muted) max-w-56">{{ $payment->failure_reason }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
            <div class="mt-4">{{ $payments->links() }}</div>
        @endif
    </div>
</div>
