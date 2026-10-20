{{-- Conditions et règlement du pressing, puis droit de magasinage (Pressing\Settings). --}}
@php $input = 'h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)'; @endphp

<div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
    <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Conditions et règlement</div>
    <p class="text-xs text-(--color-text-muted) mb-3">Affichés aux clients dans l'application (fiche du pressing) et imprimés en bas des reçus.</p>
    <textarea wire:model="terms" rows="7" maxlength="5000"
              placeholder="Ex.&#10;- Les vêtements sont rendus sur présentation du reçu.&#10;- Le pressing n'est pas responsable des objets laissés dans les poches.&#10;- Toute réclamation doit être faite au moment du retrait.&#10;- Les vêtements non retirés après 90 jours sont considérés comme abandonnés."
              class="w-full px-3 py-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm leading-relaxed focus:outline-none focus:border-(--color-primary)"></textarea>
    @error('terms') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
    <p class="text-xs text-(--color-text-muted) mt-1">Une règle par ligne. 5 000 caractères au plus.</p>
</div>

<div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
    <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Droit de magasinage</div>
    <label class="flex items-start gap-3 cursor-pointer">
        <input type="checkbox" wire:model.live="storageFeeEnabled" class="mt-0.5 rounded border-(--color-border)">
        <span class="text-sm">
            Facturer le magasinage des commandes prêtes non retirées
            <span class="block text-xs text-(--color-text-muted) mt-0.5">Le montant s'ajoute automatiquement à la remise de la commande. Le client le voit dans l'application et sur la fiche du pressing.</span>
        </span>
    </label>

    @if ($storageFeeEnabled)
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 animate-step">
            <div>
                <label for="storage-grace" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Délai gratuit après la date de retrait prévue</label>
                <div class="flex items-center gap-2">
                    <input id="storage-grace" type="number" inputmode="numeric" min="0" max="365" wire:model.live.debounce.400ms="storageFeeGraceDays" class="{{ $input }} w-24">
                    <span class="text-sm text-(--color-text-secondary)">jour(s)</span>
                </div>
                @error('storageFeeGraceDays') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="storage-amount" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Montant</label>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative">
                        <input id="storage-amount" type="number" inputmode="numeric" min="1" step="25" wire:model.live.debounce.400ms="storageFeeAmount" placeholder="200" class="{{ $input }} w-32 pr-8 tabular-nums">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">F</span>
                    </div>
                    <select wire:model.live="storageFeePeriod" aria-label="Période" class="{{ $input }}">
                        <option value="day">par jour</option>
                        <option value="week">par semaine</option>
                        <option value="month">par mois</option>
                    </select>
                    <select wire:model.live="storageFeeUnit" aria-label="Calcul" class="{{ $input }}">
                        <option value="order">par commande</option>
                        <option value="piece">par pièce</option>
                    </select>
                </div>
                @error('storageFeeAmount') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="storage-cap" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Plafond par commande (facultatif)</label>
                <div class="relative w-40">
                    <input id="storage-cap" type="number" inputmode="numeric" min="1" step="100" wire:model.live.debounce.400ms="storageFeeCap" placeholder="Aucun" class="{{ $input }} w-40 pr-8 tabular-nums">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">F</span>
                </div>
                @error('storageFeeCap') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($storageFeePreview)
            <div class="mt-4 rounded-lg bg-(--color-bg) px-4 py-3 text-sm">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1">Ce que lira le client</div>
                {{ $storageFeePreview }}
            </div>
        @endif
    @endif
</div>
