{{-- Photos des articles : prises au dépôt, visibles aussi par le client dans l'application. --}}
@php $photoCount = $order->photos->count(); @endphp
<div id="photos" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 scroll-mt-24" x-data="photosCommande"
     @keydown.escape.window="fermer()" @keydown.arrow-right.window="ouverte !== null && suivante(1)" @keydown.arrow-left.window="ouverte !== null && suivante(-1)">
    <div class="flex items-center justify-between gap-3 mb-1">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Photos des articles @if ($photoCount) <span class="tabular-nums">({{ $photoCount }})</span> @endif</div>
    </div>
    <p class="text-xs text-(--color-text-muted) mb-3">
        Pour retrouver les bons vêtements. Le client les voit aussi dans son application et peut signaler un article manquant ou qui n'est pas à lui.
    </p>

    @if ($photoCount)
        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-3">
            @foreach ($order->photos as $i => $photo)
                <div wire:key="photo-{{ $photo->id }}" class="relative aspect-square rounded-lg overflow-hidden bg-(--color-bg) border border-(--color-border)">
                    <button type="button" @click="voir({{ $i }})" data-photo="{{ $photo->url() }}" class="block w-full h-full" aria-label="Agrandir la photo {{ $i + 1 }}">
                        <img src="{{ $photo->url(thumb: true) }}" alt="" loading="lazy" class="w-full h-full object-cover">
                    </button>
                    @if (\App\Actions\Orders\OrderPhotos::canRemove(auth()->user(), $photo))
                        <button type="button" wire:click="removePhoto({{ $photo->id }})" aria-label="Supprimer la photo {{ $i + 1 }}"
                                data-confirm-title="Supprimer la photo" data-confirm-label="Supprimer" data-confirm-tone="danger"
                                wire:confirm="Supprimer cette photo ? Le client ne la verra plus. Ce sera inscrit au journal."
                                class="tap-zone tap-zone--sm absolute top-1 right-1 w-7 h-7 rounded-full bg-black/55 text-white flex items-center justify-center hover:bg-(--color-error)">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @error('photo') <p class="text-xs text-(--color-error) mb-2">{{ $message }}</p> @enderror

    <div x-show="enCours" x-cloak class="mb-3 text-xs text-(--color-text-secondary)">
        <div class="flex justify-between mb-1"><span x-text="'Envoi de la photo ' + enCours + ' sur ' + total + '…'"></span><span class="tabular-nums" x-text="progression + ' %'"></span></div>
        <div class="h-1.5 rounded-full bg-(--color-bg) overflow-hidden"><div class="h-full bg-(--color-primary) transition-all" :style="'width:' + progression + '%'"></div></div>
    </div>

    @if ($canAddPhotos && $photoCount < $maxPhotos)
        {{-- Appareil photo direct, ou photos déjà prises (galerie) --}}
        <div class="grid grid-cols-2 gap-2">
            <label class="inline-flex items-center justify-center gap-2 h-11 sm:h-10 rounded-lg bg-(--color-primary) text-white text-sm font-semibold cursor-pointer hover:bg-(--color-primary-600)" :class="enCours && 'opacity-60 pointer-events-none'">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3.5"/></svg>
                Photo
                <input type="file" accept="image/*" capture="environment" class="sr-only" @change="envoyer($event)" data-testid="photo-camera">
            </label>
            <label class="inline-flex items-center justify-center gap-2 h-11 sm:h-10 rounded-lg border border-(--color-border) text-sm font-semibold cursor-pointer hover:border-(--color-primary) hover:text-(--color-primary)" :class="enCours && 'opacity-60 pointer-events-none'">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
                Galerie
                <input type="file" accept="image/*" multiple class="sr-only" @change="envoyer($event)" data-testid="photo-gallery">
            </label>
        </div>
    @elseif ($canAddPhotos)
        <p class="text-xs text-(--color-text-muted)">{{ $maxPhotos }} photos au maximum par commande.</p>
    @elseif (! $photoCount)
        <p class="text-sm text-(--color-text-muted)">Aucune photo.</p>
    @endif

    {{-- Visionneuse plein écran --}}
    <template x-teleport="body">
        <div x-show="ouverte !== null" x-cloak x-transition.opacity class="fixed inset-0 z-[80] bg-black/90 flex items-center justify-center" @click.self="fermer()"
             @touchstart.passive="debutGlisse = $event.touches[0].clientX" @touchend="glisser($event.changedTouches[0].clientX)">
            <img :src="ouverte !== null ? urls[ouverte] : ''" alt="" class="max-w-full max-h-full object-contain select-none">
            <button type="button" @click="fermer()" aria-label="Fermer" class="absolute top-3 right-3 w-11 h-11 rounded-full bg-white/15 text-white flex items-center justify-center hover:bg-white/25">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"></path></svg>
            </button>
            <template x-if="urls.length > 1">
                <div>
                    <button type="button" @click="suivante(-1)" aria-label="Photo précédente" class="absolute left-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/15 text-white flex items-center justify-center hover:bg-white/25">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
                    </button>
                    <button type="button" @click="suivante(1)" aria-label="Photo suivante" class="absolute right-2 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/15 text-white flex items-center justify-center hover:bg-white/25">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                    <div class="absolute bottom-4 inset-x-0 text-center text-sm text-white/80 tabular-nums" x-text="(ouverte + 1) + ' / ' + urls.length"></div>
                </div>
            </template>
        </div>
    </template>
</div>
