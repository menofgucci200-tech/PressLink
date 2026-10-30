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
        <input type="checkbox" wire:model.live="storageFeeEnabled" class="mt-0.5 w-5 h-5 sm:w-4 sm:h-4 flex-none rounded border-(--color-border)">
        <span class="text-sm">
            Facturer le magasinage des commandes prêtes non retirées
            <span class="block text-xs text-(--color-text-muted) mt-0.5">Le montant s'ajoute automatiquement à la remise de la commande. Le client le voit dans l'application et sur la fiche du pressing.</span>
        </span>
    </label>

    @if ($storageFeeEnabled)
        @php
            $perLabel = ['day' => 'jour', 'week' => 'semaine', 'month' => 'mois'][$storageFeePeriod] ?? 'jour';
            $modes = [
                'flat' => ['Montant unique', 'Le même montant pour toutes les commandes.'],
                'per_piece' => ['Selon la pièce', 'Un montant pour chaque pièce (costume, chemise…).'],
                'tiers' => ['Par paliers', 'Le montant change avec la durée (ex. plus cher après 2 semaines).'],
            ];
        @endphp
        <div class="mt-4 flex flex-col gap-4 animate-step">
            {{-- Façon de calculer : au choix du pressing --}}
            <div>
                <div class="text-xs font-medium text-(--color-text-secondary) mb-1.5">Calcul</div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2" role="radiogroup" aria-label="Calcul du droit de magasinage">
                    @foreach ($modes as $value => [$title, $hint])
                        <button type="button" role="radio" aria-checked="{{ $storageFeeMode === $value ? 'true' : 'false' }}" wire:click="$set('storageFeeMode', '{{ $value }}')" wire:key="mode-{{ $value }}"
                                class="text-left p-3 rounded-xl border transition-colors {{ $storageFeeMode === $value ? 'border-(--color-primary) bg-(--color-primary-tint) shadow-[0_0_0_1px_var(--color-primary)]' : 'border-(--color-border) hover:border-(--color-text-muted)' }}">
                            <span class="block text-sm font-semibold">{{ $title }}</span>
                            <span class="block text-xs text-(--color-text-muted) mt-0.5">{{ $hint }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="storage-grace" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Délai gratuit après la date de retrait prévue</label>
                    <div class="flex items-center gap-2">
                        <input id="storage-grace" type="number" inputmode="numeric" min="0" max="365" wire:model.live.debounce.400ms="storageFeeGraceDays" class="{{ $input }} w-24">
                        <span class="text-sm text-(--color-text-secondary)">jour(s)</span>
                    </div>
                    @error('storageFeeGraceDays') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Facturé</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <select wire:model.live="storageFeePeriod" aria-label="Période" class="{{ $input }}">
                            <option value="day">par jour</option>
                            <option value="week">par semaine</option>
                            <option value="month">par mois</option>
                        </select>
                        @if ($storageFeeMode !== 'per_piece')
                            <select wire:model.live="storageFeeUnit" aria-label="Calcul" class="{{ $input }}">
                                <option value="order">par commande</option>
                                <option value="piece">par pièce</option>
                            </select>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Montant unique --}}
            @if ($storageFeeMode === 'flat')
                <div>
                    <label for="storage-amount" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Montant par {{ $perLabel }}</label>
                    <div class="relative w-40">
                        <input id="storage-amount" type="number" inputmode="numeric" min="1" wire:model.live.debounce.400ms="storageFeeAmount" placeholder="Ex. 200" class="{{ $input }} w-40 pr-8 tabular-nums">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">F</span>
                    </div>
                    @error('storageFeeAmount') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            {{-- Selon la pièce --}}
            @if ($storageFeeMode === 'per_piece')
                <div>
                    <div class="text-xs font-medium text-(--color-text-secondary) mb-1.5">Montant par {{ $perLabel }} et par pièce</div>
                    @if ($pieces->isEmpty())
                        <p class="text-sm text-(--color-text-muted)">Ajoutez d'abord vos pièces dans Services.</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
                            @foreach ($pieces as $piece)
                                <label wire:key="fee-piece-{{ $piece->id }}" class="flex items-center gap-3">
                                    <x-service-icon kind="piece" :name="$piece->iconKey()" :size="26"/>
                                    <span class="flex-1 min-w-0 text-sm truncate">{{ $piece->name }}</span>
                                    <span class="relative">
                                        <input type="number" inputmode="numeric" min="0" step="25" wire:model.live.debounce.400ms="storageFeePieceAmounts.{{ $piece->id }}" placeholder="—" aria-label="Montant pour {{ $piece->name }}"
                                               class="h-9 w-28 pl-3 pr-7 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums">
                                        <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">F</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <label class="mt-3 flex items-center gap-3">
                        <span class="flex-1 text-sm text-(--color-text-secondary)">Autres pièces (hors liste, sans montant)</span>
                        <span class="relative">
                            <input type="number" inputmode="numeric" min="0" step="25" wire:model.live.debounce.400ms="storageFeeDefaultAmount" placeholder="0" aria-label="Montant pour les autres pièces"
                                   class="h-9 w-28 pl-3 pr-7 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums">
                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">F</span>
                        </span>
                    </label>
                </div>
            @endif

            {{-- Par paliers --}}
            @if ($storageFeeMode === 'tiers')
                <div>
                    <div class="text-xs font-medium text-(--color-text-secondary) mb-1.5">Paliers (après le délai gratuit)</div>
                    <div class="flex flex-col gap-2">
                        @foreach ($storageFeeTiers as $i => $tier)
                            @php $isLast = $loop->last; @endphp
                            <div wire:key="tier-{{ $i }}" class="flex flex-wrap items-center gap-2 text-sm">
                                @if ($isLast)
                                    <span class="w-44 text-(--color-text-secondary)">{{ $loop->first ? 'Tout le temps' : 'Ensuite' }}</span>
                                @else
                                    <span class="text-(--color-text-secondary)">{{ $loop->first ? 'Pendant' : 'Puis pendant' }}</span>
                                    <input type="number" inputmode="numeric" min="1" wire:model.live.debounce.400ms="storageFeeTiers.{{ $i }}.periods" aria-label="Nombre de périodes"
                                           class="h-9 w-16 px-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums">
                                    <span class="text-(--color-text-secondary)">{{ ['day' => 'jour(s)', 'week' => 'semaine(s)', 'month' => 'mois'][$storageFeePeriod] ?? 'jour(s)' }}</span>
                                @endif
                                <span class="text-(--color-text-secondary)">:</span>
                                <span class="relative">
                                    <input type="number" inputmode="numeric" min="0" step="25" wire:model.live.debounce.400ms="storageFeeTiers.{{ $i }}.amount" aria-label="Montant du palier"
                                           class="h-9 w-28 pl-3 pr-7 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums">
                                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">F</span>
                                </span>
                                <span class="text-(--color-text-secondary)">par {{ $perLabel }}</span>
                                @if (! $isLast && count($storageFeeTiers) > 1)
                                    <button type="button" wire:click="removeTier({{ $i }})" class="text-xs text-(--color-text-muted) hover:text-(--color-error) hover:underline">Retirer</button>
                                @endif
                            </div>
                            @error("storageFeeTiers.$i.amount") <p class="text-xs text-(--color-error)">{{ $message }}</p> @enderror
                        @endforeach
                    </div>
                    <button type="button" wire:click="addTier" class="mt-2 text-[13px] font-semibold text-(--color-primary) hover:underline">+ Ajouter un palier</button>
                </div>
            @endif

            <div>
                <label for="storage-cap" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Plafond par commande (facultatif)</label>
                <div class="relative w-40">
                    <input id="storage-cap" type="number" inputmode="numeric" min="1" wire:model.live.debounce.400ms="storageFeeCap" placeholder="Aucun" class="{{ $input }} w-40 pr-8 tabular-nums">
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
