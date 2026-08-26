@php
    $dayLabels = [
        'lundi' => 'Lundi',
        'mardi' => 'Mardi',
        'mercredi' => 'Mercredi',
        'jeudi' => 'Jeudi',
        'vendredi' => 'Vendredi',
        'samedi' => 'Samedi',
        'dimanche' => 'Dimanche',
    ];
@endphp
<div class="max-w-3xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Paramètres</h1>
        <p class="text-sm text-(--color-text-muted)">
            Code pressing : <span class="font-mono font-semibold">{{ $pressing->code }}</span>
            <span class="text-(--color-text-muted)">— transmis aux clients pour rejoindre votre établissement.</span>
        </p>
    </div>

    @if ($saved)
        <div class="mb-5 p-3 rounded-lg bg-(--color-success-tint) text-(--color-success-text) text-sm">
            Paramètres enregistrés.
        </div>
    @endif

    <form wire:submit="save" class="flex flex-col gap-6">
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-4">Logo</div>
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-lg bg-(--color-bg) border border-(--color-border) flex items-center justify-center overflow-hidden">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="Aperçu du logo" class="w-full h-full object-cover">
                    @elseif ($pressing->logo_url)
                        <img src="{{ $pressing->logo_url }}" alt="Logo actuel" class="w-full h-full object-cover">
                    @else
                        <span class="text-xs text-(--color-text-muted)">Aucun</span>
                    @endif
                </div>
                <div class="flex flex-col gap-1.5">
                    <input type="file" wire:model="logo" accept="image/*" class="text-sm">
                    @error('logo') <p class="text-xs text-(--color-error)">{{ $message }}</p> @enderror
                    @if ($pressing->logo_url)
                        <button type="button" wire:click="removeLogo" class="text-xs font-medium text-(--color-error) text-left">Retirer le logo actuel</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 grid grid-cols-2 gap-4">
            <div class="col-span-2 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Informations générales</div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom du pressing</label>
                <input type="text" wire:model="name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
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
            <div class="col-span-2">
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Description</label>
                <textarea wire:model="description" rows="3" class="w-full px-3 py-2 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)"></textarea>
                @error('description') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-4">Horaires d'ouverture</div>
            <div class="flex flex-col gap-2.5">
                @foreach ($days as $day)
                    <div class="flex items-center gap-4">
                        <label class="w-24 text-sm font-medium flex-none">{{ $dayLabels[$day] }}</label>
                        <label class="flex items-center gap-1.5 text-xs text-(--color-text-secondary) flex-none w-24">
                            <input type="checkbox" wire:model="openingHours.{{ $day }}.closed" class="rounded border-(--color-border)">
                            Fermé
                        </label>
                        <input type="time" wire:model="openingHours.{{ $day }}.open" @disabled($openingHours[$day]['closed'])
                               class="h-9 px-2.5 rounded-lg border border-(--color-border) text-sm disabled:opacity-40">
                        <span class="text-(--color-text-muted)">—</span>
                        <input type="time" wire:model="openingHours.{{ $day }}.close" @disabled($openingHours[$day]['closed'])
                               class="h-9 px-2.5 rounded-lg border border-(--color-border) text-sm disabled:opacity-40">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                Enregistrer
            </button>
        </div>
    </form>
</div>
