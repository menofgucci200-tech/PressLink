@php
    $card = 'bg-(--color-surface) border border-(--color-border) rounded-xl p-5 sm:p-6';
    $sectionTitle = 'text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)';
    $label = 'block text-xs font-medium text-(--color-text-secondary) mb-1.5';
    $input = 'w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm placeholder:text-(--color-text-muted) focus:outline-none focus:border-(--color-primary)';
    $primaryButton = 'h-10 px-4 rounded-lg bg-(--color-primary) hover:bg-(--color-primary-600) text-white text-sm font-semibold disabled:opacity-60';
@endphp
<div class="max-w-3xl">
    <h1 class="font-display text-2xl font-bold mb-1">Mon profil</h1>
    <p class="text-sm text-(--color-text-muted) mb-6">Personnalisez votre profil : photo, coordonnées, apparence, identifiant de connexion et mot de passe.</p>

    {{-- ===== Photo et identité ===== --}}
    <section class="{{ $card }} mb-5 flex flex-col sm:flex-row sm:items-center gap-5" aria-label="Photo de profil">
        <div class="relative w-24 h-24 flex-none">
            @if ($avatar && ! $errors->has('avatar'))
                <img src="{{ $avatar->temporaryUrl() }}" alt="" class="w-24 h-24 rounded-2xl object-cover opacity-60">
            @else
                <x-user-avatar :user="$user" class="w-24 h-24 rounded-2xl text-2xl" />
            @endif
            <div wire:loading.flex wire:target="avatar" class="absolute inset-0 rounded-2xl bg-slate-900/50 items-center justify-center">
                <svg class="w-6 h-6 text-white animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg>
            </div>
        </div>

        <div class="min-w-0 flex-1">
            <div class="font-display text-xl font-bold truncate">{{ $user->name }}</div>
            <div class="mt-1.5 flex flex-wrap gap-1.5">
                @if ($user->is_super_admin)
                    <span class="inline-flex items-center rounded-full bg-(--color-primary-tint) text-(--color-primary) px-2.5 py-1 text-xs font-semibold">Super Administrateur</span>
                @endif
                @foreach ($memberships as $membership)
                    <span class="inline-flex items-center gap-1 rounded-full bg-(--color-bg) border border-(--color-border) px-2.5 py-1 text-xs text-(--color-text-secondary)">
                        <span class="font-semibold text-(--color-text-primary)">{{ $membership->name }}</span> · {{ $membership->pivot->role->label() }}
                    </span>
                @endforeach
            </div>
            <div class="mt-2 text-xs text-(--color-text-muted)">Membre depuis {{ $user->created_at?->translatedFormat('F Y') }}</div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <label class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-medium cursor-pointer hover:border-(--color-primary) hover:text-(--color-primary)">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z"/><circle cx="12" cy="13" r="3"/></svg>
                    <span wire:loading.remove wire:target="avatar">{{ $user->avatar_url ? 'Changer la photo' : 'Ajouter une photo' }}</span>
                    <span wire:loading wire:target="avatar">Envoi en cours…</span>
                    <input type="file" wire:model="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>
                @if ($user->avatar_url)
                    <button type="button" wire:click="removeAvatar"
                            data-confirm-title="Retirer la photo" data-confirm-label="Retirer" data-confirm-tone="danger"
                            wire:confirm="Vos initiales s'afficheront à la place de votre photo."
                            class="h-9 px-3.5 rounded-lg text-sm font-medium text-(--color-text-secondary) hover:text-(--color-error) hover:bg-(--color-error-tint)">
                        Retirer
                    </button>
                @endif
            </div>
            @error('avatar') <p class="text-xs text-(--color-error) mt-2">{{ $message }}</p> @enderror
            <p class="text-xs text-(--color-text-muted) mt-2">JPG, PNG ou WebP. Votre photo apparaît dans l'en-tête du dashboard.</p>
        </div>
    </section>

    {{-- ===== Informations personnelles ===== --}}
    <form novalidate wire:submit="updateProfile" class="{{ $card }} mb-5">
        <div class="{{ $sectionTitle }} mb-4">Informations personnelles</div>
        <div class="grid sm:grid-cols-2 gap-4 mb-4">
            <div class="sm:col-span-2">
                <label for="profile-name" class="{{ $label }}">Nom complet</label>
                <input id="profile-name" type="text" wire:model="name" autocomplete="name" class="{{ $input }}">
                @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="profile-email" class="{{ $label }}">Adresse e-mail</label>
                <input id="profile-email" type="email" wire:model="email" autocomplete="email" placeholder="vous@exemple.com" class="{{ $input }}">
                @error('email') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="profile-phone" class="{{ $label }}">Téléphone</label>
                <input id="profile-phone" type="tel" wire:model="phone" autocomplete="tel" placeholder="07 08 12 44 00" class="{{ $input }}">
                @error('phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <p class="text-xs text-(--color-text-muted)">L'e-mail et le téléphone servent aussi à vous connecter.</p>
            <button type="submit" wire:loading.attr="disabled" wire:target="updateProfile" class="{{ $primaryButton }} self-end sm:self-auto">
                <span wire:loading.remove wire:target="updateProfile">Enregistrer</span>
                <span wire:loading wire:target="updateProfile">Enregistrement…</span>
            </button>
        </div>
    </form>

    {{-- ===== Apparence (choix mémorisé sur cet appareil, cf. theme-script) ===== --}}
    <section class="{{ $card }} mb-5" x-data="{ mode: window.PressLinkTheme ? PressLinkTheme.mode() : 'system' }"
             @presslink-theme-changed.window="mode = PressLinkTheme.mode()" aria-labelledby="appearance-title">
        <div id="appearance-title" class="{{ $sectionTitle }} mb-1">Apparence</div>
        <p class="text-xs text-(--color-text-muted) mb-4">Mémorisée sur cet appareil.</p>
        <div class="grid grid-cols-3 gap-2 sm:gap-3" role="radiogroup" aria-label="Apparence">
            @foreach ([
                'light' => ['Jour', 'M12 3v1M12 20v1M4.22 4.22l.7.7M19.08 19.08l.7.7M3 12h1M20 12h1M4.22 19.78l.7-.7M19.08 4.92l.7-.7|circle'],
                'dark' => ['Nuit', 'M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z'],
                'system' => ['Automatique', 'M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z|M8 21h8|M12 17v4'],
            ] as $mode => [$modeLabel, $icon])
                <button type="button" role="radio" :aria-checked="mode === '{{ $mode }}'" @click="PressLinkTheme.set('{{ $mode }}')"
                        class="flex flex-col items-center gap-2 rounded-xl border-2 px-2 py-3.5 text-sm font-medium"
                        :class="mode === '{{ $mode }}' ? 'border-(--color-primary) bg-(--color-primary-tint) text-(--color-primary)' : 'border-(--color-border) text-(--color-text-secondary) hover:border-(--color-text-muted)'">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        @foreach (explode('|', $icon) as $path)
                            @if ($path === 'circle')
                                <circle cx="12" cy="12" r="4"></circle>
                            @else
                                <path d="{{ $path }}"></path>
                            @endif
                        @endforeach
                    </svg>
                    {{ $modeLabel }}
                </button>
            @endforeach
        </div>
    </section>

    {{-- ===== Connexion et sécurité ===== --}}
    <div id="securite" class="grid lg:grid-cols-2 gap-5 scroll-mt-6">
        <form novalidate wire:submit="updateLogin" class="{{ $card }} flex flex-col">
            <div class="{{ $sectionTitle }} mb-4">Identifiant de connexion</div>
            <div class="mb-4">
                <label for="profile-login" class="{{ $label }}">Login</label>
                <input id="profile-login" type="text" wire:model="login" autocomplete="username" class="{{ $input }} font-mono">
                @error('login') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-(--color-text-muted) mt-1.5">Lettres, chiffres, points, tirets ou underscores.</p>
            </div>
            <div class="flex-1"></div>
            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="updateLogin" class="{{ $primaryButton }}">
                    <span wire:loading.remove wire:target="updateLogin">Enregistrer</span>
                    <span wire:loading wire:target="updateLogin">Enregistrement…</span>
                </button>
            </div>
        </form>

        <form novalidate wire:submit="updatePassword" class="{{ $card }}">
            <div class="{{ $sectionTitle }} mb-4">Mot de passe</div>
            <div class="mb-4">
                <label for="profile-current-password" class="{{ $label }}">Mot de passe actuel</label>
                <input id="profile-current-password" type="password" wire:model="currentPassword" autocomplete="current-password" class="{{ $input }}">
                @error('currentPassword') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label for="profile-new-password" class="{{ $label }}">Nouveau mot de passe</label>
                <input id="profile-new-password" type="password" wire:model="newPassword" autocomplete="new-password" class="{{ $input }}">
                @error('newPassword') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label for="profile-new-password-confirmation" class="{{ $label }}">Confirmer le nouveau mot de passe</label>
                <input id="profile-new-password-confirmation" type="password" wire:model="newPasswordConfirmation" autocomplete="new-password" class="{{ $input }}">
                @error('newPasswordConfirmation') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword" class="{{ $primaryButton }}">
                    <span wire:loading.remove wire:target="updatePassword">Changer le mot de passe</span>
                    <span wire:loading wire:target="updatePassword">Enregistrement…</span>
                </button>
            </div>
        </form>
    </div>
</div>
