<div class="max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.pressings.index') }}" class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center hover:border-(--color-primary)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
        </a>
        <h1 class="font-display text-2xl font-bold">Nouveau pressing</h1>
    </div>

    @if ($createdPressing)
        <div class="mb-6 p-4 rounded-lg bg-(--color-success-tint) text-(--color-success-text) text-sm">
            <p class="font-semibold mb-1">Pressing "{{ $createdPressing->name }}" créé avec succès.</p>
            <p class="mb-1">Code pressing : <span class="font-mono font-semibold">{{ $createdPressing->code }}</span></p>
            <p>Ce pressing n'a pas encore d'administrateur — assignez-lui-en un depuis le menu Administrateurs.</p>
            <a href="{{ route('admin.administrators.index', ['pressing' => $createdPressing->id]) }}" class="inline-block mt-2 font-medium underline">Créer son administrateur →</a>
        </div>
    @endif

    @if ($createdPressings && $generatedPassword)
        <div class="mb-6 p-4 rounded-lg bg-(--color-success-tint) text-(--color-success-text) text-sm">
            <p class="font-semibold mb-1">Groupe de {{ $createdPressings->count() }} pressings créé avec succès.</p>
            <ul class="mb-2 list-disc list-inside">
                @foreach ($createdPressings as $p)
                    <li>{{ $p->name }} — code <span class="font-mono font-semibold">{{ $p->code }}</span></li>
                @endforeach
            </ul>
            <p>Mot de passe temporaire du propriétaire (administrateur des {{ $createdPressings->count() }} pressings, à transmettre vous-même, il ne sera plus affiché) :
                <span class="font-mono font-semibold tabular-nums">{{ $generatedPassword }}</span>
            </p>
        </div>
    @endif

    <div class="flex gap-2 mb-6">
        <button wire:click="$set('type', 'standard')"
                class="flex-1 text-left p-4 rounded-xl border-2 {{ $type === 'standard' ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border) bg-(--color-surface)' }}">
            <div class="font-semibold text-sm mb-1">Pressing standard</div>
            <div class="text-xs text-(--color-text-muted)">Un seul établissement, avec son propre administrateur assigné séparément.</div>
        </button>
        <button wire:click="$set('type', 'multi')"
                class="flex-1 text-left p-4 rounded-xl border-2 {{ $type === 'multi' ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border) bg-(--color-surface)' }}">
            <div class="font-semibold text-sm mb-1">Groupe de pressings (mutualisé)</div>
            <div class="text-xs text-(--color-text-muted)">Plusieurs établissements pour un même propriétaire, qui obtient le dashboard mutualisé.</div>
        </button>
    </div>

    @if ($type === 'standard')
        <form wire:submit="create" class="flex flex-col gap-6">
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 grid grid-cols-2 gap-4">
                <div class="col-span-2 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Pressing</div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom</label>
                    <input type="text" wire:model="name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Code pressing (facultatif — généré si vide)</label>
                    <input type="text" wire:model="code" placeholder="PE-4821" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary) uppercase">
                    @error('code') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone</label>
                    <input type="text" wire:model="phone" placeholder="+2250708124400" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Email</label>
                    <input type="email" wire:model="email" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('email') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Ville</label>
                    <input type="text" wire:model="city" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('city') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Adresse</label>
                    <input type="text" wire:model="address" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('address') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <p class="col-span-2 text-xs text-(--color-text-muted)">
                    Un abonnement d'essai de 14 jours (plan Starter) est créé automatiquement. L'administrateur se crée ensuite depuis le menu Administrateurs.
                </p>
            </div>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="create"
                        class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                    <span wire:loading.remove wire:target="create">Créer le pressing</span>
                    <span wire:loading wire:target="create">Création…</span>
                </button>
            </div>
        </form>
    @else
        <form wire:submit="createGroup" class="flex flex-col gap-6">
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 grid grid-cols-2 gap-4">
                <div class="col-span-2 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Propriétaire du groupe</div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom complet</label>
                    <input type="text" wire:model="ownerName" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('ownerName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Email</label>
                    <input type="email" wire:model="ownerEmail" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('ownerEmail') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone</label>
                    <input type="text" wire:model="ownerPhone" placeholder="+2250708124400" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('ownerPhone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <p class="col-span-2 text-xs text-(--color-text-muted)">
                    Ce compte sera administrateur de chacun des pressings ci-dessous, et obtiendra automatiquement le dashboard mutualisé. Des sous-administrateurs par pressing peuvent être ajoutés ensuite depuis le menu Administrateurs.
                </p>
            </div>

            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Pressings du groupe</div>
                    <button type="button" wire:click="addPressingRow" class="text-xs font-medium text-(--color-primary) hover:underline">
                        + Ajouter un pressing
                    </button>
                </div>

                @error('pressingRows') <p class="text-xs text-(--color-error) mb-3">{{ $message }}</p> @enderror

                <div class="flex flex-col gap-3">
                    @foreach ($pressingRows as $index => $row)
                        <div class="grid grid-cols-12 gap-2 items-start p-3 rounded-lg bg-(--color-bg)">
                            <div class="col-span-4">
                                <input type="text" wire:model="pressingRows.{{ $index }}.name" placeholder="Nom du pressing"
                                    class="w-full h-9 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                                @error("pressingRows.{$index}.name") <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-2">
                                <input type="text" wire:model="pressingRows.{{ $index }}.code" placeholder="Code"
                                    class="w-full h-9 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary) uppercase">
                                @error("pressingRows.{$index}.code") <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-3">
                                <input type="text" wire:model="pressingRows.{{ $index }}.phone" placeholder="+2250708124400"
                                    class="w-full h-9 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                                @error("pressingRows.{$index}.phone") <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-2">
                                <input type="text" wire:model="pressingRows.{{ $index }}.city" placeholder="Ville"
                                    class="w-full h-9 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                            </div>
                            <div class="col-span-1 flex justify-end">
                                <button type="button" wire:click="removePressingRow({{ $index }})"
                                    class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-muted) hover:border-(--color-error) hover:text-(--color-error)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path></svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="text-xs text-(--color-text-muted) mt-3">
                    Un abonnement d'essai de 14 jours (plan Starter) est créé automatiquement pour chaque pressing.
                </p>
            </div>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="createGroup"
                        class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                    <span wire:loading.remove wire:target="createGroup">Créer le groupe</span>
                    <span wire:loading wire:target="createGroup">Création…</span>
                </button>
            </div>
        </form>
    @endif
</div>
