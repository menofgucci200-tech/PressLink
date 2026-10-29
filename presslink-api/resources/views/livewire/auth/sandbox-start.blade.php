{{-- « Découvrir avec un pressing fictif » : nom du pressing + WhatsApp, puis connexion directe au pressing de test. --}}
@php
    $days = \App\Actions\Sandbox\StartSandbox::DAYS;
    $input = 'w-full h-12 px-3.5 rounded-xl border text-base outline-none bg-(--color-surface) border-(--color-border) placeholder:text-(--color-text-muted) focus:border-(--color-primary) focus:ring-4 focus:ring-blue-500/10';
    $label = 'block text-sm font-medium text-(--color-text-primary) mb-1.5';
    $help = 'mt-1 text-xs text-(--color-text-muted)';
    $whatsapp = 'https://wa.me/'.ltrim((string) config('presslink.support.phone_e164'), '+').'?text='.rawurlencode('Bonjour, j\'ai une question sur le test de PressLink.');
@endphp
<div class="min-h-screen grid lg:grid-cols-[1.05fr_1fr] bg-(--color-surface)">
    {{-- Panneau de présentation (masqué sur mobile) --}}
    <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-(--color-primary) text-white p-12">
        <div class="absolute -top-32 -right-32 w-[28rem] h-[28rem] rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
        <div class="absolute -bottom-40 -left-24 w-[30rem] h-[30rem] rounded-full bg-cyan-300/20 blur-3xl" aria-hidden="true"></div>

        <a href="{{ route('home') }}" class="relative font-display text-2xl font-bold tracking-tight">PressLink</a>

        <div class="relative max-w-md">
            <p class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-widest mb-6">Test de {{ $days }} jours</p>
            <h2 class="font-display text-4xl font-extrabold leading-tight tracking-tight mb-5">Essayez PressLink sur un pressing déjà rempli.</h2>
            <ul class="space-y-3 text-blue-50">
                @foreach ([
                    'Des clients et des commandes fictifs, prêts à manipuler',
                    'Rien n\'est envoyé à de vrais clients',
                    'Pas de mot de passe à retenir, rien à payer',
                    'Convaincu ? Créez votre vrai compte en gardant vos articles et vos prix',
                ] as $point)
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
            <a href="{{ route('home') }}" class="lg:hidden inline-block font-display text-2xl font-bold tracking-tight mb-8">Press<span class="text-(--color-primary)">Link</span></a>

            @if ($ended)
                <div class="mb-6 rounded-xl border border-(--color-warning-text)/20 bg-(--color-warning-tint) p-4 text-sm text-(--color-warning-text)" role="status" data-testid="sandbox-ended">
                    <p class="font-semibold">Votre test est terminé.</p>
                    <p class="mt-1">Les données fictives ont été effacées. Prêt à démarrer ? <a href="{{ route('signup') }}" class="font-semibold underline">Créez votre vrai pressing</a> : essai gratuit, sans engagement.</p>
                </div>
            @endif

            <p class="text-xs font-semibold uppercase tracking-widest text-(--color-primary) mb-2">Pressing fictif</p>
            <h1 class="font-display text-3xl font-bold tracking-tight text-(--color-text-primary) mb-1.5">Tester PressLink</h1>
            <p class="text-(--color-text-secondary) mb-7">
                Votre pressing de test est prêt en quelques secondes, pour <span class="font-semibold text-(--color-text-primary)">{{ $days }} jours</span>. Ensuite, il est effacé.
            </p>

            @if ($runningTrial)
                <div class="mb-6 rounded-xl border border-(--color-primary)/20 bg-(--color-primary-tint) p-4" data-testid="sandbox-resume">
                    <p class="text-sm text-(--color-text-primary)">Votre test <span class="font-semibold">{{ $runningTrial->pressing_name }}</span> est encore ouvert jusqu'au {{ $runningTrial->expires_at->translatedFormat('j F à H\hi') }}.</p>
                    <button type="button" wire:click="resume" wire:loading.attr="disabled"
                            class="mt-3 w-full h-11 rounded-xl text-white text-sm font-semibold bg-(--color-primary) hover:bg-(--color-primary-600) disabled:opacity-70">Reprendre mon test</button>
                </div>
            @endif

            <form novalidate wire:submit="start" class="space-y-5">
                {{-- Champ piège pour les robots, invisible pour les personnes --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">Site web</label>
                    <input id="website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <label for="pressingName" class="{{ $label }}">Nom de votre pressing</label>
                    <input id="pressingName" type="text" wire:model="pressingName" @unless ($runningTrial) autofocus @endunless autocomplete="organization" placeholder="Pressing Élégance" class="{{ $input }}">
                    <p class="{{ $help }}">Il s'affiche sur votre pressing de test et sur ses reçus.</p>
                    @error('pressingName') <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone" class="{{ $label }}">Votre numéro WhatsApp</label>
                    <input id="phone" type="tel" inputmode="tel" wire:model="phone" autocomplete="tel" placeholder="07 00 00 00 00" class="{{ $input }} tabular-nums">
                    <p class="{{ $help }}">Pour vous aider si vous avez une question. Aucun message n'est envoyé à vos clients.</p>
                    @error('phone') <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full h-12 rounded-xl text-white text-sm font-semibold inline-flex items-center justify-center gap-2 disabled:opacity-70 bg-(--color-primary) hover:bg-(--color-primary-600) shadow-lg shadow-blue-600/20">
                    <span wire:loading.remove wire:target="start">Lancer mon test</span>
                    <span wire:loading wire:target="start">Préparation de votre pressing…</span>
                </button>
            </form>

            <div class="mt-8 rounded-xl border border-(--color-border) bg-(--color-surface) lg:bg-(--color-bg) p-4 text-sm text-(--color-text-secondary)">
                Prêt à travailler avec vos vrais clients ? <a href="{{ route('signup') }}" class="font-semibold text-(--color-primary) hover:underline">Créer mon pressing</a>
                <span class="block mt-1">Une question ? <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="font-semibold text-(--color-primary) hover:underline">Écrivez-nous sur WhatsApp</a></span>
            </div>
        </div>
    </main>
</div>
