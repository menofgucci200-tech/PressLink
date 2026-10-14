{{-- Portail « Espace pressing » : clair, bleu PressLink, tourné métier. --}}
<div class="min-h-screen grid lg:grid-cols-[1.05fr_1fr] bg-(--color-surface)">
    {{-- Panneau de présentation (masqué sur mobile) --}}
    <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-(--color-primary) text-white p-12">
        <div class="absolute -top-32 -right-32 w-[28rem] h-[28rem] rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
        <div class="absolute -bottom-40 -left-24 w-[30rem] h-[30rem] rounded-full bg-cyan-300/20 blur-3xl" aria-hidden="true"></div>

        <a href="{{ route('home') }}" class="relative font-display text-2xl font-bold tracking-tight">PressLink</a>

        <div class="relative max-w-md">
            <p class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-widest mb-6">Espace pressing</p>
            <h2 class="font-display text-4xl font-extrabold leading-tight tracking-tight mb-5">Votre pressing, organisé et vos clients toujours informés.</h2>
            <ul class="space-y-3 text-blue-50">
                @foreach (['Commandes créées en quelques secondes', 'Clients notifiés automatiquement', 'Tableau de bord, équipe et exports'] as $point)
                    <li class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center flex-none">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        {{ $point }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="relative phone-mock phone-mock--hands w-80 -mb-12 self-end">
            <div class="phone-mock__screen"><x-site.app-screen /></div>
            <img src="{{ asset('images/hands-phone.webp') }}" width="736" height="648" alt="">
        </div>
    </aside>

    {{-- Formulaire --}}
    <main class="relative flex flex-col justify-center px-6 py-12 sm:px-12 bg-(--color-bg) lg:bg-(--color-surface)">
        <x-theme-toggle class="absolute top-5 right-5 sm:top-6 sm:right-6" />
        <div class="w-full max-w-sm mx-auto animate-step">
            <a href="{{ route('home') }}" class="lg:hidden inline-block font-display text-2xl font-bold tracking-tight mb-10">Press<span class="text-(--color-primary)">Link</span></a>

            <div class="w-12 h-12 rounded-2xl bg-(--color-primary-tint) text-(--color-primary) flex items-center justify-center mb-5">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9.5 12 4l9 5.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z"/><path d="M9 21v-7h6v7"/></svg>
            </div>
            <h1 class="font-display text-3xl font-bold tracking-tight text-(--color-text-primary) mb-1.5">Bon retour 👋</h1>
            <p class="text-(--color-text-secondary) mb-8">Connectez-vous pour gérer votre pressing.</p>

            @include('livewire.auth._form', ['dark' => false])

            <div class="mt-8 rounded-xl border border-(--color-border) bg-(--color-surface) lg:bg-(--color-bg) p-4 text-sm text-(--color-text-secondary)">
                Mot de passe oublié ou pas encore de compte ?
                <a href="{{ route('site.contact') }}" class="font-semibold text-(--color-primary) hover:underline">Contactez PressLink</a>
                ou demandez à l'administrateur de votre pressing.
            </div>

            <p class="mt-8 text-xs text-(--color-text-muted)">
                <a href="{{ route('home') }}" class="hover:text-(--color-primary)">← presslink.org</a>
                <span class="mx-2">·</span>
                <a href="{{ route('site.terms') }}" class="hover:text-(--color-primary)">CGU</a>
                <span class="mx-2">·</span>
                <a href="{{ route('site.privacy') }}" class="hover:text-(--color-primary)">Confidentialité</a>
            </p>
        </div>
    </main>
</div>
