@php
    $th = 'px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)';
    $field = 'h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $days = \App\Livewire\Admin\Clients\Index::ACTIVE_WITHIN_DAYS;
    $cards = [
        ['label' => 'Clients inscrits', 'value' => $summary['total'], 'sub' => null, 'filter' => ''],
        ['label' => 'Nouveaux', 'value' => $summary['new'], 'sub' => 'sur '.$days.' jours', 'filter' => null],
        ['label' => 'Actifs', 'value' => $summary['active'], 'sub' => 'une commande sur '.$days.' jours', 'filter' => 'active'],
        ['label' => 'Sans commande', 'value' => $summary['never'], 'sub' => 'inscrits, jamais servis', 'filter' => 'never'],
    ];
@endphp

<div>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Clients</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $fcfa($customers->total()) }} client(s){{ $hasFilters ? ' pour ces filtres' : ' sur la plateforme' }}.</p>
        </div>
        <div class="flex items-center gap-2">
        <a href="{{ route('admin.export', ['type' => 'clients', ...array_filter($this->filters())]) }}"
               class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Exporter
            </a>
            <button type="button" wire:click="openCreateForm" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nouveau client
            </button>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach ($cards as $card)
            @php $clickable = $card['filter'] !== null; $isOn = $clickable && $card['filter'] !== '' && $activity === $card['filter']; @endphp
            <button type="button" @disabled(! $clickable)
                @if ($clickable) wire:click="$set('activity', '{{ $isOn ? '' : $card['filter'] }}')" @endif
                class="text-left rounded-xl border p-4 {{ $isOn ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border) bg-(--color-surface)' }} {{ $clickable ? 'hover:border-(--color-text-muted) cursor-pointer' : 'cursor-default' }}">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">{{ $card['label'] }}</div>
                <div class="text-2xl font-bold tabular-nums mt-1">{{ $fcfa($card['value']) }}</div>
                @if ($card['sub'])
                    <div class="text-xs text-(--color-text-muted) mt-0.5">{{ $card['sub'] }}</div>
                @endif
            </button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <div class="relative w-full sm:w-auto sm:flex-1 sm:max-w-xs">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Nom, téléphone ou email…"
                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
        </div>
        <select wire:model.live="pressingId" class="{{ $field }} max-w-[220px]" aria-label="Pressing">
            <option value="">Tous les pressings</option>
            @foreach ($pressings as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="activity" class="{{ $field }}" aria-label="Activité">
            <option value="">Toute activité</option>
            <option value="active">Actifs ({{ $days }} j)</option>
            <option value="inactive">Inactifs depuis {{ $days }} j</option>
            <option value="never">Jamais commandé</option>
            <option value="disabled">Comptes désactivés{{ $summary['disabled'] ? ' ('.$summary['disabled'].')' : '' }}</option>
        </select>
        <select wire:model.live="sort" class="{{ $field }}" aria-label="Trier">
            <option value="recent">Inscription récente</option>
            <option value="name">Nom (A → Z)</option>
            <option value="orders">Plus de commandes</option>
            <option value="spent">Plus gros montant</option>
            <option value="last_order">Dernière commande</option>
        </select>
        @if ($hasFilters)
            <button wire:click="resetFilters" class="text-xs font-medium text-(--color-text-muted) hover:text-(--color-primary) underline">Réinitialiser</button>
        @endif
        <span wire:loading.delay class="text-xs text-(--color-text-muted)">Chargement…</span>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($customers->isEmpty())
            @if ($hasFilters)
                <x-empty-state icon="search" title="Aucun client trouvé pour ces filtres">
                    Modifiez la recherche ou retirez un filtre pour élargir la liste.
                    <x-slot:actions>
                        <button type="button" wire:click="resetFilters" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">Réinitialiser les filtres</button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <x-empty-state icon="users" title="Aucun client pour le moment" />
            @endif
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[900px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="{{ $th }}">Client</th>
                        <th class="{{ $th }}">Téléphone</th>
                        <th class="{{ $th }}">Pressings</th>
                        <th class="{{ $th }} text-right">Commandes</th>
                        <th class="{{ $th }} text-right">Montant</th>
                        <th class="{{ $th }}">Dernière commande</th>
                        <th class="{{ $th }}">Inscrit le</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        @php $lastOrderAt = $customer->last_order_at ? \Illuminate\Support\Carbon::parse($customer->last_order_at) : null; @endphp
                        <tr wire:key="customer-{{ $customer->id }}" wire:click="showCustomer({{ $customer->id }})"
                            class="border-t border-(--color-border) cursor-pointer {{ $selectedCustomerId === $customer->id ? 'bg-(--color-primary-tint)' : 'hover:bg-(--color-bg)' }}">
                            <td class="px-5 py-3.5">
                                <div class="font-medium">
                                    {{ $customer->fullName() ?: 'Sans nom' }}
                                    @unless ($customer->is_active)
                                        <span class="ml-1.5 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-error-tint) text-(--color-error)">Désactivé</span>
                                    @endunless
                                </div>
                                @if ($customer->email)
                                    <div class="text-xs text-(--color-text-muted)">{{ $customer->email }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary) tabular-nums">{{ $customer->phone }}</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">
                                {{ $customer->pressings->take(2)->pluck('name')->implode(', ') ?: '—' }}
                                @if ($customer->pressings_count > 2)
                                    <span class="text-xs text-(--color-text-muted)">+{{ $customer->pressings_count - 2 }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $customer->orders_count }}</td>
                            <td class="px-5 py-3.5 text-right font-semibold tabular-nums">{{ $fcfa((int) $customer->total_spent) }} F</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">
                                @if ($lastOrderAt)
                                    <span title="{{ $lastOrderAt->format('d/m/Y H:i') }}">{{ $lastOrderAt->diffForHumans() }}</span>
                                @else
                                    <span class="text-(--color-text-muted)">Aucune</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-(--color-text-muted)">{{ $customer->created_at->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="mt-5">{{ $customers->links() }}</div>

    {{-- ===== Nouveau client ===== --}}
    @if ($formMode === 'create')
        <div wire:key="customer-create-panel" x-data @keydown.escape.window="$wire.cancelForm()">
            <div class="fixed inset-0 z-40 bg-slate-900/40" wire:click="cancelForm" aria-hidden="true"></div>
            <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-(--color-surface) shadow-2xl flex flex-col animate-step" role="dialog" aria-label="Nouveau client">
                <div class="flex items-center gap-3 px-5 py-4 border-b border-(--color-border)">
                    <div class="flex-1 font-display text-lg font-bold">Nouveau client</div>
                    <button type="button" wire:click="cancelForm" aria-label="Fermer" class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form novalidate wire:submit="saveCustomer" class="flex-1 overflow-y-auto px-5 py-5 grid grid-cols-2 gap-4 content-start text-sm">
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Prénom</label>
                    <input type="text" wire:model="form.first_name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('form.first_name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom</label>
                    <input type="text" wire:model="form.last_name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('form.last_name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone</label>
                    <x-phone-input wire:model="form.phone" />
                    @error('form.phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Email (facultatif)</label>
                    <input type="email" wire:model="form.email" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('form.email') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Rattacher à un pressing (facultatif)</label>
                    <select wire:model="form.pressing_id" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary) bg-(--color-surface)">
                        <option value="">Aucun</option>
                        @foreach ($pressings as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    @error('form.pressing_id') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <p class="col-span-2 text-xs text-(--color-text-muted)">Le compte attend le client : en s'inscrivant dans l'application avec ce numéro, il confirme ses informations, choisit son mot de passe et retrouve ses commandes.</p>
                    <div class="col-span-2 flex justify-end gap-2">
                        <button type="button" wire:click="cancelForm" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium">Annuler</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveCustomer" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">Créer le client</button>
                    </div>
                </form>
            </aside>
        </div>
    @endif

    {{-- ===== Panneau de détail ===== --}}
    @if ($selectedCustomer)
        <div wire:key="customer-panel-{{ $selectedCustomer->id }}" x-data @keydown.escape.window="$wire.closeCustomer()">
            <div class="fixed inset-0 z-40 bg-slate-900/40" wire:click="closeCustomer" aria-hidden="true"></div>
            <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-(--color-surface) shadow-2xl flex flex-col animate-step" role="dialog" aria-label="Fiche client">
                <div class="flex items-start gap-3 px-5 py-4 border-b border-(--color-border)">
                    <div class="w-11 h-11 rounded-full flex-none flex items-center justify-center bg-(--color-primary-tint) text-(--color-primary) font-bold overflow-hidden">
                        @if ($selectedCustomer->photo_url)
                            <img src="{{ $selectedCustomer->photo_url }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ mb_strtoupper(mb_substr($selectedCustomer->first_name, 0, 1).mb_substr($selectedCustomer->last_name, 0, 1)) ?: '?' }}
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-display text-lg font-bold truncate">{{ $selectedCustomer->fullName() ?: 'Sans nom' }}</div>
                        <div class="text-xs text-(--color-text-muted)">
                            Inscrit le {{ $selectedCustomer->created_at->format('d/m/Y') }}
                            @unless ($selectedCustomer->is_active)
                                · <span class="font-semibold text-(--color-error)">Compte désactivé</span>
                            @endunless
                        </div>
                    </div>
                    <button type="button" wire:click="closeCustomer" aria-label="Fermer"
                            class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary)">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-5 space-y-6 text-sm">

                    @if ($formMode === 'edit')
                        <form novalidate wire:submit="saveCustomer" class="grid grid-cols-2 gap-4 rounded-lg border border-(--color-border) p-4">
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Prénom</label>
                    <input type="text" wire:model="form.first_name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('form.first_name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom</label>
                    <input type="text" wire:model="form.last_name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('form.last_name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone</label>
                    <x-phone-input wire:model="form.phone" />
                    @error('form.phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Email (facultatif)</label>
                    <input type="email" wire:model="form.email" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('form.email') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                        <div class="col-span-2 flex justify-end gap-2">
                                <button type="button" wire:click="cancelForm" class="h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-medium">Annuler</button>
                                <button type="submit" wire:loading.attr="disabled" wire:target="saveCustomer" class="h-9 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">Enregistrer</button>
                            </div>
                        </form>
                    @else
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="editCustomer" class="h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-primary) hover:text-(--color-primary)">Modifier</button>
                            <button type="button" wire:click="resetPassword" data-confirm-title="Réinitialiser le mot de passe" data-confirm-label="Réinitialiser" data-confirm-tone="warning" wire:confirm="Le mot de passe de ce client sera réinitialisé à {{ \App\Actions\Auth\ResetPasswordToDefault::DEFAULT_PASSWORD }} et il sera déconnecté de l'application. Après la réinitialisation, il doit changer son mot de passe pour sécuriser son compte."
                                    class="h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-primary) hover:text-(--color-primary)">Réinitialiser le mot de passe</button>
                            <button type="button" wire:click="toggleActive"
                                    data-confirm-title="{{ $selectedCustomer->is_active ? 'Désactiver le compte' : 'Réactiver le compte' }}" data-confirm-label="{{ $selectedCustomer->is_active ? 'Désactiver' : 'Réactiver' }}" data-confirm-tone="{{ $selectedCustomer->is_active ? 'warning' : 'primary' }}"
                                    wire:confirm="{{ $selectedCustomer->is_active ? 'Désactiver ce compte ? Le client sera déconnecté et ne pourra plus se connecter à l\'application, ni recevoir de notifications. Ses commandes sont conservées.' : 'Réactiver ce compte ? Le client pourra de nouveau se connecter.' }}"
                                    class="h-9 px-3.5 rounded-lg border text-sm font-medium {{ $selectedCustomer->is_active ? 'border-(--color-warning)/40 text-(--color-warning-text) hover:bg-(--color-warning-tint)' : 'border-(--color-success)/30 text-(--color-success-text) hover:bg-(--color-success-tint)' }}">
                                {{ $selectedCustomer->is_active ? 'Désactiver' : 'Réactiver' }}
                            </button>
                            <button type="button" wire:click="deleteCustomer"
                                    data-confirm-title="Supprimer le client" data-confirm-label="Supprimer définitivement" data-confirm-tone="danger"
                                    wire:confirm="Supprimer définitivement ce client ? Ses {{ $selectedCustomer->orders_count }} commande(s) et signalements seront aussi supprimés. Cette action est irréversible."
                                    class="h-9 px-3.5 rounded-lg border border-(--color-error)/30 text-sm font-medium text-(--color-error) hover:bg-(--color-error-tint)">Supprimer</button>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-(--color-bg) p-3">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Commandes</div>
                            <div class="text-xl font-bold tabular-nums">{{ $selectedCustomer->orders_count }}</div>
                        </div>
                        <div class="rounded-lg bg-(--color-bg) p-3">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Montant</div>
                            <div class="text-xl font-bold tabular-nums">{{ $fcfa((int) $selectedCustomer->total_spent) }} F</div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex justify-between gap-3"><span class="text-(--color-text-secondary)">Téléphone</span><a href="tel:{{ $selectedCustomer->phone }}" class="font-medium tabular-nums text-(--color-primary) hover:underline">{{ $selectedCustomer->phone }}</a></div>
                        <div class="flex justify-between gap-3"><span class="text-(--color-text-secondary)">Email</span><span class="font-medium truncate">{{ $selectedCustomer->email ?? '—' }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-(--color-text-secondary)">Dernière connexion</span><span class="font-medium">{{ $selectedCustomer->last_login_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-(--color-text-secondary)">Notifications</span><span class="font-medium">{{ $selectedCustomer->onesignal_player_id || $selectedCustomer->fcm_token ? 'Activées' : 'Non activées' }}</span></div>
                    </div>

                    <div>
                        <h3 class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Pressings rejoints</h3>
                        <div class="rounded-lg border border-(--color-border) divide-y divide-(--color-border)">
                            @forelse ($selectedCustomer->pressings as $p)
                                <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                                    <a href="{{ route('admin.pressings.show', $p) }}" wire:navigate class="font-medium text-(--color-primary) hover:underline truncate">{{ $p->name }}</a>
                                    <div class="flex items-center gap-3 flex-none">
                                        <span class="text-xs text-(--color-text-muted)">depuis le {{ \Illuminate\Support\Carbon::parse($p->pivot->joined_at ?? $p->pivot->created_at)->format('d/m/Y') }}</span>
                                        <button type="button" wire:click="detachPressing({{ $p->id }})" data-confirm-title="Retirer du pressing" data-confirm-label="Retirer" data-confirm-tone="warning" wire:confirm="Retirer ce client de {{ $p->name }} ? Ses commandes existantes sont conservées."
                                                class="text-xs font-medium text-(--color-error) hover:underline">Retirer</button>
                                    </div>
                                </div>
                            @empty
                                <div class="px-3 py-4 text-center text-(--color-text-muted)">Aucun pressing rejoint.</div>
                            @endforelse
                        </div>
                        <form novalidate wire:submit="attachPressing" class="mt-2 flex gap-2">
                            <select wire:model="attachPressingId" class="flex-1 min-w-0 h-9 px-2.5 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)" aria-label="Rattacher à un pressing">
                                <option value="">Rattacher à un pressing…</option>
                                @foreach ($pressings->whereNotIn('id', $selectedCustomer->pressings->pluck('id')) as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-primary) hover:text-(--color-primary)">Ajouter</button>
                        </form>
                        @error('attachPressingId') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <div class="flex items-baseline justify-between mb-2">
                            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Dernières commandes</h3>
                            @if ($selectedCustomer->orders_count > 0)
                                <a href="{{ route('admin.orders.index', ['search' => $selectedCustomer->phone]) }}" wire:navigate class="text-xs font-medium text-(--color-primary) hover:underline">Tout voir →</a>
                            @endif
                        </div>
                        <div class="rounded-lg border border-(--color-border) divide-y divide-(--color-border)">
                            @forelse ($selectedCustomer->orders as $order)
                                <a href="{{ route('admin.orders.index', ['commande' => $order->id, 'search' => $order->order_number]) }}" wire:navigate
                                   class="flex items-center justify-between gap-3 px-3 py-2.5 hover:bg-(--color-bg)">
                                    <div class="min-w-0">
                                        <div class="font-medium tabular-nums">{{ $order->order_number }}</div>
                                        <div class="text-xs text-(--color-text-muted) truncate">{{ $order->pressing->name }} · {{ $order->created_at->format('d/m/Y') }}</div>
                                    </div>
                                    <div class="flex flex-col items-end gap-1 flex-none">
                                        <span class="font-semibold tabular-nums">{{ $fcfa($order->total_fcfa) }} F</span>
                                        <x-status-badge :status="$order->status" class="px-2! py-0.5! text-[10px]!" />
                                    </div>
                                </a>
                            @empty
                                <div class="px-3 py-4 text-center text-(--color-text-muted)">Aucune commande.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    @endif
</div>
