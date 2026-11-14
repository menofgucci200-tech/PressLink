@php
    $card = 'bg-(--color-surface) border border-(--color-border) rounded-xl p-5';
    $sectionTitle = 'text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-4';
    $label = 'block text-xs font-medium text-(--color-text-secondary) mb-1.5';
    $input = 'w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)';
    $checkbox = 'mt-0.5 w-5 h-5 sm:w-4 sm:h-4 flex-none rounded border-(--color-border)';
@endphp
<div class="max-w-3xl">
    <div class="mb-6">
        <a href="{{ route('pressing.settings') }}" wire:navigate class="text-sm text-(--color-text-muted) hover:text-(--color-primary)">← Paramètres</a>
        <h1 class="font-display text-2xl font-bold mt-1 mb-1">Ramassage et livraison</h1>
        <p class="text-sm text-(--color-text-muted)">Vos clients envoient leur commande depuis l'appli PressLink : vous passez chercher le linge chez eux, et vous le livrez quand il est prêt. Les frais sont libres.</p>
    </div>

    @unless ($launched)
        <div class="mb-6 rounded-xl border border-(--color-info) bg-(--color-info-tint) px-4 py-3 text-sm text-(--color-text-primary)" data-testid="delivery-not-launched">
            <span class="font-semibold">Pas encore visible par vos clients.</span>
            Le service s'ouvrira bientôt dans l'appli PressLink. Préparez vos réglages dès maintenant : ils seront prêts le jour du lancement.
        </div>
    @endunless

    <form novalidate wire:submit="save" class="flex flex-col gap-6">
        <div class="{{ $card }}">
            <div class="{{ $sectionTitle }}">Services proposés</div>
            <div class="flex flex-col gap-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="pickupEnabled" class="{{ $checkbox }}" data-testid="pickup-enabled">
                    <span class="text-sm">
                        Ramassage chez le client
                        <span class="block text-xs text-(--color-text-muted) mt-0.5">Le client envoie sa commande (avec ou sans photos), vous passez la chercher sur le créneau convenu.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="deliveryEnabled" class="{{ $checkbox }}" data-testid="delivery-enabled">
                    <span class="text-sm">
                        Livraison quand c'est prêt
                        <span class="block text-xs text-(--color-text-muted) mt-0.5">Le client choisit de se faire livrer au lieu de passer au pressing. Il paie à la livraison (espèces ou mobile money).</span>
                    </span>
                </label>
            </div>
        </div>

        <div class="{{ $card }}">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Communes desservies</div>
                <span class="text-xs text-(--color-text-muted)">{{ count($communes) }} cochée{{ count($communes) > 1 ? 's' : '' }}</span>
            </div>
            <p class="text-xs text-(--color-text-muted) -mt-2 mb-4">Seuls les clients de ces communes pourront vous envoyer une commande.</p>
            @foreach ($communeGroups as $group => $names)
                <div class="mb-4 last:mb-0">
                    <div class="text-xs font-semibold mb-2">{{ $group }}</div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2">
                        @foreach ($names as $commune)
                            <label class="flex items-center gap-2 text-sm cursor-pointer" wire:key="commune-{{ $commune }}">
                                <input type="checkbox" value="{{ $commune }}" wire:model.live="communes" class="w-5 h-5 sm:w-4 sm:h-4 flex-none rounded border-(--color-border)">
                                <span class="truncate">{{ $commune }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
            @error('communes') <p class="text-xs text-(--color-error) mt-2">{{ $message }}</p> @enderror
            @error('communes.*') <p class="text-xs text-(--color-error) mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="{{ $card }}">
            <div class="{{ $sectionTitle }}">Frais</div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="pickup-fee" class="{{ $label }}">Ramassage (F CFA)</label>
                    <input id="pickup-fee" type="number" inputmode="numeric" min="0" step="100" wire:model="pickupFee" class="{{ $input }}">
                    @error('pickupFee') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="delivery-fee" class="{{ $label }}">Livraison (F CFA)</label>
                    <input id="delivery-fee" type="number" inputmode="numeric" min="0" step="100" wire:model="deliveryFee" class="{{ $input }}">
                    @error('deliveryFee') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="free-from" class="{{ $label }}">Gratuit dès (facultatif)</label>
                    <input id="free-from" type="number" inputmode="numeric" min="0" step="500" wire:model="freeFrom" placeholder="ex. 10000" class="{{ $input }}">
                    @error('freeFrom') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-(--color-text-muted) mt-3">0 = gratuit. « Gratuit dès » : pas de frais de ramassage ni de livraison quand la commande atteint ce montant.</p>
        </div>

        <div class="{{ $card }}">
            <div class="{{ $sectionTitle }}">Créneaux et délai</div>
            <p class="text-xs text-(--color-text-muted) -mt-2 mb-3">Créneaux proposés au client pour le ramassage et la livraison, les jours où vous êtes ouvert.</p>
            <div class="flex flex-col gap-2">
                @foreach ($timeSlots as $index => $slot)
                    <div class="flex flex-wrap items-center gap-2" wire:key="slot-{{ $index }}">
                        <input type="time" wire:model="timeSlots.{{ $index }}.from" aria-label="Début du créneau {{ $index + 1 }}" class="h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                        <span class="text-sm text-(--color-text-muted)">à</span>
                        <input type="time" wire:model="timeSlots.{{ $index }}.to" aria-label="Fin du créneau {{ $index + 1 }}" class="h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                        @if (count($timeSlots) > 1)
                            <button type="button" wire:click="removeSlot({{ $index }})" class="h-10 px-3 text-sm text-(--color-text-muted) hover:text-(--color-error)" aria-label="Retirer le créneau {{ $index + 1 }}">Retirer</button>
                        @endif
                        @error('timeSlots.'.$index.'.from') <p class="w-full text-xs text-(--color-error)">{{ $message }}</p> @enderror
                        @error('timeSlots.'.$index.'.to') <p class="w-full text-xs text-(--color-error)">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
            @if (count($timeSlots) < $maxSlots)
                <button type="button" wire:click="addSlot" class="mt-3 text-sm font-medium text-(--color-primary) hover:underline">+ Ajouter un créneau</button>
            @endif
            @error('timeSlots') <p class="text-xs text-(--color-error) mt-2">{{ $message }}</p> @enderror

            <div class="mt-5 sm:max-w-xs">
                <label for="turnaround" class="{{ $label }}">Délai habituel avant que ce soit prêt (heures, facultatif)</label>
                <input id="turnaround" type="number" inputmode="numeric" min="1" wire:model="turnaroundHours" placeholder="ex. 48" class="{{ $input }}">
                @error('turnaroundHours') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end max-lg:sticky max-lg:bottom-[calc(4.75rem+env(safe-area-inset-bottom))] max-lg:z-10 max-lg:-mx-1 max-lg:p-1 max-lg:rounded-xl max-lg:bg-(--color-bg)/90 max-lg:backdrop-blur">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="max-lg:w-full max-lg:h-12 h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
