<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('clients.index') }}" data-back aria-label="Retour" title="Retour" class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center hover:border-(--color-primary)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
        </a>
        <div>
            <div class="font-semibold">
                {{ $customer->fullName() }}
                @unless ($customer->is_active)
                    <span class="ml-1.5 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-error-tint) text-(--color-error)" title="Compte désactivé par PressLink : le client ne peut plus se connecter à l'application.">Compte désactivé</span>
                @endunless
            </div>
            <div class="text-xs text-(--color-text-muted) tabular-nums">{{ $customer->phone }}</div>
        </div>
        @if ($canResetPassword)
            <div class="flex-1"></div>
            <button type="button" wire:click="resetPassword" wire:loading.attr="disabled" wire:target="resetPassword"
                    data-confirm-title="Réinitialiser le mot de passe" data-confirm-label="Réinitialiser" data-confirm-tone="warning"
                    wire:confirm="Le mot de passe de {{ $customer->fullName() }} sera réinitialisé à {{ \App\Actions\Auth\ResetPasswordToDefault::DEFAULT_PASSWORD }} et le client sera déconnecté de l'application. Après la réinitialisation, il doit changer son mot de passe pour sécuriser son compte."
                    class="h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-primary) hover:text-(--color-primary) disabled:opacity-60">
                Réinitialiser le mot de passe
            </button>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px animate-step">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Commandes</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ $orders->count() }}</div>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px animate-step">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Total dépensé</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ number_format($totalSpentFcfa, 0, ',', ' ') }} F</div>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px animate-step">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Client depuis</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ $joinedAt?->format('d/m/Y') ?? '—' }}</div>
        </div>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-(--color-border) text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">
            Historique des commandes
        </div>
        @if ($orders->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucune commande pour le moment.</div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[640px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Commande</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Déposée le</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Total</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg) cursor-pointer" onclick="window.location='{{ route('orders.show', $order) }}'">
                            <td class="px-5 py-3.5 font-medium tabular-nums">{{ $order->order_number }}</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">{{ $order->dropped_off_at->format('d/m/Y') }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums font-medium">{{ number_format($order->total_fcfa, 0, ',', ' ') }} F</td>
                            <td class="px-5 py-3.5 text-right"><x-status-badge :status="$order->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
</div>
