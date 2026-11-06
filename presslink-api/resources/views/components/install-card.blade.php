{{-- Carte « Installer PressLink » sur téléphone (accueil du logiciel, connexion) : masquable, se souvient du choix sur l'appareil. --}}
<div wire:ignore x-data="{
        show: false,
        init() {
            const pi = window.presslinkInstall;
            let hidden = false;
            try { hidden = localStorage.getItem('presslink-install-card') === 'masquee'; } catch (e) {}
            const update = () => { this.show = !hidden && !!pi && pi.phone && pi.offer(); };
            update();
            window.addEventListener('presslink-install', update);
        },
        hide() {
            this.show = false;
            try { localStorage.setItem('presslink-install-card', 'masquee'); } catch (e) {}
        },
     }"
     x-show="show" x-cloak data-install-card
     {{ $attributes->class('flex items-center gap-3 rounded-2xl border border-(--color-primary)/20 bg-(--color-primary-tint) p-3.5') }}>
    <img src="{{ asset('apple-touch-icon-pro.png') }}?v=3" width="44" height="44" alt="" class="w-11 h-11 rounded-xl flex-none">
    <div class="flex-1 min-w-0">
        <p class="font-semibold text-sm leading-tight">Installez PressLink</p>
        <p class="text-xs text-(--color-text-secondary) mt-0.5">Sur l'écran d'accueil de ce téléphone.</p>
    </div>
    <button type="button" @click="window.presslinkInstall.start()"
            class="flex-none inline-flex items-center min-h-11 px-4 rounded-xl bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
        Installer
    </button>
    <button type="button" @click="hide()" aria-label="Ne plus proposer"
            class="flex-none -mr-1 w-9 h-11 flex items-center justify-center text-(--color-text-muted) hover:text-(--color-text-primary)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"></path></svg>
    </button>
</div>
