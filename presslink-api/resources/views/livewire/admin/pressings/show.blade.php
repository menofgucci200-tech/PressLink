<div class="max-w-4xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.pressings.index') }}" class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center hover:border-(--color-primary)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
        </a>
        <div>
            <div class="font-semibold">{{ $pressing->name }}</div>
            <div class="text-xs text-(--color-text-muted) font-mono">{{ $pressing->code }}</div>
        </div>
        <div class="flex-1"></div>
        <span class="text-xs font-medium {{ $pressing->status->value === 'active' ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">
            {{ $pressing->status->label() }}
        </span>
        <button wire:click="togglePressingStatus" wire:loading.attr="disabled" wire:target="togglePressingStatus"
                class="h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-primary) disabled:opacity-60">
            {{ $pressing->status->value === 'active' ? 'Suspendre' : 'Réactiver' }}
        </button>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Équipe</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ $pressing->staff_count }}</div>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Clients</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ $pressing->customers_count }}</div>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Commandes</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ $pressing->orders_count }}</div>
        </div>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Informations</div>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="flex justify-between"><span class="text-(--color-text-secondary)">Téléphone</span><span class="font-medium tabular-nums">{{ $pressing->phone }}</span></div>
            <div class="flex justify-between"><span class="text-(--color-text-secondary)">Email</span><span class="font-medium">{{ $pressing->email ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-(--color-text-secondary)">Ville</span><span class="font-medium">{{ $pressing->city ?? '—' }}</span></div>
            <div class="flex justify-between"><span class="text-(--color-text-secondary)">Adresse</span><span class="font-medium">{{ $pressing->address ?? '—' }}</span></div>
        </div>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden mb-6">
        <div class="px-5 py-3.5 border-b border-(--color-border) text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Équipe</div>

        @if ($generatedPassword)
            <div class="mx-5 mt-4 p-3 rounded-lg bg-(--color-success-tint) text-(--color-success-text) text-sm">
                Nouveau mot de passe temporaire (à transmettre vous-même, il ne sera plus affiché) :
                <span class="font-mono font-semibold">{{ $generatedPassword }}</span>
            </div>
        @endif

        @if ($staff->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-(--color-text-muted)">Aucun membre.</div>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($staff as $member)
                        <tr class="border-t border-(--color-border) first:border-t-0">
                            <td class="px-5 py-3 font-medium">{{ $member->name }}</td>
                            <td class="px-5 py-3 text-(--color-text-secondary)">{{ $member->email }}</td>
                            <td class="px-5 py-3">{{ $member->pivot->role->label() }}</td>
                            <td class="px-5 py-3 text-xs font-medium {{ $member->pivot->is_active ? 'text-(--color-success-text)' : 'text-(--color-text-muted)' }}">
                                {{ $member->pivot->is_active ? 'Actif' : 'Retiré' }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($member->pivot->is_active)
                                    <button wire:click="resetStaffPassword({{ $member->id }})"
                                            wire:confirm="Générer un nouveau mot de passe pour {{ $member->name }} ? L'ancien ne fonctionnera plus."
                                            wire:loading.attr="disabled" wire:target="resetStaffPassword({{ $member->id }})"
                                            class="text-xs font-medium text-(--color-primary) disabled:opacity-60">
                                        Réinitialiser le mot de passe
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-4">Abonnement</div>

        @if ($saved)
            <div class="mb-4 p-3 rounded-lg bg-(--color-success-tint) text-(--color-success-text) text-sm">Abonnement mis à jour.</div>
        @endif

        <form wire:submit="saveSubscription" class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Plan</label>
                <select wire:model="plan" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @foreach (\App\Enums\SubscriptionPlan::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
                @error('plan') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Statut</label>
                <select wire:model="status" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @foreach (\App\Enums\SubscriptionStatus::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
                @error('status') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Quota de commandes (vide = illimité)</label>
                <input type="number" min="0" wire:model="ordersLimit" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('ordersLimit') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div></div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Fin d'essai</label>
                <input type="date" wire:model="trialEndsAt" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('trialEndsAt') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Fin de période en cours</label>
                <input type="date" wire:model="currentPeriodEndsAt" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('currentPeriodEndsAt') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-2 flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="saveSubscription"
                        class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                    <span wire:loading.remove wire:target="saveSubscription">Enregistrer</span>
                    <span wire:loading wire:target="saveSubscription">Enregistrement…</span>
                </button>
            </div>
        </form>
    </div>
</div>
