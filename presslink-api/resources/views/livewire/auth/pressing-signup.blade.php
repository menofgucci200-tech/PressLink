{{-- Inscription d'un pressing : deux étapes courtes, essai gratuit, sans paiement. --}}
@php
    $trialEnd = \App\Models\Subscription::trialEndsAtForNewPressing();
    $launch = \App\Support\LaunchOffer::isActive();
    $input = 'w-full h-12 px-3.5 rounded-xl border text-base outline-none bg-(--color-surface) border-(--color-border) placeholder:text-(--color-text-muted) focus:border-(--color-primary) focus:ring-4 focus:ring-blue-500/10';
    $label = 'block text-sm font-medium text-(--color-text-primary) mb-1.5';
    $help = 'mt-1 text-xs text-(--color-text-muted)';
    $whatsapp = 'https://wa.me/'.ltrim((string) config('presslink.support.phone_e164'), '+').'?text='.rawurlencode('Bonjour, je souhaite être accompagné pour inscrire mon pressing sur PressLink.');
@endphp
<div class="min-h-screen grid lg:grid-cols-[1.05fr_1fr] bg-(--color-surface)">
    {{-- Panneau de présentation (masqué sur mobile) --}}
    <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-(--color-primary) text-white p-12">
        <div class="absolute -top-32 -right-32 w-[28rem] h-[28rem] rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
        <div class="absolute -bottom-40 -left-24 w-[30rem] h-[30rem] rounded-full bg-cyan-300/20 blur-3xl" aria-hidden="true"></div>

        <a href="{{ route('home') }}" class="relative font-display text-2xl font-bold tracking-tight">PressLink</a>

        <div class="relative max-w-md">
            <p class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-widest mb-6">Essai gratuit</p>
            <h2 class="font-display text-4xl font-extrabold leading-tight tracking-tight mb-5">Votre pressing sur PressLink en 2 minutes.</h2>
            <ul class="space-y-3 text-blue-50">
                @foreach ([
                    $launch ? 'Gratuit jusqu\'au '.\App\Support\LaunchOffer::dateLabel().', puis une semaine pour choisir' : 'Toutes les fonctions pendant l\'essai',
                    'Rien à payer pour commencer, sans engagement',
                    'Notre équipe vous appelle pour vous aider à démarrer',
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

            <p class="text-xs font-semibold uppercase tracking-widest text-(--color-primary) mb-2">Étape {{ $step }} sur 2</p>
            <h1 class="font-display text-3xl font-bold tracking-tight text-(--color-text-primary) mb-1.5">
                {{ $step === 1 ? 'Votre pressing' : 'Votre compte' }}
            </h1>
            @if ($sandboxPressingId && $step === 1)
                <div class="mb-5 rounded-xl border border-(--color-primary)/20 bg-(--color-primary-tint) p-3.5 text-sm text-(--color-text-primary)" data-testid="sandbox-convert-note">
                    Vos <span class="font-semibold">articles et vos prix</span> du test sont repris dans votre vrai pressing. Les clients et les commandes fictifs, eux, sont effacés.
                </div>
            @endif
            <p class="text-(--color-text-secondary) mb-7">
                @if ($step === 1)
                    Essai gratuit jusqu'au <span class="font-semibold text-(--color-text-primary)">{{ $trialEnd->translatedFormat('j F') }}</span>, rien à payer pour commencer.
                @else
                    Vous vous connecterez avec votre numéro et ce mot de passe.
                @endif
            </p>

            <form novalidate wire:submit="{{ $step === 1 ? 'next' : 'register' }}" class="space-y-5">
                {{-- Champ piège pour les robots, invisible pour les personnes --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">Site web</label>
                    <input id="website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
                </div>

                @if ($step === 1)
                    <div>
                        <label for="pressingName" class="{{ $label }}">Nom du pressing</label>
                        <input id="pressingName" type="text" wire:model="pressingName" autofocus autocomplete="organization" placeholder="Pressing Élégance" class="{{ $input }}">
                        @error('pressingName') <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="city" class="{{ $label }}">Commune ou quartier</label>
                        <input id="city" type="text" wire:model="city" autocomplete="address-level2" placeholder="Cocody Angré" class="{{ $input }}">
                        @error('city') <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="pressingPhone" class="{{ $label }}">Téléphone du pressing</label>
                        <input id="pressingPhone" type="tel" inputmode="tel" wire:model="pressingPhone" autocomplete="tel" placeholder="07 00 00 00 00" class="{{ $input }} tabular-nums">
                        <p class="{{ $help }}">Le numéro que vos clients appellent.</p>
                        @error('pressingPhone') <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <label for="ownerName" class="{{ $label }}">Votre nom</label>
                        <input id="ownerName" type="text" wire:model="ownerName" autofocus autocomplete="name" placeholder="Awa Kouassi" class="{{ $input }}">
                        @error('ownerName') <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="ownerPhone" class="{{ $label }}">Votre numéro de téléphone</label>
                        <input id="ownerPhone" type="tel" inputmode="tel" wire:model="ownerPhone" autocomplete="tel" placeholder="07 00 00 00 00" class="{{ $input }} tabular-nums">
                        <p class="{{ $help }}">Il vous sert à vous connecter.</p>
                        @error('ownerPhone')
                            <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}
                                @if (str_contains($message, 'connectez-vous')) <a href="{{ route('login') }}" class="font-semibold underline">Se connecter</a> @endif
                            </p>
                        @enderror
                    </div>
                    <div>
                        <label for="email" class="{{ $label }}">E-mail <span class="font-normal text-(--color-text-muted)">(facultatif)</span></label>
                        <input id="email" type="email" wire:model="email" autocomplete="email" placeholder="vous@exemple.com" class="{{ $input }}">
                        <p class="{{ $help }}">Pour vous prévenir avant la fin de l'essai.</p>
                        @error('email') <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p> @enderror
                    </div>
                    <div x-data="{ show: false }">
                        <label for="password" class="{{ $label }}">Mot de passe</label>
                        <div class="relative">
                            <input id="password" :type="show ? 'text' : 'password'" wire:model="password" autocomplete="new-password" class="{{ $input }} pr-11">
                            <button type="button" @click="show = !show" tabindex="-1" class="absolute inset-y-0 right-0 flex items-center px-3.5 text-(--color-text-muted) hover:text-(--color-text-secondary)"
                                    :aria-label="show ? 'Masquer le mot de passe' : 'Afficher le mot de passe'">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <p class="{{ $help }}">Au moins 8 caractères.</p>
                        @error('password') <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="flex items-start gap-2.5 text-sm text-(--color-text-secondary)">
                            <input type="checkbox" wire:model="acceptTerms" class="mt-0.5 w-5 h-5 rounded border-(--color-border)">
                            <span>J'accepte les <a href="{{ route('site.terms') }}" target="_blank" class="font-semibold text-(--color-primary) hover:underline">conditions générales</a> et la <a href="{{ route('site.privacy') }}" target="_blank" class="font-semibold text-(--color-primary) hover:underline">politique de confidentialité</a>.</span>
                        </label>
                        @error('acceptTerms') <p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p> @enderror
                    </div>
                @endif

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full h-12 rounded-xl text-white text-sm font-semibold inline-flex items-center justify-center gap-2 disabled:opacity-70 bg-(--color-primary) hover:bg-(--color-primary-600) shadow-lg shadow-blue-600/20">
                    <span wire:loading.remove>{{ $step === 1 ? 'Continuer' : 'Créer mon espace pressing' }}</span>
                    <span wire:loading>{{ $step === 1 ? 'Un instant…' : 'Création de votre espace…' }}</span>
                </button>

                @if ($step === 2)
                    <button type="button" wire:click="back" class="w-full text-sm font-medium text-(--color-text-secondary) hover:text-(--color-primary)">← Revenir au pressing</button>
                @endif
            </form>

            <div class="mt-8 rounded-xl border border-(--color-border) bg-(--color-surface) lg:bg-(--color-bg) p-4 text-sm text-(--color-text-secondary)">
                @if ($sandboxPressingId)
                    Pas encore décidé ? <a href="{{ route('dashboard') }}" class="font-semibold text-(--color-primary) hover:underline">Revenir à mon pressing de test</a>
                @else
                    Déjà inscrit ? <a href="{{ route('login') }}" class="font-semibold text-(--color-primary) hover:underline">Se connecter</a>
                    <span class="block mt-1">Pas encore décidé ? <a href="{{ route('sandbox.start') }}" class="font-semibold text-(--color-primary) hover:underline">Tester avec un pressing fictif</a></span>
                @endif
                <span class="block mt-1">Vous préférez être accompagné ? <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="font-semibold text-(--color-primary) hover:underline">Écrivez-nous sur WhatsApp</a></span>
            </div>
        </div>
    </main>
</div>
