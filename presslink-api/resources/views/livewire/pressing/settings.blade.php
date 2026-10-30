@php
    $dayLabels = [
        'lundi' => 'Lundi',
        'mardi' => 'Mardi',
        'mercredi' => 'Mercredi',
        'jeudi' => 'Jeudi',
        'vendredi' => 'Vendredi',
        'samedi' => 'Samedi',
        'dimanche' => 'Dimanche',
    ];
@endphp
<div class="max-w-3xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Paramètres</h1>
        <p class="text-sm text-(--color-text-muted)">
            Tout le paramétrage de votre pressing. Code pressing : <span class="font-mono font-semibold">{{ $pressing->code }}</span>
            <span class="text-(--color-text-muted)">— à transmettre aux clients pour rejoindre votre établissement.</span>
        </p>
    </div>

    <form novalidate wire:submit="save" class="flex flex-col gap-6">
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5" data-guide="logo">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-4">Logo du pressing</div>
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-lg bg-(--color-bg) border border-(--color-border) flex items-center justify-center overflow-hidden">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="Aperçu du logo" class="w-full h-full object-cover">
                    @elseif ($pressing->logo_url)
                        <img src="{{ $pressing->logo_url }}" alt="Logo actuel" class="w-full h-full object-cover">
                    @else
                        <span class="text-xs text-(--color-text-muted)">Aucun</span>
                    @endif
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg border border-(--color-border) text-sm font-semibold cursor-pointer hover:border-(--color-primary) hover:text-(--color-primary)">
                        <span wire:loading.remove wire:target="logo">{{ $pressing->logo_url ? 'Changer le logo' : 'Choisir un logo' }}</span>
                        <span wire:loading wire:target="logo">Envoi…</span>
                        <input type="file" wire:model="logo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                    </label>
                    <span class="text-xs text-(--color-text-muted)">Affiché aux clients dans l'application et sur les reçus.</span>
                    @error('logo') <p class="text-xs text-(--color-error)">{{ $message }}</p> @enderror
                    @if ($pressing->logo_url)
                        <button type="button" wire:click="removeLogo" data-confirm-title="Retirer le logo" data-confirm-label="Retirer" data-confirm-tone="danger" wire:confirm="Le logo ne sera plus affiché à vos clients dans l'application. Vous pourrez en envoyer un nouveau à tout moment." class="text-xs font-medium text-(--color-error) text-left">Retirer le logo actuel</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Informations générales</div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom du pressing</label>
                <input type="text" wire:model="name" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone</label>
                <x-phone-input wire:model="phone" placeholder="07 08 12 44 00 ou 27 22 44 55 66" />
                @error('phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Email</label>
                <input type="email" wire:model="email" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('email') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Ville</label>
                <input type="text" wire:model="city" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('city') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Adresse</label>
                <input type="text" wire:model="address" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('address') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Description</label>
                <textarea wire:model="description" rows="3" class="w-full px-3 py-2 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)"></textarea>
                @error('description') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Contact du pressing : affiché sur la fiche du pressing et sous chaque commande dans l'appli --}}
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Contact du pressing</div>
            <p class="text-xs text-(--color-text-muted) mb-4">Vos clients vous joignent avec ces informations : sur la fiche de votre pressing et avec le bouton « Contacter le pressing » de chaque commande. Sans téléphone ici, c'est le téléphone principal qui est utilisé.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone (appels)</label>
                    <x-phone-input wire:model="supportPhone" placeholder="07 08 12 44 00 ou 27 22 44 55 66" />
                    @error('supportPhone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">WhatsApp (mobile)</label>
                    <x-phone-input wire:model="supportWhatsapp" />
                    @error('supportWhatsapp') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="support-email" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">E-mail</label>
                    <input id="support-email" type="email" wire:model="supportEmail" placeholder="contact@monpressing.ci" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('supportEmail') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="support-hours" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Disponibilités (facultatif)</label>
                    <input id="support-hours" type="text" wire:model="supportHours" maxlength="150" placeholder="Ex. Du lundi au samedi, 8 h – 19 h" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('supportHours') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5" data-guide="horaires">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Horaires d'ouverture</div>
            @if ($untouchedDefaultHours !== null)
                <p class="text-xs text-(--color-text-muted) mb-4">Aucun horaire enregistré : le pressing est ouvert à toute heure. Modifiez un jour pour enregistrer vos horaires.</p>
            @else
                <div class="mb-4"></div>
            @endif
            <div class="flex flex-col gap-2.5">
                @foreach ($days as $day)
                    {{-- Téléphone : le jour et « Fermé » sur une ligne, les deux heures en dessous, pleine largeur --}}
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 max-sm:pb-2.5 max-sm:border-b max-sm:border-(--color-border) max-sm:last:border-0" x-data="{ ferme: @js((bool) $openingHours[$day]['closed']) }">
                        <label class="max-sm:flex-1 w-24 text-sm font-medium flex-none">{{ $dayLabels[$day] }}</label>
                        <label class="flex items-center gap-2 text-sm sm:text-xs text-(--color-text-secondary) flex-none sm:w-24 cursor-pointer">
                            <input type="checkbox" wire:model="openingHours.{{ $day }}.closed" x-model="ferme" class="w-5 h-5 sm:w-4 sm:h-4 rounded border-(--color-border)">
                            Fermé
                        </label>
                        <div class="max-sm:basis-full flex items-center gap-2" :class="ferme && 'opacity-40'">
                            <input type="time" wire:model="openingHours.{{ $day }}.open" :disabled="ferme" aria-label="{{ $dayLabels[$day] }} : ouverture"
                                   class="max-sm:flex-1 h-10 sm:h-9 px-2.5 rounded-lg border border-(--color-border) text-sm">
                            <span class="text-(--color-text-muted)">—</span>
                            <input type="time" wire:model="openingHours.{{ $day }}.close" :disabled="ferme" aria-label="{{ $dayLabels[$day] }} : fermeture"
                                   class="max-sm:flex-1 h-10 sm:h-9 px-2.5 rounded-lg border border-(--color-border) text-sm">
                        </div>
                        @error("openingHours.{$day}.close") <p class="basis-full text-xs text-(--color-error)">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-4">Grille tarifaire</div>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" wire:model="showPricingToCustomers" class="mt-0.5 w-5 h-5 sm:w-4 sm:h-4 flex-none rounded border-(--color-border)">
                <span class="text-sm">
                    Afficher ma grille tarifaire à mes clients
                    <span class="block text-xs text-(--color-text-muted) mt-0.5">Vos clients pourront consulter les prix de vos services depuis l'application mobile, sur la fiche de votre pressing.</span>
                </span>
            </label>
        </div>

        @include('livewire.pressing._terms-and-storage')

        {{-- Téléphone et tablette : « Enregistrer » reste visible en bas de l'écran pendant le défilement --}}
        <div class="flex justify-end max-lg:sticky max-lg:bottom-[calc(4.75rem+env(safe-area-inset-bottom))] max-lg:z-10 max-lg:-mx-1 max-lg:p-1 max-lg:rounded-xl max-lg:bg-(--color-bg)/90 max-lg:backdrop-blur">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" data-guide="parametres-enregistrer"
                    class="max-lg:w-full max-lg:h-12 h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
