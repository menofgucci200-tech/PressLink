<div class="max-w-lg">
    <h1 class="font-display text-2xl font-bold mb-1">Mon compte</h1>
    <p class="text-sm text-(--color-text-muted) mb-6">Identifiant de connexion et mot de passe de votre compte.</p>

    @if ($successMessage)
        <div class="mb-5 p-3 rounded-lg bg-(--color-success-tint) text-(--color-success-text) text-sm">{{ $successMessage }}</div>
    @endif

    <form wire:submit="updateLogin" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-5">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-4">Identifiant de connexion</div>
        <div class="mb-4">
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Login</label>
            <input type="text" wire:model="login" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
            @error('login') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="updateLogin"
                    class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold disabled:opacity-60">
                <span wire:loading.remove wire:target="updateLogin">Enregistrer</span>
                <span wire:loading wire:target="updateLogin">Enregistrement…</span>
            </button>
        </div>
    </form>

    <form wire:submit="updatePassword" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-4">Mot de passe</div>
        <div class="mb-4">
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Mot de passe actuel</label>
            <input type="password" wire:model="currentPassword" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
            @error('currentPassword') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="mb-4">
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nouveau mot de passe</label>
            <input type="password" wire:model="newPassword" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
            @error('newPassword') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="mb-4">
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Confirmer le nouveau mot de passe</label>
            <input type="password" wire:model="newPasswordConfirmation" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
            @error('newPasswordConfirmation') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword"
                    class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold disabled:opacity-60">
                <span wire:loading.remove wire:target="updatePassword">Changer le mot de passe</span>
                <span wire:loading wire:target="updatePassword">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
