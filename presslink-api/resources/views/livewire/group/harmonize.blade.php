@php $card = 'bg-(--color-surface) border border-(--color-border) rounded-xl p-5'; @endphp
<div class="max-w-5xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Harmoniser le groupe</h1>
        <p class="text-sm text-(--color-text-muted)">Définissez vos tarifs et vos paramètres dans un pressing modèle, puis recopiez-les dans les autres en un clic.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
        {{-- Tarifs --}}
        <form wire:submit="syncCatalog" class="{{ $card }} flex flex-col gap-4">
            <div>
                <h2 class="font-semibold">Tarifs</h2>
                <p class="text-xs text-(--color-text-muted) mt-0.5">Ajoute les services manquants et aligne les prix des services de même nom. Rien n'est supprimé ; un prix peut ensuite être ajusté localement.</p>
            </div>
            <div>
                <label for="catalog-source" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Pressing modèle</label>
                <select id="catalog-source" wire:model.live="catalogSource" class="w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                    @foreach ($pressings as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-(--color-text-muted) mt-1">{{ $sourceServicesCount }} service(s) actif(s) à recopier.</p>
            </div>
            <fieldset>
                <legend class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Pressings à mettre à jour</legend>
                <div class="flex flex-col gap-1.5">
                    @foreach ($pressings as $p)
                        @continue($catalogSourcePressing && $p->id === $catalogSourcePressing->id)
                        @php $pv = $preview[$p->id] ?? null; @endphp
                        <label wire:key="ct-{{ $p->id }}" class="flex items-center gap-3 rounded-lg border border-(--color-border) px-3 py-2.5 hover:border-(--color-text-muted)">
                            <input type="checkbox" wire:model.live="catalogTargets" value="{{ $p->id }}" class="w-4 h-4 accent-(--color-primary)">
                            <span class="flex-1 text-sm font-medium">{{ $p->name }}</span>
                            @if ($pv)
                                <span class="text-xs {{ ($pv['created'] + $pv['updated']) > 0 ? 'text-(--color-warning-text)' : 'text-(--color-success-text)' }}">
                                    {{ ($pv['created'] + $pv['updated']) === 0 ? 'Déjà à jour' : $pv['created'].' à ajouter · '.$pv['updated'].' à mettre à jour' }}
                                </span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @error('catalogTargets') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </fieldset>
            <button type="submit" wire:loading.attr="disabled" wire:target="syncCatalog"
                    data-confirm-title="Harmoniser les tarifs" data-confirm-label="Harmoniser"
                    wire:confirm="Recopier les tarifs du pressing modèle dans les pressings cochés ? Les prix des services de même nom seront remplacés."
                    class="h-10 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">Harmoniser les tarifs</button>
        </form>

        {{-- Paramètres --}}
        <form wire:submit="syncSettings" class="{{ $card }} flex flex-col gap-4">
            <div>
                <h2 class="font-semibold">Paramètres communs</h2>
                <p class="text-xs text-(--color-text-muted) mt-0.5">Le nom, l'adresse et le téléphone restent propres à chaque pressing.</p>
            </div>
            <div>
                <label for="settings-source" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Pressing modèle</label>
                <select id="settings-source" wire:model.live="settingsSource" class="w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm">
                    @foreach ($pressings as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <fieldset>
                <legend class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">À recopier</legend>
                <div class="grid grid-cols-2 gap-1.5">
                    @foreach ($settings as $key => $label)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="settingsFields" value="{{ $key }}" class="w-4 h-4 accent-(--color-primary)"> {{ $label }}</label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Pressings à mettre à jour</legend>
                <div class="flex flex-col gap-1.5">
                    @foreach ($pressings as $p)
                        @continue((string) $p->id === $settingsSource)
                        <label wire:key="st-{{ $p->id }}" class="flex items-center gap-3 rounded-lg border border-(--color-border) px-3 py-2.5 hover:border-(--color-text-muted)">
                            <input type="checkbox" wire:model="settingsTargets" value="{{ $p->id }}" class="w-4 h-4 accent-(--color-primary)">
                            <span class="text-sm font-medium">{{ $p->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('settingsTargets') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </fieldset>
            <button type="submit" wire:loading.attr="disabled" wire:target="syncSettings"
                    data-confirm-title="Harmoniser les paramètres" data-confirm-label="Recopier"
                    wire:confirm="Recopier les paramètres cochés dans les pressings sélectionnés ? Leurs valeurs actuelles seront remplacées."
                    class="h-10 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">Recopier les paramètres</button>
        </form>
    </div>
</div>
