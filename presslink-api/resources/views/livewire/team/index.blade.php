<div>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Équipe</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $members->count() }} membre(s) · chacun voit et fait ce que permet son rôle.</p>
        </div>
        @if ($canManageTeam)
            <button wire:click="$toggle('showCreateForm')" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Ajouter un membre
            </button>
        @endif
    </div>

    @if ($errorMessage)
        <div class="mb-5 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
    @endif

    @if ($createdMember)
        <div class="mb-5 p-4 rounded-lg bg-(--color-success-tint) text-(--color-success-text) text-sm">
            <p class="font-semibold mb-1">Compte « {{ $createdMember->name }} » créé.</p>
            <p>Identifiant de connexion : <span class="font-mono font-semibold">{{ $createdMember->login }}</span> — communiquez-le avec le mot de passe que vous venez de définir.</p>
        </div>
    @endif

    @if ($showCreateForm && $canManageTeam)
        <form novalidate wire:submit="inviteEmployee" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-5 grid grid-cols-1 sm:grid-cols-2 gap-4 items-start animate-step">
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom et prénom</label>
                <input type="text" wire:model="name" placeholder="Ex. Koffi Yao" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Rôle</label>
                <select wire:model="roleId" class="w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
                    @foreach ($assignableRoles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                @error('roleId') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Identifiant de connexion</label>
                <input type="text" wire:model="login" placeholder="Ex. koffi.yao" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('login') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Mot de passe (8 caractères au moins)</label>
                <input type="text" wire:model="password" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('password') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone (facultatif)</label>
                <x-phone-input wire:model="phone" />
                @error('phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2 flex gap-2 justify-end">
                <button type="button" wire:click="$set('showCreateForm', false)" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium">Annuler</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="inviteEmployee"
                        class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold disabled:opacity-60">
                    <span wire:loading.remove wire:target="inviteEmployee">Créer le compte</span>
                    <span wire:loading wire:target="inviteEmployee">Création…</span>
                </button>
            </div>
        </form>
    @endif

    {{-- Membres --}}
    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden mb-8">
        @if ($members->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun membre pour le moment.</div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[680px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Membre</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Contact</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Rôle</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Statut</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($members as $member)
                        @php
                            $memberRole = $memberRoles[$member->id];
                            $canManage = $manageable[$member->id] && $canManageTeam;
                        @endphp
                        <tr wire:key="member-{{ $member->id }}" class="border-t border-(--color-border) hover:bg-(--color-bg)">
                            <td class="px-5 py-3.5 font-medium">
                                <div class="flex items-center gap-3">
                                    <x-user-avatar :user="$member" class="w-8 h-8 text-[11px] rounded-full" />
                                    <span>
                                        {{ $member->name }}
                                        @if ($member->id === auth()->id())
                                            <span class="text-(--color-text-muted) font-normal">(vous)</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">
                                <div class="font-mono">{{ $member->login }}</div>
                                <div class="tabular-nums text-xs">{{ \App\Support\PhoneNumber::format($member->phone) }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($canManage)
                                    <select wire:change="changeRole({{ $member->id }}, $event.target.value)" aria-label="Rôle de {{ $member->name }}"
                                            class="h-9 pl-2.5 pr-8 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                                        @foreach ($assignableRoles as $role)
                                            <option value="{{ $role->id }}" @selected($memberRole?->id === $role->id)>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="inline-flex items-center gap-1.5 font-medium">
                                        @if ($memberRole?->isOwner())
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--color-warning)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7Z"/><path d="M5 20h14"/></svg>
                                        @endif
                                        {{ $memberRole?->name ?? '—' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="text-xs font-medium {{ $member->pivot->is_active ? 'text-(--color-success-text)' : 'text-(--color-text-muted)' }}">
                                    {{ $member->pivot->is_active ? 'Actif' : 'Retiré' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                @if ($canManage)
                                    @if ($member->pivot->is_active)
                                        <button wire:click="resetMemberPassword({{ $member->id }})"
                                                wire:loading.attr="disabled" wire:target="resetMemberPassword({{ $member->id }})"
                                                data-confirm-title="Réinitialiser le mot de passe" data-confirm-label="Réinitialiser" data-confirm-tone="warning"
                                                wire:confirm="Le mot de passe de {{ $member->name }} sera réinitialisé à {{ \App\Actions\Auth\ResetPasswordToDefault::DEFAULT_PASSWORD }} et ses sessions ouvertes seront fermées. Après la réinitialisation, il doit changer son mot de passe pour sécuriser son compte."
                                                class="mr-4 text-xs font-medium text-(--color-text-secondary) hover:text-(--color-primary) disabled:opacity-60">
                                            Réinitialiser le mot de passe
                                        </button>
                                    @endif
                                    <button wire:click="toggleActive({{ $member->id }})"
                                            data-confirm-title="{{ $member->pivot->is_active ? 'Retirer de l\'équipe' : 'Réactiver le membre' }}"
                                            data-confirm-label="{{ $member->pivot->is_active ? 'Retirer' : 'Réactiver' }}"
                                            data-confirm-tone="{{ $member->pivot->is_active ? 'danger' : 'primary' }}"
                                            wire:confirm="{{ $member->pivot->is_active ? 'Retirer '.$member->name.' de l\'équipe ? Il ne pourra plus se connecter à l\'espace du pressing. Vous pourrez le réactiver plus tard.' : 'Réactiver '.$member->name.' ? Il retrouvera l\'accès à l\'espace du pressing avec ses identifiants habituels.' }}"
                                            wire:loading.attr="disabled" wire:target="toggleActive({{ $member->id }})"
                                            class="text-xs font-medium text-(--color-primary) disabled:opacity-60">
                                        {{ $member->pivot->is_active ? 'Retirer' : 'Réactiver' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    {{-- Rôles et accès --}}
    @if ($canManageRoles)
        <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
            <div>
                <h2 class="font-display text-lg font-bold">Rôles et accès</h2>
                <p class="text-sm text-(--color-text-muted)">Cochez ce que chaque rôle peut voir et faire. Le propriétaire a toujours tous les accès.</p>
            </div>
            <button type="button" wire:click="newRole" class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
                + Nouveau rôle
            </button>
        </div>

        @if ($showRoleForm)
            <form wire:submit="saveRole" class="bg-(--color-surface) border-2 border-(--color-primary) rounded-xl p-5 mb-4 animate-step">
                <div class="flex flex-wrap items-end gap-3 mb-4">
                    <div class="flex-1 min-w-56">
                        <label for="role-name" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom du rôle</label>
                        <input id="role-name" type="text" wire:model="roleName" placeholder="Ex. Caissière, Repasseur" maxlength="60"
                               class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                        @error('roleName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
                    @foreach ($permissionGroups as $groupLabel => $permissions)
                        <fieldset>
                            <legend class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">{{ $groupLabel }}</legend>
                            <div class="flex flex-col gap-1.5">
                                @foreach ($permissions as $permission)
                                    @php $grantableHere = in_array($permission->value, $grantable, true); @endphp
                                    <label class="flex items-start gap-2.5 text-sm {{ $grantableHere ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed' }}">
                                        <input type="checkbox" wire:model="rolePermissions" value="{{ $permission->value }}" @disabled(! $grantableHere)
                                               class="mt-0.5 w-4 h-4 rounded border-(--color-border) accent-(--color-primary)">
                                        <span>
                                            {{ $permission->label() }}
                                            @if ($permission->isSensitive())
                                                <span class="ml-1 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-warning-tint) text-(--color-warning-text)">Revenus</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" wire:click="$set('showRoleForm', false)" class="h-10 px-4 rounded-lg text-sm font-medium text-(--color-text-secondary) hover:underline">Annuler</button>
                    <button type="submit" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Enregistrer le rôle</button>
                </div>
            </form>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @foreach ($roles as $role)
                @php $values = $role->permissionValues(); @endphp
                <div wire:key="role-{{ $role->id }}" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 flex flex-col">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <div>
                            <div class="font-semibold">{{ $role->name }}</div>
                            <div class="text-xs text-(--color-text-muted)">{{ $role->memberships_count }} membre(s) · {{ $role->isOwner() ? 'tous les accès' : count($values).' accès sur '.count(\App\Enums\Permission::cases()) }}</div>
                        </div>
                        @if ($role->isOwner())
                            <span class="text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-bg) text-(--color-text-muted)">Verrouillé</span>
                        @endif
                    </div>
                    <ul class="mt-2 flex flex-col gap-1 text-[13px] flex-1">
                        @foreach (\App\Enums\Permission::cases() as $permission)
                            @php $on = in_array($permission->value, $values, true); @endphp
                            <li class="flex items-start gap-2 {{ $on ? '' : 'text-(--color-text-muted)' }}">
                                <span class="flex-none mt-0.5 {{ $on ? 'text-(--color-success)' : 'text-(--color-text-muted)' }}">{{ $on ? '✓' : '—' }}</span>
                                <span class="{{ $on ? '' : 'line-through decoration-(--color-border)' }}">{{ $permission->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @unless ($role->isOwner())
                        <div class="flex items-center gap-4 mt-4 pt-3 border-t border-(--color-border) text-sm">
                            <button type="button" wire:click="editRole({{ $role->id }})" class="font-semibold text-(--color-primary) hover:underline">Modifier les accès</button>
                            @if ($role->key === null)
                                <button type="button" wire:click="deleteRole({{ $role->id }})"
                                        data-confirm-title="Supprimer le rôle" data-confirm-label="Supprimer" data-confirm-tone="danger"
                                        wire:confirm="Supprimer le rôle « {{ $role->name }} » ?"
                                        class="text-(--color-text-muted) hover:text-(--color-error) hover:underline">Supprimer</button>
                            @endif
                        </div>
                    @endunless
                </div>
            @endforeach
        </div>
    @endif
</div>
