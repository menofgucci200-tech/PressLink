<div class="max-w-4xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.pressings.index') }}" data-back aria-label="Retour" data-tooltip="Retour" data-tooltip-pos="bottom" class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center hover:border-(--color-primary)">
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
                    <x-phone-input wire:model="info.phone" />
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
        <div class="px-5 py-3.5 border-b border-(--color-border) flex items-center justify-between gap-3">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Équipe</span>
            <a href="{{ route('admin.roles.index', ['pressing' => $pressing->id]) }}" wire:navigate class="text-xs font-semibold text-(--color-primary) hover:underline">Rôles et accès →</a>
        </div>


        @if ($staff->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-(--color-text-muted)">Aucun membre.</div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[720px] whitespace-nowrap text-sm">
                <tbody>
                    @foreach ($staff as $member)
                        <tr class="border-t border-(--color-border) first:border-t-0 hover:bg-(--color-bg)" wire:key="staff-{{ $member->id }}-{{ $memberRoleIds[$member->id] }}-{{ (int) $member->pivot->is_active }}">
                            <td class="px-5 py-3 font-medium">{{ $member->name }}</td>
                            <td class="px-5 py-3 text-(--color-text-secondary)">{{ $member->login ?: ($member->email ?: \App\Support\PhoneNumber::format($member->phone)) }}</td>
                            <td class="px-5 py-2">
                                <select aria-label="Rôle de {{ $member->name }}" wire:change="changeMemberRole({{ $member->id }}, $event.target.value)"
                                        class="h-9 px-2.5 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected($memberRoleIds[$member->id] === $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-5 py-3 text-xs font-medium {{ $member->pivot->is_active ? 'text-(--color-success-text)' : 'text-(--color-text-muted)' }}">
                                {{ $member->pivot->is_active ? 'Actif' : 'Retiré' }}
                            </td>
                            <td class="px-5 py-3 text-right space-x-3">
                                @if ($member->pivot->is_active)
                                    <button wire:click="resetStaffPassword({{ $member->id }})"
                                            data-confirm-title="Réinitialiser le mot de passe" data-confirm-label="Réinitialiser" data-confirm-tone="warning"
                                            wire:confirm="Le mot de passe de {{ $member->name }} sera réinitialisé à {{ \App\Actions\Auth\ResetPasswordToDefault::DEFAULT_PASSWORD }} et ses sessions ouvertes seront fermées. Après la réinitialisation, il doit changer son mot de passe pour sécuriser son compte."
                                            wire:loading.attr="disabled" wire:target="resetStaffPassword({{ $member->id }})"
                                            class="text-xs font-medium text-(--color-primary) hover:underline disabled:opacity-60">
                                        Réinitialiser le mot de passe
                                    </button>
                                @endif
                                <button wire:click="toggleMember({{ $member->id }})"
                                        @if ($member->pivot->is_active)
                                            data-confirm-title="Retirer de l'équipe" data-confirm-label="Retirer" data-confirm-tone="danger"
                                            wire:confirm="Retirer {{ $member->name }} de l'équipe ? Son accès au pressing est coupé ; son compte et son historique sont gardés."
                                        @endif
                                        class="text-xs font-medium {{ $member->pivot->is_active ? 'text-(--color-text-muted) hover:text-(--color-error)' : 'text-(--color-primary) hover:underline' }}">
                                    {{ $member->pivot->is_active ? 'Retirer' : 'Réintégrer' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif

        <div class="border-t border-(--color-border) px-5 py-4">
            @if ($addingMember)
                <form novalidate wire:submit="addMember" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="nm-name" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom</label>
                        <input id="nm-name" type="text" wire:model="newMember.name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                        @error('newMember.name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="nm-login" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Identifiant de connexion</label>
                        <input id="nm-login" type="text" wire:model="newMember.login" autocomplete="off" placeholder="ex. awa.kone" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                        @error('newMember.login') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone (facultatif)</label>
                        <x-phone-input wire:model="newMember.phone" />
                        @error('newMember.phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="nm-password" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Mot de passe (8 caractères minimum)</label>
                        <input id="nm-password" type="text" wire:model="newMember.password" autocomplete="new-password" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                        @error('newMember.password') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="nm-role" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Rôle</label>
                        <select id="nm-role" wire:model="newMember.roleId" class="w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                        @error('newMember.roleId') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end justify-end gap-2">
                        <button type="button" wire:click="$set('addingMember', false)" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium">Annuler</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="addMember" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">Ajouter</button>
                    </div>
                </form>
            @else
                <button type="button" wire:click="$set('addingMember', true)" class="text-sm font-semibold text-(--color-primary) hover:underline">+ Ajouter un membre</button>
            @endif
        </div>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Abonnement</div>
            @if ($pressing->subscription)
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="extend('trial')" wire:confirm="Prolonger l'essai de {{ $pressing->name }} de 7 jours ?"
                            class="h-8 px-3 rounded-lg border border-(--color-border) text-xs font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">+ 7 jours d'essai</button>
                    <button type="button" wire:click="extend('month')" wire:confirm="Offrir un mois de période payée à {{ $pressing->name }} (sans paiement) ?"
                            class="h-8 px-3 rounded-lg border border-(--color-border) text-xs font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">+ 1 mois offert</button>
                </div>
            @endif
        </div>
        @if ($sub = $pressing->subscription)
            <p class="mb-4 text-xs text-(--color-text-secondary)">
                {{ $sub->plan->label() }} · {{ $sub->status->label() }}
                @if ($sub->status === \App\Enums\SubscriptionStatus::Trialing && $sub->trial_ends_at) · essai jusqu'au {{ $sub->trial_ends_at->format('d/m/Y') }}
                @elseif ($sub->current_period_ends_at) · payé jusqu'au {{ $sub->current_period_ends_at->format('d/m/Y') }}
                @endif
            </p>
        @endif

        @if ($pendingRequests->isNotEmpty())
            <div class="mb-5 rounded-lg border border-(--color-warning)/40 bg-(--color-warning-tint) p-4" data-testid="pending-requests">
                <div class="text-sm font-semibold text-(--color-warning-text) mb-1">Demandes du pressing à traiter</div>
                <p class="text-xs text-(--color-text-secondary) mb-3">Créez le lien de paiement dans Wave Business, collez-le ici et envoyez-le ; activez la demande une fois le paiement reçu.</p>
                <ul class="space-y-2">
                    @foreach ($pendingRequests as $request)
                        <x-admin.subscription-request :request="$request" />
                    @endforeach
                </ul>
            </div>
        @endif

        <form novalidate wire:submit="saveSubscription" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Plan</label>
                <select wire:model.live="plan" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
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
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Commandes par mois (vide = illimité)</label>
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
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Commandes ajoutées ce mois-ci</label>
                <input type="number" min="0" wire:model="extraOrders" placeholder="0" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('extraOrders') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <div class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Options en plus de la formule</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach (\App\Enums\Feature::cases() as $feature)
                        <label class="flex items-center gap-2.5 text-sm cursor-pointer">
                            <input type="checkbox" wire:model="addons" value="{{ $feature->value }}" class="w-4 h-4 rounded border-(--color-border) accent-(--color-primary)">
                            <span>{{ $feature->label() }} @if ($feature->includedFrom()) <span class="text-xs text-(--color-text-muted)">(inclus dès {{ $feature->includedFrom()->label() }})</span> @endif</span>
                        </label>
                    @endforeach
                </div>
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

    <div class="mt-6 bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden" data-testid="payment-history">
        <div class="px-5 py-3.5 border-b border-(--color-border) flex flex-wrap items-center justify-between gap-3">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Paiements et demandes traitées</span>
            @php $paidTotal = $handledRequests->filter->isPayment()->sum('amount_fcfa'); @endphp
            @if ($paidTotal > 0)
                <span class="text-xs text-(--color-text-secondary)">Total reçu : <span class="font-semibold tabular-nums text-(--color-text-primary)">{{ number_format($paidTotal, 0, ',', ' ') }} F</span></span>
            @endif
        </div>
        @if ($handledRequests->isEmpty())
            <div class="px-5 py-6 text-center text-sm text-(--color-text-muted)">Aucun paiement ni demande traitée pour l'instant.</div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-sm">
                <tbody>
                    @foreach ($handledRequests as $handled)
                        <tr class="border-t border-(--color-border) first:border-t-0" wire:key="handled-{{ $handled->id }}">
                            <td class="px-5 py-3 whitespace-nowrap text-(--color-text-secondary) tabular-nums">{{ ($handled->handled_at ?? $handled->updated_at)->format('d/m/Y') }}</td>
                            <td class="px-5 py-3">
                                {{ $handled->label() }}
                                @if ($handled->decline_reason) <div class="text-xs text-(--color-text-muted)">Motif : {{ $handled->decline_reason }}</div> @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-right tabular-nums font-semibold">{{ $handled->isPayment() ? number_format($handled->amount_fcfa, 0, ',', ' ').' F' : '—' }}</td>
                            <td class="px-5 py-3 whitespace-nowrap text-xs font-medium {{ $handled->status === \App\Models\SubscriptionRequest::DONE ? 'text-(--color-success-text)' : 'text-(--color-error)' }}">
                                {{ $handled->status === \App\Models\SubscriptionRequest::DONE ? 'Payé, activé' : 'Refusé' }}
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-xs text-(--color-text-muted)">{{ $handled->handler?->name ?? ($handled->paid_online_at ? 'Payé en ligne' : '') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="mt-6 border border-(--color-error)/30 rounded-xl p-5" x-data="{ open: false }">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-xl">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-error) mb-1">Zone de danger</div>
                <p class="text-sm text-(--color-text-secondary)">
                    Supprimer définitivement ce pressing efface aussi ses {{ $pressing->orders_count }} commande(s), ses {{ $pressing->services_count }} service(s),
                    son abonnement et le rattachement de ses {{ $pressing->customers_count }} client(s). Les comptes clients et ceux de l'équipe sont conservés.
                    Pour simplement bloquer l'accès, préférez « Suspendre ».
                </p>
            </div>
            <button type="button" @click="open = !open" class="h-9 px-3.5 rounded-lg border border-(--color-error)/30 text-sm font-semibold text-(--color-error) hover:bg-(--color-error-tint)">Supprimer le pressing</button>
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
