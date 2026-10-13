{{-- Rappel affiché tant qu'un mot de passe réinitialisé (ou par défaut) n'a pas été changé. --}}
@if (auth()->user()?->must_change_password)
    <div x-data="{ hidden: false }" x-show="! hidden" @password-changed.window="hidden = true" class="mb-6 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-amber-200 bg-(--color-warning-tint) px-4 py-3 text-sm" role="status">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-warning-text)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        <p class="flex-1 min-w-0 text-(--color-warning-text)">
            <span class="font-semibold">Votre mot de passe a été réinitialisé.</span>
            Choisissez-en un nouveau pour sécuriser votre compte.
        </p>
        @unless (request()->routeIs('account.settings'))
            <a href="{{ route('account.settings') }}" wire:navigate class="inline-flex items-center h-8 px-3 rounded-lg bg-(--color-warning-text) text-white text-xs font-semibold hover:brightness-110">Changer mon mot de passe</a>
        @endunless
    </div>
@endif
