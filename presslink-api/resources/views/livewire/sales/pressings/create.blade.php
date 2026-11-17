@php
    $input = 'w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $label = 'block text-xs font-medium text-(--color-text-secondary) mb-1.5';
    $error = 'text-xs text-(--color-error) mt-1';
@endphp
<div class="max-w-3xl">
    <h1 class="font-display text-2xl font-bold mb-1">Inscrire un pressing</h1>
    <p class="text-sm text-(--color-text-muted) mb-6">Le pressing a {{ \App\Support\PlanCatalog::trialOffer() }} dès maintenant, toutes les fonctions ouvertes. Il choisit sa formule ensuite.</p>

    @if ($created)
        @php $owner = $created->admins->first(); @endphp
        <div class="p-5 rounded-xl bg-(--color-success-tint) text-(--color-success-text) mb-6" data-testid="pressing-created">
            <p class="font-semibold mb-2">{{ $created->name }} est inscrit et vous est rattaché.</p>
            <p class="text-sm mb-1">Le gérant, {{ $owner?->name }}, se connecte sur <span class="font-semibold">presslink.org/espace/connexion</span> avec son numéro <span class="font-semibold tabular-nums">{{ \App\Support\PhoneNumber::national($owner?->phone) }}</span> et le mot de passe provisoire que vous avez choisi.</p>
            <p class="text-sm">Mois offert jusqu'au {{ $created->subscription?->trial_ends_at?->format('d/m/Y') }}. L'équipe PressLink l'appelle pour l'aider à démarrer.</p>
            <div class="flex flex-wrap gap-2 mt-4">
                <a href="{{ route('sales.pressings.show', $created) }}" wire:navigate class="inline-flex items-center h-9 px-4 rounded-lg bg-(--color-surface) text-(--color-text-primary) text-sm font-semibold border border-(--color-border)">Voir sa fiche</a>
                <button type="button" wire:click="another" class="inline-flex items-center h-9 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Inscrire un autre pressing</button>
            </div>
        </div>
    @else
        <form novalidate wire:submit="create" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
            <div class="sm:col-span-2 text-[11px] font-semibold uppercase tracking-widest text-(--color-text-muted)">Le pressing</div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}" for="pressingName">Nom du pressing</label>
                <input id="pressingName" type="text" wire:model="pressingName" class="{{ $input }}">
                @error('pressingName') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}" for="city">Commune ou quartier</label>
                <input id="city" type="text" wire:model="city" placeholder="Cocody Angré" class="{{ $input }}">
                @error('city') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}" for="pressingPhone">Téléphone du pressing</label>
                <input id="pressingPhone" type="tel" inputmode="tel" wire:model="pressingPhone" placeholder="07 00 00 00 00" class="{{ $input }} tabular-nums">
                @error('pressingPhone') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}" for="address">Adresse ou repère <span class="font-normal text-(--color-text-muted)">(facultatif)</span></label>
                <input id="address" type="text" wire:model="address" placeholder="Face à la pharmacie…" class="{{ $input }}">
                @error('address') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2 mt-2 text-[11px] font-semibold uppercase tracking-widest text-(--color-text-muted)">Le gérant</div>
            <div>
                <label class="{{ $label }}" for="ownerName">Nom du gérant</label>
                <input id="ownerName" type="text" wire:model="ownerName" class="{{ $input }}">
                @error('ownerName') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="{{ $label }}" for="ownerPhone">Son numéro (pour se connecter)</label>
                <input id="ownerPhone" type="tel" inputmode="tel" wire:model="ownerPhone" placeholder="07 00 00 00 00" class="{{ $input }} tabular-nums">
                @error('ownerPhone') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="{{ $label }}" for="password">Mot de passe provisoire</label>
                <input id="password" type="text" wire:model="password" autocomplete="off" class="{{ $input }}">
                <p class="text-[11px] text-(--color-text-muted) mt-1">8 caractères au moins. Donnez-le au gérant : il pourra le changer dans « Mon compte ».</p>
                @error('password') <p class="{{ $error }}">{{ $message }}</p> @enderror
            </div>
            <label class="sm:col-span-2 flex items-start gap-2.5 text-sm cursor-pointer">
                <input type="checkbox" wire:model="termsShown" class="mt-0.5 w-4 h-4 rounded border-(--color-border) accent-(--color-primary)">
                <span>Le gérant a pris connaissance des <a href="{{ route('site.terms') }}" target="_blank" class="font-semibold text-(--color-primary) hover:underline">conditions générales</a> et les accepte.</span>
            </label>
            @error('termsShown') <p class="sm:col-span-2 {{ $error }} -mt-2">{{ $message }}</p> @enderror

            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="create" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold disabled:opacity-60">
                    <span wire:loading.remove wire:target="create">Inscrire le pressing</span>
                    <span wire:loading wire:target="create">Inscription…</span>
                </button>
            </div>
        </form>
    @endif
</div>
