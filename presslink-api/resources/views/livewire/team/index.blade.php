<div>
    <div class="flex items-start justify-between gap-6 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Équipe</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $members->count() }} membre(s) de l'équipe.</p>
        </div>
        <button wire:click="$toggle('showCreateForm')" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Ajouter un employé
        </button>
    </div>

    @if ($errorMessage)
        <div class="mb-5 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
    @endif

    @if ($generatedPassword)
        <div class="mb-5 p-4 rounded-lg bg-(--color-success-tint) text-(--color-success-text) text-sm">
            <p class="font-semibold mb-1">Compte créé avec succès.</p>
            <p>Mot de passe temporaire (à transmettre vous-même, il ne sera plus affiché) :
                <span class="font-mono font-semibold tabular-nums">{{ $generatedPassword }}</span>
            </p>
        </div>
    @endif

    @if ($showCreateForm)
        <form wire:submit="inviteEmployee" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-5 grid grid-cols-2 gap-4 items-start">
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom complet</label>
                <input type="text" wire:model="name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Rôle</label>
                <select wire:model="role" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @foreach (\App\Enums\PressingRole::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
                @error('role') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Email</label>
                <input type="email" wire:model="email" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('email') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone</label>
                <input type="text" wire:model="phone" placeholder="+2250708124400" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-2 flex gap-2 justify-end">
                <button type="button" wire:click="$set('showCreateForm', false)" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium">Annuler</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="inviteEmployee"
                        class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold disabled:opacity-60">
                    <span wire:loading.remove wire:target="inviteEmployee">Créer</span>
                    <span wire:loading wire:target="inviteEmployee">Création…</span>
                </button>
            </div>
        </form>
    @endif

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($members->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun membre pour le moment.</div>
        @else
            <table class="w-full text-sm">
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
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg)">
                            <td class="px-5 py-3.5 font-medium">
                                {{ $member->name }}
                                @if ($member->id === auth()->id())
                                    <span class="text-(--color-text-muted) font-normal">(vous)</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">
                                <div>{{ $member->email }}</div>
                                <div class="tabular-nums text-xs">{{ $member->phone }}</div>
                            </td>
                            <td class="px-5 py-3.5">{{ $member->pivot->role->label() }}</td>
                            <td class="px-5 py-3.5">
                                <span class="text-xs font-medium {{ $member->pivot->is_active ? 'text-(--color-success-text)' : 'text-(--color-text-muted)' }}">
                                    {{ $member->pivot->is_active ? 'Actif' : 'Retiré' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                @if ($member->id !== auth()->id())
                                    <button wire:click="toggleActive({{ $member->id }})"
                                            wire:loading.attr="disabled" wire:target="toggleActive({{ $member->id }})"
                                            class="text-xs font-medium text-(--color-primary) disabled:opacity-60">
                                        {{ $member->pivot->is_active ? 'Retirer' : 'Réactiver' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
