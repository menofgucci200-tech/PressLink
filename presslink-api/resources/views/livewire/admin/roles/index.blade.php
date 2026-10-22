<div class="max-w-5xl">
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Rôles et accès</h1>
            <p class="text-sm text-(--color-text-muted)">Créez des rôles, cochez leurs accès et rattachez-les au pressing de votre choix. Le propriétaire a toujours tous les accès.</p>
        </div>
        <div class="w-full sm:w-72">
            <label for="role-pressing" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Pressing</label>
            <select id="role-pressing" wire:model.live="pressingId" class="w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
                @foreach ($pressings as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($errorMessage)
        <div class="mb-5 p-4 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
    @endif

    @if (! $pressing)
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 text-sm text-(--color-text-muted)">Aucun pressing pour le moment.</div>
    @else
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="font-display text-lg font-bold">Rôles de {{ $pressing->name }}</h2>
            @unless ($showForm)
                <button type="button" wire:click="newRole" class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">+ Nouveau rôle</button>
            @endunless
        </div>

        @if ($showForm)
            <form novalidate wire:submit="save" class="bg-(--color-surface) border border-(--color-primary)/40 rounded-xl p-5 mb-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="role-name" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom du rôle</label>
                        <input id="role-name" type="text" wire:model="roleName" placeholder="Ex. Caissière, Repasseur" class="w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
                        @error('roleName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    @if ($editingRoleId === null)
                        <div class="flex items-end">
                            <label class="flex items-center gap-2.5 text-sm cursor-pointer h-10">
                                <input type="checkbox" wire:model="applyToAll" class="w-4 h-4 rounded border-(--color-border) accent-(--color-primary)">
                                Ajouter ce rôle à tous les pressings
                            </label>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    @foreach ($permissionGroups as $group => $permissions)
                        <fieldset>
                            <legend class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">{{ $group }}</legend>
                            <div class="space-y-1.5">
                                @foreach ($permissions as $permission)
                                    <label class="flex items-start gap-2.5 text-sm cursor-pointer">
                                        <input type="checkbox" wire:model="rolePermissions" value="{{ $permission->value }}" class="mt-0.5 w-4 h-4 rounded border-(--color-border) accent-(--color-primary)">
                                        <span>
                                            {{ $permission->label() }}
                                            @if ($permission->isSensitive()) <span class="text-[11px] text-(--color-warning-text)">· sensible</span> @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" wire:click="cancel" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold">Annuler</button>
                    <button type="submit" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                        {{ $editingRoleId ? 'Enregistrer' : 'Créer le rôle' }}
                    </button>
                </div>
            </form>
        @endif

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden mb-8">
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead class="text-[11px] uppercase tracking-wide text-(--color-text-muted) border-b border-(--color-border)">
                    <tr>
                        <th class="text-left font-semibold px-4 py-3">Rôle</th>
                        <th class="text-left font-semibold px-4 py-3">Accès</th>
                        <th class="text-left font-semibold px-4 py-3">Membres</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-(--color-border)">
                    @foreach ($roles as $role)
                        <tr wire:key="role-{{ $role->id }}">
                            <td class="px-4 py-3 font-medium">{{ $role->name }}</td>
                            <td class="px-4 py-3 text-(--color-text-secondary)">
                                {{ $role->isOwner() ? 'Tous les accès' : count($role->permissionValues()).' accès' }}
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ collect($memberRoleIds)->filter(fn ($id) => (int) $id === $role->id)->count() }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @unless ($role->isOwner())
                                    <button type="button" wire:click="editRole({{ $role->id }})" class="text-xs font-semibold text-(--color-primary) hover:underline">Modifier les accès</button>
                                    @if ($role->key === null)
                                        <button type="button" wire:click="deleteRole({{ $role->id }})" wire:confirm="Supprimer le rôle « {{ $role->name }} » ?" class="ml-3 text-xs font-semibold text-(--color-text-muted) hover:text-(--color-error)">Supprimer</button>
                                    @endif
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </div>

        <h2 class="font-display text-lg font-bold mb-3">Membres de {{ $pressing->name }}</h2>
        @if ($members->isEmpty())
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 text-sm text-(--color-text-muted)">Aucun membre dans ce pressing.</div>
        @else
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl divide-y divide-(--color-border)">
                @foreach ($members as $member)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" wire:key="member-{{ $member->id }}-{{ $memberRoleIds[$member->id] }}-{{ $refusals }}">
                        <div class="min-w-0">
                            <div class="font-medium text-sm">{{ $member->name }}</div>
                            <div class="text-xs text-(--color-text-muted)">{{ $member->login }} @unless ($member->pivot->is_active) · inactif @endunless</div>
                        </div>
                        <select aria-label="Rôle de {{ $member->name }}" wire:change="assignRole({{ $member->id }}, $event.target.value)"
                                class="h-9 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected($memberRoleIds[$member->id] === $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
