@php
    // Formulaire commun aux deux portails ; $dark = console super admin.
    $dark = $dark ?? false;
    $label = $dark ? 'text-slate-300' : 'text-(--color-text-primary)';
    $input = $dark
        ? 'bg-white/5 border-white/10 text-white placeholder:text-slate-500 focus:border-violet-400 focus:ring-4 focus:ring-violet-500/20'
        : 'bg-(--color-surface) border-(--color-border) placeholder:text-(--color-text-muted) focus:border-(--color-primary) focus:ring-4 focus:ring-blue-500/10';
    $button = $dark
        ? 'bg-gradient-to-r from-violet-500 to-indigo-500 hover:from-violet-400 hover:to-indigo-400 shadow-lg shadow-violet-900/40'
        : 'bg-(--color-primary) hover:bg-(--color-primary-600) shadow-lg shadow-blue-600/20';
    $error = $dark ? 'text-rose-300' : 'text-(--color-error)';
@endphp
<form novalidate wire:submit="authenticate" class="space-y-5">
    <div>
        <label for="login" class="block text-sm font-medium {{ $label }} mb-1.5">{{ $dark ? 'Identifiant, téléphone ou email' : 'Téléphone, email ou identifiant' }}</label>
        <div class="relative">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 {{ $dark ? 'text-slate-500' : 'text-(--color-text-muted)' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
            <input id="login" type="text" wire:model="login" autofocus autocomplete="username"
                   placeholder="{{ $dark ? 'superadmin' : '07 00 00 00 00' }}"
                   class="w-full h-12 pl-10 pr-3 rounded-xl border text-sm outline-none {{ $input }}">
        </div>
        @error('login')
            <p class="mt-1.5 text-sm {{ $error }}">{{ $message }}</p>
        @enderror
    </div>

    <div x-data="{ show: false }">
        <label for="password" class="block text-sm font-medium {{ $label }} mb-1.5">Mot de passe</label>
        <div class="relative">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 {{ $dark ? 'text-slate-500' : 'text-(--color-text-muted)' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            <input id="password" :type="show ? 'text' : 'password'" wire:model="password" autocomplete="current-password"
                   class="w-full h-12 pl-10 pr-11 rounded-xl border text-sm outline-none {{ $input }}">
            <button type="button" @click="show = !show" tabindex="-1"
                    class="absolute inset-y-0 right-0 flex items-center px-3.5 {{ $dark ? 'text-slate-500 hover:text-slate-300' : 'text-(--color-text-muted) hover:text-(--color-text-secondary)' }}"
                    :aria-label="show ? 'Masquer le mot de passe' : 'Afficher le mot de passe'">
                <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 11 7 11 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 1 12s4 7 11 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>
            </button>
        </div>
        @error('password')
            <p class="mt-1.5 text-sm {{ $error }}">{{ $message }}</p>
        @enderror
    </div>

    <label class="flex items-center gap-2 text-sm {{ $dark ? 'text-slate-400' : 'text-(--color-text-secondary)' }}">
        <input type="checkbox" wire:model="remember" class="rounded {{ $dark ? 'bg-white/5 border-white/20 accent-violet-500' : 'border-(--color-border)' }}">
        Rester connecté sur cet appareil
    </label>

    <button type="submit" wire:loading.attr="disabled"
            class="w-full h-12 rounded-xl text-white text-sm font-semibold inline-flex items-center justify-center gap-2 disabled:opacity-70 {{ $button }}">
        <span wire:loading.remove>{{ $dark ? 'Accéder à la console' : 'Se connecter' }}</span>
        <span wire:loading class="inline-flex items-center gap-2">
            <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            Connexion…
        </span>
        <svg wire:loading.remove class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </button>
</form>
