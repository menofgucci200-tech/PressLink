<div class="max-w-5xl">
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Services</h1>
            <p class="text-sm text-(--color-text-muted)">Créez un service et rattachez-le aux pressings de votre choix. Chaque pressing fixe ensuite ses prix par pièce.</p>
        </div>
        @unless ($showForm)
            <button type="button" wire:click="newService" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">+ Nouveau service</button>
        @endunless
    </div>

    @if ($showForm)
        <form novalidate wire:submit="save" class="bg-(--color-surface) border border-(--color-primary)/40 rounded-xl p-5 mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-4 mb-5">
                <div>
                    <label for="service-name" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom du service</label>
                    <input id="service-name" type="text" wire:model.live.debounce.400ms="name" placeholder="Ex. Teinture, Express, Cordonnerie" class="w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="service-icon" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Icône</label>
                    <div class="flex items-center gap-2">
                        {{ \App\Support\ServiceIcons::svg('service', $icon, 40) }}
                        <select id="service-icon" wire:model.live="icon" class="h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
                            @foreach ($icons as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-3 text-xs font-medium text-(--color-text-secondary)">Rattacher à</div>
            <div class="flex flex-wrap gap-4 mb-3">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="radio" wire:model.live="target" value="some" class="accent-(--color-primary)"> Des pressings choisis
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="radio" wire:model.live="target" value="all" class="accent-(--color-primary)"> Tous les pressings ({{ $pressings->count() }})
                </label>
            </div>
            @if ($target === 'some')
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto p-3 rounded-lg border border-(--color-border)">
                    @foreach ($pressings as $p)
                        <label class="flex items-center gap-2.5 text-sm cursor-pointer" wire:key="target-{{ $p->id }}">
                            <input type="checkbox" wire:model="pressingIds" value="{{ $p->id }}" class="w-4 h-4 rounded border-(--color-border) accent-(--color-primary)">
                            <span class="truncate">{{ $p->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('pressingIds') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            @endif

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" wire:click="cancel" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold">Annuler</button>
                <button type="submit" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Rattacher le service</button>
            </div>
        </form>
    @endif

    <div class="flex items-center justify-between gap-3 mb-3">
        <h2 class="font-display text-lg font-bold">Services des pressings</h2>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher un service" aria-label="Rechercher un service"
               class="w-56 h-9 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
    </div>
    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl divide-y divide-(--color-border)">
        @forelse ($catalog as $service)
            <div class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="service-{{ md5($service['name']) }}">
                {{ \App\Support\ServiceIcons::svg('service', $service['icon'], 32) }}
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-sm">{{ $service['name'] }}</div>
                    <div class="text-xs text-(--color-text-muted) truncate" title="{{ $service['pressings']->implode(', ') }}">
                        {{ $service['pressings']->count() }} / {{ $pressings->count() }} pressing(s) · {{ $service['pressings']->take(4)->implode(', ') }}{{ $service['pressings']->count() > 4 ? '…' : '' }}
                    </div>
                </div>
                @if ($service['pressings']->count() < $pressings->count())
                    <button type="button" wire:click="attachExisting(@js($service['name']))" class="text-xs font-semibold text-(--color-primary) hover:underline">Rattacher à d'autres pressings</button>
                @endif
            </div>
        @empty
            <div class="p-5 text-sm text-(--color-text-muted)">Aucun service.</div>
        @endforelse
    </div>
</div>
