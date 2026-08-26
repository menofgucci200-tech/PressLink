<div>
    <div class="flex items-start justify-between gap-6 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Administrateurs</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $administrators->total() }} administrateur(s) de pressing sur la plateforme.</p>
        </div>
        <button wire:click="$toggle('showCreateForm')" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Nouvel administrateur
        </button>
    </div>

    @if ($createdAdmin && $generatedPassword)
        <div class="mb-6 p-4 rounded-lg bg-(--color-success-tint) text-(--color-success-text) text-sm">
            <p class="font-semibold mb-1">Administrateur "{{ $createdAdmin->name }}" créé avec succès.</p>
            <p>Mot de passe temporaire (à transmettre vous-même, il ne sera plus affiché) :
                <span class="font-mono font-semibold tabular-nums">{{ $generatedPassword }}</span>
            </p>
        </div>
    @endif

    @if ($pressingsWithoutAdmin->isNotEmpty() && ! $showCreateForm)
        <div class="mb-6 p-4 rounded-lg bg-(--color-warning-tint) text-(--color-warning-text) text-sm">
            <p class="font-semibold mb-1">{{ $pressingsWithoutAdmin->count() }} pressing(s) sans administrateur :</p>
            <ul class="flex flex-wrap gap-x-4 gap-y-1">
                @foreach ($pressingsWithoutAdmin as $p)
                    <li>
                        <button wire:click="selectPressingForNewAdmin({{ $p->id }})" class="underline font-medium">
                            {{ $p->name }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($showCreateForm)
        <form id="admin-create-form" wire:submit="createAdministrator" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-5 grid grid-cols-2 gap-4 items-start">
            <div class="col-span-2">
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Pressing</label>
                <select wire:model="pressingId" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    <option value="">Sélectionner un pressing…</option>
                    @foreach ($pressings as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                @error('pressingId') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom complet</label>
                <input type="text" wire:model="name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
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
            <div class="col-span-2 flex items-center gap-2 justify-end">
                <button type="button" wire:click="$set('showCreateForm', false)" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium">Annuler</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="createAdministrator"
                        class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold disabled:opacity-60">
                    <span wire:loading.remove wire:target="createAdministrator">Créer</span>
                    <span wire:loading wire:target="createAdministrator">Création…</span>
                </button>
            </div>
        </form>
    @endif

    <div class="relative max-w-sm mb-5">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Nom, email ou téléphone…"
               class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-white">
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($administrators->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun administrateur trouvé.</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Nom</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Contact</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Pressing(s)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($administrators as $admin)
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg)">
                            <td class="px-5 py-3.5 font-medium">{{ $admin->name }}</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary)">
                                <div>{{ $admin->email }}</div>
                                <div class="text-xs tabular-nums">{{ $admin->phone }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                @foreach ($admin->pressings as $p)
                                    <a href="{{ route('admin.pressings.show', $p) }}" class="inline-block text-(--color-primary) hover:underline mr-2">{{ $p->name }}</a>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if (method_exists($administrators, 'links'))
        <div class="mt-5">{{ $administrators->links() }}</div>
    @endif
</div>
