<div class="max-w-5xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Demandes et paiements</h1>
        <p class="text-sm text-(--color-text-muted)">Les demandes des pressings (formule, renouvellement, options, commandes en plus) et les paiements reçus.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ($totals as $label => $amount)
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Reçu {{ mb_strtolower($label) }}</div>
                <div class="font-display text-2xl font-bold tabular-nums">{{ number_format($amount, 0, ',', ' ') }} F</div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex gap-1.5 bg-(--color-surface) border border-(--color-border) rounded-xl p-1" role="tablist">
            @foreach (\App\Livewire\Admin\Requests\Index::TABS as $key => $label)
                <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')"
                        class="h-8 px-3 rounded-lg text-sm font-semibold {{ $tab === $key ? 'bg-(--color-primary) text-white' : 'text-(--color-text-secondary) hover:text-(--color-text-primary)' }}">
                    {{ $label }}
                    @if ($key === 'a-traiter' && $pendingCount > 0)
                        <span class="ml-1 inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[11px] tabular-nums {{ $tab === $key ? 'bg-white/25' : 'bg-(--color-warning-tint) text-(--color-warning-text)' }}">{{ $pendingCount }}</span>
                    @endif
                </button>
            @endforeach
        </div>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher un pressing (nom ou code)" aria-label="Rechercher un pressing"
               class="w-full sm:w-72 h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
    </div>

    @if ($requests->isEmpty())
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-8 text-center text-sm text-(--color-text-muted)">
            {{ match ($tab) { 'paiements' => 'Aucun paiement reçu pour l\'instant.', 'refusees' => 'Aucune demande refusée.', default => 'Aucune demande à traiter. Tout est à jour.' } }}
        </div>
    @elseif ($tab === 'a-traiter')
        <p class="text-xs text-(--color-text-secondary) mb-3">Créez le lien de paiement dans Wave Business, collez-le ici et envoyez-le ; activez la demande une fois le paiement reçu. Les plus anciennes d'abord.</p>
        <ul class="space-y-2">
            @foreach ($requests as $request)
                <x-admin.subscription-request :request="$request" show-pressing />
            @endforeach
        </ul>
    @else
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
            <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-sm">
                <thead class="text-[11px] uppercase tracking-wide text-(--color-text-muted) border-b border-(--color-border)">
                    <tr>
                        <th class="text-left font-semibold px-5 py-3">Date</th>
                        <th class="text-left font-semibold px-5 py-3">Pressing</th>
                        <th class="text-left font-semibold px-5 py-3">Demande</th>
                        <th class="text-right font-semibold px-5 py-3">{{ $tab === 'paiements' ? 'Montant' : '' }}</th>
                        @if ($tab === 'paiements')
                            <th class="text-right font-semibold px-5 py-3">Net reçu</th>
                        @endif
                        <th class="text-left font-semibold px-5 py-3">Par</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $request)
                        <tr class="border-t border-(--color-border) first:border-t-0" wire:key="handled-{{ $request->id }}">
                            <td class="px-5 py-3 whitespace-nowrap text-(--color-text-secondary) tabular-nums">{{ ($request->handled_at ?? $request->updated_at)->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($request->pressing)
                                    <a href="{{ route('admin.pressings.show', $request->pressing) }}" wire:navigate class="font-medium text-(--color-primary) hover:underline">{{ $request->pressing->name }}</a>
                                @else — @endif
                            </td>
                            <td class="px-5 py-3">
                                {{ $request->label() }}
                                @if ($request->decline_reason) <div class="text-xs text-(--color-text-muted)">Motif : {{ $request->decline_reason }}</div> @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-right font-semibold tabular-nums">
                                {{ $tab === 'paiements' ? ($request->amount_fcfa !== null ? number_format($request->amount_fcfa, 0, ',', ' ').' F' : '—') : '' }}
                                @if ($tab === 'paiements' && $request->payment_fee_fcfa)
                                    <div class="text-xs font-normal text-(--color-text-muted)">+ {{ number_format($request->payment_fee_fcfa, 0, ',', ' ') }} F de frais payés</div>
                                @endif
                            </td>
                            @if ($tab === 'paiements')
                                <td class="px-5 py-3 whitespace-nowrap text-right tabular-nums" data-testid="net-received">
                                    @if ($request->net_received_fcfa !== null)
                                        <span class="font-semibold">{{ number_format($request->net_received_fcfa, 0, ',', ' ') }} F</span>
                                        @if ($request->gateway_fees_fcfa !== null)
                                            <div class="text-xs text-(--color-text-muted)">frais retenus {{ number_format($request->gateway_fees_fcfa, 0, ',', ' ') }} F</div>
                                        @endif
                                    @else
                                        <span class="text-(--color-text-muted)">{{ $request->paid_online_at ? 'en attente' : '—' }}</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-5 py-3 whitespace-nowrap text-xs text-(--color-text-muted)">{{ $request->handler?->name ?? ($request->paid_online_at ? 'Payé en ligne' : '—') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </div>
    @endif

    <div class="mt-5">{{ $requests->links() }}</div>
</div>
