<div class="max-w-3xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Profil du pressing</h1>
        <p class="text-sm text-(--color-text-muted)">
            Ce que vos clients voient de votre pressing dans l'application et sur les reçus.
            Code pressing : <span class="font-mono font-semibold">{{ $pressing->code }}</span>
            <span class="text-(--color-text-muted)">— à transmettre aux clients pour rejoindre votre établissement.</span>
        </p>
    </div>

    <form novalidate wire:submit="save" class="flex flex-col gap-6">
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
                        <button type="button" wire:click="removeLogo" data-confirm-title="Retirer le logo" data-confirm-label="Retirer" data-confirm-tone="danger" wire:confirm="Le logo ne sera plus affiché à vos clients dans l'application. Vous pourrez en envoyer un nouveau à tout moment." class="text-xs font-medium text-(--color-error) text-left">Retirer le logo actuel</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Informations générales</div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom du pressing</label>
                <input type="text" wire:model="name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone</label>
                <x-phone-input wire:model="phone" placeholder="07 08 12 44 00 ou 27 22 44 55 66" />
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
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Adresse</label>
                <input type="text" wire:model="address" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('address') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Description</label>
                <textarea wire:model="description" rows="3" class="w-full px-3 py-2 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)"></textarea>
                @error('description') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
