@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ').' F';
@endphp
<div>
    <a href="{{ route('admin.sales-agents.index') }}" wire:navigate class="text-sm text-(--color-text-secondary) hover:text-(--color-primary)">← Commerciaux</a>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mt-2 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">{{ $agent->user->name }}</h1>
            <p class="text-sm text-(--color-text-muted)">
                Code <span class="font-mono">{{ $agent->code }}</span> · <span class="tabular-nums">{{ \App\Support\PhoneNumber::national($agent->user->phone) }}</span> ·
                <a href="{{ route('admin.pressings.index', ['commercial' => $agent->id]) }}" wire:navigate class="text-(--color-primary) hover:underline">{{ $agent->pressings_count }} pressing(s)</a> ·
                {{ $agent->commissionRate() }} % de l'abonnement
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 max-w-xl">
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4">
            <div class="text-xs text-(--color-text-muted) mb-1">À payer</div>
            <div class="font-display text-2xl font-bold tabular-nums" data-testid="unpaid">{{ $fcfa($unpaid) }}</div>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4">
            <div class="text-xs text-(--color-text-muted) mb-1">Déjà payé</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ $fcfa($paid) }}</div>
        </div>
    </div>

    @forelse ($months as $key => $month)
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden mb-4" data-month="{{ $key }}">
            <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                <div class="flex-1 min-w-48">
                    <h2 class="font-display text-base font-bold capitalize">{{ $month['month']->translatedFormat('F Y') }}</h2>
                    <p class="text-sm text-(--color-text-muted)">{{ $month['count'] }} paiement(s) · {{ $fcfa($month['total']) }} de commissions{{ $month['paid'] > 0 ? ' · '.$fcfa($month['paid']).' payés' : '' }}</p>
                </div>
                @if ($month['unpaid'] > 0)
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" wire:model="references.{{ $key }}" placeholder="Référence (Wave…)" aria-label="Référence du paiement" class="h-9 w-40 px-2.5 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                        <button type="button" wire:click="markMonthPaid('{{ $key }}')"
                                wire:confirm="Marquer {{ $fcfa($month['unpaid']) }} comme payés à {{ $agent->user->name }} ? Faites d'abord le paiement."
                                data-confirm-title="Commissions payées" data-confirm-label="Marquer payé"
                                class="h-9 px-3 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Marquer {{ $fcfa($month['unpaid']) }} payés</button>
                    </div>
                @else
                    <span class="inline-flex items-center h-7 px-2.5 rounded-full bg-(--color-success-tint) text-(--color-success-text) text-xs font-semibold">Payé</span>
                @endif
            </div>
            <div class="overflow-x-auto"><table class="w-full min-w-[640px] whitespace-nowrap text-sm">
                <tbody>
                    @foreach ($linesByMonth[$key] as $line)
                        <tr class="border-t border-(--color-border)">
                            <td class="px-5 py-2.5 tabular-nums text-(--color-text-secondary)">{{ $line->earned_at->format('d/m/Y') }}</td>
                            <td class="px-5 py-2.5">
                                @if ($line->pressing_id)
                                    <a href="{{ route('admin.pressings.show', $line->pressing_id) }}" wire:navigate class="font-medium text-(--color-primary) hover:underline">{{ $line->pressing_name }}</a>
                                @else
                                    <span class="font-medium">{{ $line->pressing_name }}</span>
                                @endif
                                <span class="text-xs text-(--color-text-muted)"> · {{ $line->label }}</span>
                            </td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ $fcfa($line->base_fcfa) }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums font-semibold">{{ $fcfa($line->amount_fcfa) }} <span class="text-xs font-normal text-(--color-text-muted)">({{ $line->rate }} %)</span></td>
                            <td class="px-5 py-2.5 text-xs text-(--color-text-muted)">
                                @if ($line->paid_at)
                                    Payée le {{ $line->paid_at->format('d/m/Y') }}{{ $line->payout_reference ? ' · '.$line->payout_reference : '' }}
                                @else
                                    À payer
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </div>
    @empty
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl px-5 py-12 text-center text-sm text-(--color-text-muted)">
            Pas encore de commission : elle arrive avec le premier paiement d'abonnement d'un de ses pressings.
        </div>
    @endforelse
</div>
