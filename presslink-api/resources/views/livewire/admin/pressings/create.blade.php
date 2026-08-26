<div class="max-w-2xl">
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
</div>
