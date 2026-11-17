@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ').' F';
    $th = 'px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)';
@endphp
<div>
    <h1 class="font-display text-2xl font-bold mb-1">Mes revenus</h1>
    <p class="text-sm text-(--color-text-muted) mb-6">{{ $agent->commissionRate() }} % de chaque paiement de l'abonnement de vos pressings (formule ou renouvellement, hors options et frais). Rien pendant le mois offert : la commission arrive avec le premier paiement.</p>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ([['Gagné ce mois-ci', $totals['thisMonth']], ['À recevoir', $totals['unpaid']], ['Déjà reçu', $totals['paid']]] as [$statLabel, $statValue])
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4">
                <div class="text-xs text-(--color-text-muted) mb-1">{{ $statLabel }}</div>
                <div class="font-display text-2xl font-bold tabular-nums">{{ $fcfa($statValue) }}</div>
            </div>
        @endforeach
    </div>

    @if ($months->isEmpty())
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl px-5 py-12 text-center text-sm text-(--color-text-muted)">
            Pas encore de commission. Elle arrive dès qu'un de vos pressings paie son abonnement.
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-[18rem_1fr] gap-4 items-start">
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
                @foreach ($months as $key => $month)
                    <button type="button" wire:click="$set('month', '{{ $key }}')"
                            class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left text-sm border-t first:border-t-0 border-(--color-border) {{ $key === $selected ? 'bg-(--color-primary-tint)' : 'hover:bg-(--color-bg)' }}">
                        <span>
                            <span class="block font-semibold capitalize">{{ $month['month']->translatedFormat('F Y') }}</span>
                            <span class="block text-xs text-(--color-text-muted)">{{ $month['unpaid'] > 0 ? 'À recevoir : '.$fcfa($month['unpaid']) : 'Payé' }}</span>
                        </span>
                        <span class="font-semibold tabular-nums">{{ $fcfa($month['total']) }}</span>
                    </button>
                @endforeach
            </div>

            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
                <div class="overflow-x-auto"><table class="w-full min-w-[560px] whitespace-nowrap text-sm">
                    <thead>
                        <tr class="bg-(--color-bg) text-left">
                            <th class="{{ $th }}">Date</th>
                            <th class="{{ $th }}">Pressing</th>
                            <th class="{{ $th }} text-right">Paiement</th>
                            <th class="{{ $th }} text-right">Commission</th>
                            <th class="{{ $th }}"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr class="border-t border-(--color-border)">
                                <td class="px-5 py-3 tabular-nums text-(--color-text-secondary)">{{ $line->earned_at->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">
                                    <div class="font-medium">{{ $line->pressing_name }}</div>
                                    <div class="text-xs text-(--color-text-muted)">{{ $line->label }}</div>
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ $fcfa($line->base_fcfa) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-semibold">{{ $fcfa($line->amount_fcfa) }} <span class="text-xs font-normal text-(--color-text-muted)">({{ $line->rate }} %)</span></td>
                                <td class="px-5 py-3 text-xs">
                                    @if ($line->paid_at)
                                        <span class="text-(--color-success-text) font-semibold">Payée le {{ $line->paid_at->format('d/m') }}</span>
                                    @else
                                        <span class="text-(--color-text-muted)">À recevoir</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            </div>
        </div>
    @endif
</div>
