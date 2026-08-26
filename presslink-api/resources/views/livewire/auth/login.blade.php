<div class="w-full max-w-md">
    <div class="flex items-center gap-2 justify-center mb-8">
        <span class="font-display text-2xl font-bold text-(--color-text-primary)">
            Press<span class="text-(--color-primary)">Link</span>
        </span>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-(--radius-lg) shadow-sm p-8">
        <h1 class="font-display text-xl font-bold text-(--color-text-primary) mb-1">Espace pressing</h1>
        <p class="text-sm text-(--color-text-secondary) mb-6">Connectez-vous pour gérer votre établissement.</p>

        <form wire:submit="authenticate" class="space-y-4">
            <div>
                <label for="login" class="block text-sm font-medium text-(--color-text-primary) mb-1.5">
                    Téléphone ou email
                </label>
                <input
                    id="login"
                    type="text"
                    wire:model="login"
                    autofocus
                    placeholder="admin@pressing-elegance.test"
                    class="w-full h-11 px-3 rounded-(--radius-md) border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)"
                >
                @error('login')
                    <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-(--color-text-primary) mb-1.5">
                    Mot de passe
                </label>
                <input
                    id="password"
                    type="password"
                    wire:model="password"
                    class="w-full h-11 px-3 rounded-(--radius-md) border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)"
                >
                @error('password')
                    <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-(--color-text-secondary)">
                <input type="checkbox" wire:model="remember" class="rounded border-(--color-border)">
                Se souvenir de moi
            </label>

            <button
                type="submit"
                class="w-full h-11 rounded-(--radius-md) bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) transition-colors"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>Se connecter</span>
                <span wire:loading>Connexion…</span>
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-(--color-text-muted) mt-6">
        PressLink — Le lien entre votre pressing et vos clients.
    </p>
</div>
