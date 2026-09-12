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

            <div x-data="{ show: false }">
                <label for="password" class="block text-sm font-medium text-(--color-text-primary) mb-1.5">
                    Mot de passe
                </label>
                <div class="relative">
                    <input
                        id="password"
                        :type="show ? 'text' : 'password'"
                        wire:model="password"
                        class="w-full h-11 px-3 pr-10 rounded-(--radius-md) border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)"
                    >
                    <button type="button" @click="show = !show"
                            class="absolute inset-y-0 right-0 flex items-center px-3 text-(--color-text-muted) hover:text-(--color-text-secondary)"
                            :aria-label="show ? 'Masquer le mot de passe' : 'Afficher le mot de passe'" tabindex="-1">
                        <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg x-show="show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 11 7 11 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 1 12s4 7 11 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>
                    </button>
                </div>
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
