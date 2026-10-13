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
        <button wire:click="togglePressingStatus"
                data-confirm-title="{{ $pressing->status->value === 'active' ? 'Suspendre le pressing' : 'Réactiver le pressing' }}" data-confirm-label="{{ $pressing->status->value === 'active' ? 'Suspendre' : 'Réactiver' }}" data-confirm-tone="{{ $pressing->status->value === 'active' ? 'danger' : 'primary' }}"
                wire:confirm="{{ $pressing->status->value === 'active' ? 'Suspendre '.$pressing->name.' ? Il ne pourra plus créer de commande ni accueillir de nouveaux clients.' : 'Réactiver '.$pressing->name.' ? Il retrouvera immédiatement l\'accès à toutes les fonctionnalités.' }}"
                wire:loading.attr="disabled" wire:target="togglePressingStatus"
                class="h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-primary) disabled:opacity-60">
            {{ $pressing->status->value === 'active' ? 'Suspendre' : 'Réactiver' }}
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px animate-step">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Équipe</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ $pressing->staff_count }}</div>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px animate-step">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Clients</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ $pressing->customers_count }}</div>
        </div>
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px animate-step">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Commandes</div>
            <div class="font-display text-2xl font-bold tabular-nums">{{ $pressing->orders_count }}</div>
        </div>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Informations</div>
            @unless ($editingInfo)
                <button wire:click="editInfo" class="text-xs font-medium text-(--color-primary) hover:underline">Modifier</button>
            @endunless
        </div>


        @if ($editingInfo)
            <form novalidate wire:submit="saveInfo" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom</label>
                    <input type="text" wire:model="info.name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('info.name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone (+2250…)</label>
                    <input type="text" wire:model="info.phone" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('info.phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Email</label>
                    <input type="email" wire:model="info.email" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('info.email') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Ville</label>
                    <input type="text" wire:model="info.city" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('info.city') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Adresse</label>
                    <input type="text" wire:model="info.address" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('info.address') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Description</label>
                    <textarea wire:model="info.description" rows="3" class="w-full px-3 py-2 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)"></textarea>
                    @error('info.description') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 flex justify-end gap-2">
                    <button type="button" wire:click="cancelEditInfo" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-text-muted)">Annuler</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveInfo" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">Enregistrer</button>
                </div>
            </form>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div class="flex justify-between gap-3"><span class="text-(--color-text-secondary)">Téléphone</span><span class="font-medium tabular-nums">{{ $pressing->phone }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-(--color-text-secondary)">Email</span><span class="font-medium">{{ $pressing->email ?? '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-(--color-text-secondary)">Ville</span><span class="font-medium">{{ $pressing->city ?? '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-(--color-text-secondary)">Adresse</span><span class="font-medium">{{ $pressing->address ?? '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-(--color-text-secondary)">Créé le</span><span class="font-medium">{{ $pressing->created_at->format('d/m/Y') }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-(--color-text-secondary)">Services</span><span class="font-medium tabular-nums">{{ $pressing->services_count }}</span></div>
                @if ($pressing->description)
                    <p class="sm:col-span-2 text-(--color-text-secondary)">{{ $pressing->description }}</p>
                @endif
            </div>
        @endif
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden mb-6">
        <div class="px-5 py-3.5 border-b border-(--color-border) text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Équipe</div>


        @if ($staff->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-(--color-text-muted)">Aucun membre.</div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[640px] whitespace-nowrap text-sm">
                <tbody>
                    @foreach ($staff as $member)
                        <tr class="border-t border-(--color-border) first:border-t-0 hover:bg-(--color-bg)">
                            <td class="px-5 py-3 font-medium">{{ $member->name }}</td>
                            <td class="px-5 py-3 text-(--color-text-secondary)">{{ $member->email }}</td>
                            <td class="px-5 py-3">{{ $member->pivot->role->label() }}</td>
                            <td class="px-5 py-3 text-xs font-medium {{ $member->pivot->is_active ? 'text-(--color-success-text)' : 'text-(--color-text-muted)' }}">
                                {{ $member->pivot->is_active ? 'Actif' : 'Retiré' }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                @if ($member->pivot->is_active)
                                    <button wire:click="resetStaffPassword({{ $member->id }})"
                                            data-confirm-title="Réinitialiser le mot de passe" data-confirm-label="Réinitialiser" data-confirm-tone="warning"
                                            wire:confirm="Le mot de passe de {{ $member->name }} sera remplacé par {{ \App\Actions\Auth\ResetPasswordToDefault::DEFAULT_PASSWORD }} et ses sessions ouvertes seront fermées. Il devra en choisir un nouveau depuis « Mon compte »."
                                            wire:loading.attr="disabled" wire:target="resetStaffPassword({{ $member->id }})"
                                            class="text-xs font-medium text-(--color-primary) hover:underline disabled:opacity-60">
                                        Réinitialiser le mot de passe
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-4">Abonnement</div>


        <form novalidate wire:submit="saveSubscription" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Facturation</label>
                <select wire:model="billingCycle" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    <option value="monthly">Mensuelle</option>
                    <option value="annual">Annuelle</option>
                </select>
                @error('billingCycle') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="flex items-center gap-2.5 text-sm cursor-pointer">
                    <input type="checkbox" wire:model="setupFeePaid" class="w-4 h-4 rounded border-(--color-border) accent-(--color-primary)">
                    <span>Ticket d'entrée réglé</span>
                    @if ($pressing->subscription?->setup_fee_paid_at)
                        <span class="text-xs text-(--color-text-muted)">le {{ $pressing->subscription->setup_fee_paid_at->format('d/m/Y') }}</span>
                    @endif
                </label>
            </div>
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
            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="saveSubscription"
                        class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                    <span wire:loading.remove wire:target="saveSubscription">Enregistrer</span>
                    <span wire:loading wire:target="saveSubscription">Enregistrement…</span>
                </button>
            </div>
        </form>
    </div>
    <div class="mt-6 border border-red-200 rounded-xl p-5" x-data="{ open: false }">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-xl">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-error) mb-1">Zone de danger</div>
                <p class="text-sm text-(--color-text-secondary)">
                    Supprimer définitivement ce pressing efface aussi ses {{ $pressing->orders_count }} commande(s), ses {{ $pressing->services_count }} service(s),
                    son abonnement et le rattachement de ses {{ $pressing->customers_count }} client(s). Les comptes clients et ceux de l'équipe sont conservés.
                    Pour simplement bloquer l'accès, préférez « Suspendre ».
                </p>
            </div>
            <button type="button" @click="open = !open" class="h-9 px-3.5 rounded-lg border border-red-200 text-sm font-semibold text-(--color-error) hover:bg-(--color-error-tint)">Supprimer le pressing</button>
        </div>
        <form novalidate x-show="open" x-cloak wire:submit="deletePressing" class="mt-4 flex flex-wrap items-start gap-3">
            <div>
                <input type="text" wire:model="deleteConfirmation" placeholder="Saisissez {{ $pressing->code }}" autocomplete="off"
                       class="h-10 px-3 rounded-lg border border-(--color-border) text-sm font-mono focus:outline-none focus:border-(--color-error)">
                @error('deleteConfirmation') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="deletePressing"
                    class="h-10 px-4 rounded-lg bg-(--color-error) text-white text-sm font-semibold hover:brightness-95 disabled:opacity-60">Supprimer définitivement</button>
        </form>
    </div>
</div>
