{{--
    Gestes pour installer PressLink Pro quand le navigateur n'a pas d'invite
    (iPhone, Samsung Internet, appli Facebook…) : ouvert par
    window.presslinkInstall.start() (voir <x-installable-app />).
--}}
@php
    $share = '<svg class="inline w-4 h-4 -mt-1 text-(--color-primary)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="Partager"><path d="M12 3v12"/><path d="m8 7 4-4 4 4"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>';
    $cases = [
        'ios-safari' => ["Touchez « Partager » {$share}, en bas de l'écran (ou dans le menu « ••• »).", 'Choisissez « Sur l\'écran d\'accueil ».', 'Touchez « Ajouter » : l\'icône PressLink Pro apparaît sur votre écran d\'accueil.'],
        'ios-chrome' => ["Touchez « Partager » {$share}, dans la barre d'adresse en haut.", 'Choisissez « Sur l\'écran d\'accueil ».', 'Touchez « Ajouter » : l\'icône PressLink Pro apparaît sur votre écran d\'accueil.'],
        'ios-other' => ['Ouvrez presslink.org dans <strong>Safari</strong> (l\'installation se fait depuis Safari sur iPhone).', "Touchez « Partager » {$share} puis « Sur l'écran d'accueil ».", 'Touchez « Ajouter ».'],
        'android-chrome' => ['Touchez le menu <strong>⋮</strong> en haut à droite de Chrome.', 'Choisissez « Installer l\'application » (ou « Ajouter à l\'écran d\'accueil »).', 'Confirmez avec « Installer ».'],
        'android-samsung' => ['Touchez le menu <strong>≡</strong> en bas à droite.', 'Choisissez « Ajouter la page à » puis « Écran d\'accueil ».', 'Confirmez avec « Ajouter ».'],
        'android-other' => ['Ouvrez le menu de votre navigateur (<strong>⋮</strong> ou <strong>≡</strong>).', 'Choisissez « Installer l\'application » ou « Ajouter à l\'écran d\'accueil ».', 'Confirmez.'],
        'desktop' => ['Dans Chrome ou Edge, cliquez sur l\'icône d\'installation à droite de la barre d\'adresse.', 'Ou ouvrez le menu <strong>⋮</strong> puis « Installer PressLink Pro ».', 'Confirmez avec « Installer ».'],
    ];
@endphp
<div data-install-steps x-data="{
        open: false,
        get pi() { return window.presslinkInstall || {} },
        get key() {
            const pi = this.pi;
            if (pi.ios) return pi.browser === 'safari' ? 'ios-safari' : (pi.browser === 'chrome' ? 'ios-chrome' : 'ios-other');
            if (pi.android) return pi.browser === 'chrome' ? 'android-chrome' : (pi.browser === 'samsung' ? 'android-samsung' : 'android-other');
            return 'desktop';
        },
        get chromeButton() { return this.pi.android && this.pi.browser !== 'chrome' },
     }"
     @presslink-install-steps.window="open = true" @keydown.escape.window="open = false"
     x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="install-steps-title">
    <div class="absolute inset-0 bg-slate-900/45" @click="open = false" aria-hidden="true"></div>
    <div class="relative w-full sm:max-w-md bg-(--color-surface) border border-(--color-border) rounded-t-3xl sm:rounded-2xl shadow-2xl animate-step pb-[env(safe-area-inset-bottom)]" x-trap.noscroll="open">
        <div class="flex items-start gap-3 px-5 pt-5 pb-3">
            <img src="{{ asset('apple-touch-icon-pro.png') }}?v=3" width="44" height="44" alt="" class="w-11 h-11 rounded-xl flex-none">
            <div class="flex-1 min-w-0">
                <h2 id="install-steps-title" class="font-semibold text-base">Installer PressLink Pro</h2>
                <p class="text-sm text-(--color-text-secondary)">Une icône sur votre écran d'accueil, le logiciel en plein écran, sans passer par un store.</p>
            </div>
            <button type="button" @click="open = false" aria-label="Fermer"
                    class="w-9 h-9 flex-none rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="px-5 pb-6">
            <template x-if="chromeButton">
                <div class="mb-4">
                    <button type="button" @click="window.presslinkInstall.openInChrome()" data-testid="install-open-chrome"
                            class="w-full inline-flex items-center justify-center gap-2 min-h-12 px-5 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">
                        Ouvrir dans Chrome pour installer
                    </button>
                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-(--color-text-muted)">Ou, dans ce navigateur :</p>
                </div>
            </template>
            @foreach ($cases as $key => $steps)
                <ol x-show="key === '{{ $key }}'" @if ($key !== 'desktop') x-cloak @endif data-install-case="{{ $key }}" class="space-y-3">
                    @foreach ($steps as $step)
                        <li class="flex items-start gap-3 text-sm leading-relaxed">
                            <span class="w-6 h-6 flex-none rounded-full bg-(--color-primary-tint) text-(--color-primary) text-xs font-bold flex items-center justify-center">{{ $loop->iteration }}</span>
                            <span>{!! $step !!}</span>
                        </li>
                    @endforeach
                </ol>
            @endforeach
            <p x-show="pi.ios" x-cloak class="mt-4 rounded-xl bg-(--color-bg) border border-(--color-border) p-3 text-xs text-(--color-text-secondary)">
                À la première ouverture depuis l'icône, connectez-vous une fois : l'iPhone garde la connexion de l'application à part.
            </p>
            <p x-show="pi.android" x-cloak class="mt-4 text-xs text-(--color-text-muted)">
                Déjà installé ? Ouvrez PressLink Pro depuis votre écran d'accueil.
            </p>
        </div>
    </div>
</div>
