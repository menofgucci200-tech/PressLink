<div>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Services</h1>
        <p class="text-sm text-(--color-text-muted)">Les prestations que vous proposez, et le prix de chaque pièce pour chacune d'elles. Ces prix servent à la création des commandes.</p>
    </div>

    @if ($errorMessage)
        <div class="mb-5 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
    @endif

    {{-- ===================================================== Vos services --}}
    <section class="mb-10">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="font-semibold text-lg">Vos services</h2>
            <button type="button" wire:click="newPrestation" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Ajouter un service
            </button>
        </div>

        @if ($showPrestationForm)
            <form novalidate wire:submit="savePrestation" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-5 animate-step">
                <h3 class="font-semibold mb-4">{{ $editingPrestationId ? 'Modifier le service' : 'Nouveau service' }}</h3>
                <div class="max-w-md mb-5">
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom du service</label>
                    <input type="text" wire:model.live.debounce.300ms="prestationName" maxlength="60" placeholder="Ex. Retouches, Détachage, Express…" autofocus
                           class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('prestationName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <p class="block text-xs font-medium text-(--color-text-secondary) mb-2">Icône</p>
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2 mb-5">
                    @foreach ($serviceIcons as $key => $label)
                        <button type="button" wire:click="choosePrestationIcon('{{ $key }}')" wire:key="pi-{{ $key }}" aria-pressed="{{ $prestationIcon === $key ? 'true' : 'false' }}"
                                class="flex flex-col items-center gap-1.5 rounded-xl border-2 px-2 py-3 text-center transition-colors {{ $prestationIcon === $key ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border) hover:border-(--color-primary)' }}">
                            <x-service-icon kind="service" :name="$key" :size="36"/>
                            <span class="text-[11px] leading-tight text-(--color-text-secondary)">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">{{ $editingPrestationId ? 'Enregistrer' : 'Ajouter le service' }}</button>
                    <button type="button" wire:click="cancelPrestation" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-primary)">Annuler</button>
                </div>
            </form>
        @endif

        @if ($prestations->isEmpty())
            <div class="rounded-xl border border-dashed border-(--color-border) p-8 text-center text-sm text-(--color-text-secondary)">
                Aucun service pour l'instant. Ajoutez par exemple « Repassage » ou « Nettoyage ».
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                @foreach ($prestations as $prestation)
                    <div wire:key="prestation-{{ $prestation->id }}" class="bg-(--color-surface) border border-(--color-border) rounded-2xl p-4 flex flex-col gap-3 {{ $prestation->is_active ? '' : 'opacity-60' }}">
                        <div class="flex items-center gap-3">
                            <x-service-icon kind="service" :name="$prestation->iconKey()" :size="40"/>
                            <div class="min-w-0">
                                <div class="font-semibold truncate">{{ $prestation->name }}</div>
                                <div class="text-xs text-(--color-text-muted)">
                                    @if (! $prestation->is_active) <span class="text-(--color-warning-text) font-medium">Désactivé · </span> @endif
                                    {{ $prestation->prices_count }} prix défini{{ $prestation->prices_count > 1 ? 's' : '' }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-1 pt-3 border-t border-(--color-border)">
                            <div class="flex items-center gap-1">
                                <button type="button" wire:click="movePrestation({{ $prestation->id }}, 'left')" @disabled($loop->first) title="Avancer" aria-label="Avancer {{ $prestation->name }}"
                                        class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary) disabled:opacity-30 disabled:pointer-events-none">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
                                </button>
                                <button type="button" wire:click="movePrestation({{ $prestation->id }}, 'right')" @disabled($loop->last) title="Reculer" aria-label="Reculer {{ $prestation->name }}"
                                        class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary) disabled:opacity-30 disabled:pointer-events-none">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                                </button>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" wire:click="editPrestation({{ $prestation->id }})" title="Modifier" aria-label="Modifier {{ $prestation->name }}"
                                        class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                </button>
                                <button type="button" wire:click="togglePrestation({{ $prestation->id }})" title="{{ $prestation->is_active ? 'Désactiver' : 'Réactiver' }}" aria-label="{{ $prestation->is_active ? 'Désactiver' : 'Réactiver' }} {{ $prestation->name }}"
                                        class="h-8 px-2.5 rounded-lg border border-(--color-border) text-xs font-medium text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary)">{{ $prestation->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                                <button type="button" wire:click="deletePrestation({{ $prestation->id }})" title="Supprimer" aria-label="Supprimer {{ $prestation->name }}"
                                        data-confirm-title="Supprimer ce service" data-confirm-label="Supprimer" data-confirm-tone="danger"
                                        wire:confirm="Supprimer le service « {{ $prestation->name }} » et ses prix ? S'il figure déjà dans des commandes, il sera seulement désactivé."
                                        class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-muted) hover:border-(--color-error) hover:text-(--color-error)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ================================================== Pièces et tarifs --}}
    <section>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
            <h2 class="font-semibold text-lg">Pièces et tarifs</h2>
            <button type="button" wire:click="newPiece" data-guide="ajouter-piece" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-primary) text-(--color-primary) text-sm font-semibold hover:bg-(--color-primary-tint)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Ajouter une pièce
            </button>
        </div>
        <p class="text-sm text-(--color-text-muted) mb-4">Saisissez le prix de chaque pièce pour chaque service. Une case vide : la pièce n'est pas proposée pour ce service. Les prix s'enregistrent dès que vous quittez la case.</p>

        @if ($showPieceForm)
            <form novalidate wire:submit="savePiece" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-5 animate-step">
                <h3 class="font-semibold mb-4">{{ $editingPieceId ? 'Modifier la pièce' : 'Nouvelle pièce' }}</h3>
                <div class="max-w-md mb-5" data-guide="piece-nom">
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom de la pièce</label>
                    <input type="text" wire:model.live.debounce.300ms="pieceName" maxlength="100" placeholder="Ex. Chemise, Robe, Grand boubou…" autofocus
                           class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('pieceName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <p class="block text-xs font-medium text-(--color-text-secondary) mb-2">Icône</p>
                <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-11 gap-2 mb-5">
                    @foreach ($pieceIcons as $key => $label)
                        <button type="button" wire:click="choosePieceIcon('{{ $key }}')" wire:key="pc-{{ $key }}" aria-pressed="{{ $pieceIcon === $key ? 'true' : 'false' }}"
                                class="flex flex-col items-center gap-1 rounded-xl border-2 px-1.5 py-2.5 text-center transition-colors {{ $pieceIcon === $key ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border) hover:border-(--color-primary)' }}">
                            <x-service-icon kind="piece" :name="$key" :size="30"/>
                            <span class="text-[10.5px] leading-tight text-(--color-text-secondary)">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
                @if (! $editingPieceId && $prestations->isNotEmpty())
                    <p class="block text-xs font-medium text-(--color-text-secondary) mb-2">Prix (FCFA) — laissez vide si la pièce n'est pas proposée pour ce service</p>
                    <div class="flex flex-wrap gap-3 mb-5" data-guide="piece-prix">
                        @foreach ($prestations as $prestation)
                            <label class="max-sm:w-full flex items-center gap-2 rounded-xl border border-(--color-border) pl-2 pr-3 py-2" wire:key="np-{{ $prestation->id }}">
                                <x-service-icon kind="service" :name="$prestation->iconKey()" :size="24"/>
                                <span class="max-sm:flex-1 text-sm font-medium">{{ $prestation->name }}</span>
                                <input type="number" min="0" wire:model="newPiecePrices.{{ $prestation->id }}" placeholder="—" class="w-24 h-9 px-2 text-right tabular-nums rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                            </label>
                        @endforeach
                    </div>
                    @error('newPiecePrices.*') <p class="text-xs text-(--color-error) mb-3">{{ $message }}</p> @enderror
                @endif
                <div class="flex gap-2 max-sm:flex-col-reverse">
                    <button type="submit" data-guide="piece-enregistrer" class="max-sm:h-12 h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">{{ $editingPieceId ? 'Enregistrer' : 'Ajouter la pièce' }}</button>
                    <button type="button" wire:click="cancelPiece" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium hover:border-(--color-primary)">Annuler</button>
                </div>
            </form>
        @endif

        @if ($pieces->isEmpty())
            <div class="rounded-xl border border-dashed border-(--color-border) p-8 text-center text-sm text-(--color-text-secondary)">
                Aucune pièce pour l'instant. Ajoutez « Chemise », « Pantalon », « Robe »…
            </div>
        @elseif ($prestations->isEmpty())
            <div class="rounded-xl border border-dashed border-(--color-border) p-8 text-center text-sm text-(--color-text-secondary)">
                Ajoutez d'abord au moins un service pour fixer les prix de vos pièces.
            </div>
        @else
            {{-- Téléphone et tablette : une carte par pièce, un prix par service, les uns sous les autres
                 (deux cartes par ligne sur tablette). La grille complète ne tient que sur grand écran. --}}
            <div class="lg:hidden grid grid-cols-1 md:grid-cols-2 gap-3 items-start">
                @foreach ($pieces as $piece)
                    @php
                        $activeVariants = $piece->variants->where('is_active', true);
                        $hasVariants = $activeVariants->isNotEmpty();
                    @endphp
                    <div wire:key="piece-card-{{ $piece->id }}" class="bg-(--color-surface) border border-(--color-border) rounded-xl {{ $piece->is_active ? '' : 'opacity-55' }}" x-data="{ actions: false }">
                        <div class="flex items-center gap-3 px-4 pt-3.5 pb-2">
                            <x-service-icon kind="piece" :name="$piece->iconKey()" :size="34"/>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold truncate">{{ $piece->name }}</div>
                                @if (! $piece->is_active)
                                    <div class="text-xs text-(--color-warning-text) font-medium">Désactivée</div>
                                @elseif ($hasVariants)
                                    <div class="text-xs text-(--color-text-muted)">{{ $activeVariants->count() }} variante{{ $activeVariants->count() > 1 ? 's' : '' }}</div>
                                @endif
                            </div>
                            <button type="button" @click="actions = !actions" :aria-expanded="actions" aria-label="Actions sur {{ $piece->name }}"
                                    class="w-10 h-10 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                            </button>
                        </div>
                        <div x-show="actions" x-cloak class="grid grid-cols-2 gap-2 px-4 pb-3">
                            <button type="button" wire:click="editPiece({{ $piece->id }})" class="h-10 rounded-lg border border-(--color-border) text-sm font-medium">Modifier</button>
                            <button type="button" wire:click="newVariant({{ $piece->id }})" class="h-10 rounded-lg border border-(--color-border) text-sm font-medium">+ Variante</button>
                            <button type="button" wire:click="togglePiece({{ $piece->id }})" class="h-10 rounded-lg border border-(--color-border) text-sm font-medium">{{ $piece->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                            <button type="button" wire:click="deletePiece({{ $piece->id }})"
                                    data-confirm-title="Supprimer cette pièce" data-confirm-label="Supprimer" data-confirm-tone="danger"
                                    wire:confirm="Supprimer la pièce « {{ $piece->name }} » et tous ses prix ? Si elle figure déjà dans des commandes, elle sera seulement désactivée."
                                    class="h-10 rounded-lg border border-(--color-error)/30 text-sm font-medium text-(--color-error)">Supprimer</button>
                        </div>
                        <div class="border-t border-(--color-border) divide-y divide-(--color-border)">
                            @if (! $hasVariants)
                                @foreach ($prestations as $prestation)
                                    <div class="flex items-center gap-3 px-4 py-2 {{ $prestation->is_active ? '' : 'opacity-50' }}" wire:key="mcell-{{ $piece->id }}-0-{{ $prestation->id }}">
                                        <x-service-icon kind="service" :name="$prestation->iconKey()" :size="24"/>
                                        <span class="flex-1 text-sm">{{ $prestation->name }}</span>
                                        @include('livewire.services._price-input', ['piece' => $piece, 'variant' => null, 'prestation' => $prestation, 'value' => $prices[$piece->id][0][$prestation->id] ?? null])
                                    </div>
                                @endforeach
                            @endif
                            @foreach ($piece->variants as $variant)
                                <div class="px-4 py-2.5 bg-(--color-bg)/60 {{ $variant->is_active ? '' : 'opacity-55' }}" wire:key="mvariant-{{ $variant->id }}">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <span class="text-sm font-semibold">{{ $variant->name }}@if (! $variant->is_active) <span class="text-xs text-(--color-warning-text) font-medium"> · désactivée</span>@endif</span>
                                        <button type="button" wire:click="deleteVariant({{ $variant->id }})" aria-label="Supprimer {{ $variant->name }}"
                                                data-confirm-title="Supprimer cette variante" data-confirm-label="Supprimer" data-confirm-tone="danger"
                                                wire:confirm="Supprimer la variante « {{ $variant->name }} » ? Si elle figure déjà dans des commandes, elle sera seulement désactivée."
                                                class="text-xs font-medium text-(--color-text-muted) hover:text-(--color-error)">Supprimer</button>
                                    </div>
                                    @foreach ($prestations as $prestation)
                                        <div class="flex items-center gap-3 py-1 {{ $prestation->is_active ? '' : 'opacity-50' }}" wire:key="mcell-{{ $piece->id }}-{{ $variant->id }}-{{ $prestation->id }}">
                                            <x-service-icon kind="service" :name="$prestation->iconKey()" :size="22"/>
                                            <span class="flex-1 text-sm">{{ $prestation->name }}</span>
                                            @include('livewire.services._price-input', ['piece' => $piece, 'variant' => $variant, 'prestation' => $prestation, 'value' => $prices[$piece->id][$variant->id][$prestation->id] ?? null])
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                            @if ($variantFormPieceId === $piece->id)
                                <form novalidate wire:submit="saveVariant" class="px-4 py-3 flex flex-col gap-2" wire:key="mvariant-form-{{ $piece->id }}">
                                    <input type="text" wire:model="variantName" maxlength="100" placeholder="Nom de la variante (ex. Manche longue)" autofocus
                                           class="w-full h-11 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                                    @error('variantName') <p class="text-xs text-(--color-error)">{{ $message }}</p> @enderror
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" wire:click="cancelVariant" class="h-10 rounded-lg border border-(--color-border) text-sm">Annuler</button>
                                        <button type="submit" class="h-10 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Ajouter</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="max-lg:hidden bg-(--color-surface) border border-(--color-border) rounded-xl overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-(--color-border) text-left">
                            <th class="sticky left-0 z-10 bg-(--color-surface) px-4 py-3 font-semibold min-w-56">Pièce</th>
                            @foreach ($prestations as $prestation)
                                <th class="px-3 py-3 text-center font-semibold min-w-32 {{ $prestation->is_active ? '' : 'opacity-50' }}">
                                    <div class="flex flex-col items-center gap-1">
                                        <x-service-icon kind="service" :name="$prestation->iconKey()" :size="26"/>
                                        <span class="text-xs">{{ $prestation->name }}</span>
                                    </div>
                                </th>
                            @endforeach
                            <th class="px-3 py-3 w-px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pieces as $piece)
                            @php
                                $activeVariants = $piece->variants->where('is_active', true);
                                $hasVariants = $activeVariants->isNotEmpty();
                            @endphp
                            <tr wire:key="piece-{{ $piece->id }}" class="border-b border-(--color-border) {{ $piece->is_active ? '' : 'opacity-55' }}">
                                <td class="sticky left-0 z-10 bg-(--color-surface) px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-service-icon kind="piece" :name="$piece->iconKey()" :size="30"/>
                                        <div class="min-w-0">
                                            <div class="font-semibold truncate">{{ $piece->name }}</div>
                                            @if (! $piece->is_active)
                                                <div class="text-xs text-(--color-warning-text) font-medium">Désactivée</div>
                                            @elseif ($hasVariants)
                                                <div class="text-xs text-(--color-text-muted)">{{ $activeVariants->count() }} variante{{ $activeVariants->count() > 1 ? 's' : '' }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                @if ($hasVariants)
                                    <td colspan="{{ $prestations->count() }}" class="px-3 py-3 text-center text-xs text-(--color-text-muted)">Prix selon la variante, ci-dessous</td>
                                @else
                                    @foreach ($prestations as $prestation)
                                        <td class="px-3 py-2.5 text-center" wire:key="cell-{{ $piece->id }}-0-{{ $prestation->id }}">
                                            @include('livewire.services._price-input', ['piece' => $piece, 'variant' => null, 'prestation' => $prestation, 'value' => $prices[$piece->id][0][$prestation->id] ?? null])
                                        </td>
                                    @endforeach
                                @endif
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" wire:click="newVariant({{ $piece->id }})" title="Ajouter une variante (ex. manche longue)"
                                                class="h-8 px-2.5 rounded-lg border border-(--color-border) text-xs font-medium text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary) whitespace-nowrap">+ Variante</button>
                                        <button type="button" wire:click="editPiece({{ $piece->id }})" title="Modifier" aria-label="Modifier {{ $piece->name }}"
                                                class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                        </button>
                                        <button type="button" wire:click="togglePiece({{ $piece->id }})" title="{{ $piece->is_active ? 'Désactiver' : 'Réactiver' }}" aria-label="{{ $piece->is_active ? 'Désactiver' : 'Réactiver' }} {{ $piece->name }}"
                                                class="h-8 px-2.5 rounded-lg border border-(--color-border) text-xs font-medium text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary)">{{ $piece->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                                        <button type="button" wire:click="deletePiece({{ $piece->id }})" title="Supprimer" aria-label="Supprimer {{ $piece->name }}"
                                                data-confirm-title="Supprimer cette pièce" data-confirm-label="Supprimer" data-confirm-tone="danger"
                                                wire:confirm="Supprimer la pièce « {{ $piece->name }} » et tous ses prix ? Si elle figure déjà dans des commandes, elle sera seulement désactivée."
                                                class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-muted) hover:border-(--color-error) hover:text-(--color-error)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            @foreach ($piece->variants as $variant)
                                <tr wire:key="variant-{{ $variant->id }}" class="border-b border-(--color-border) bg-(--color-bg)/60 {{ $variant->is_active ? '' : 'opacity-55' }}">
                                    <td class="sticky left-0 z-10 bg-(--color-bg) px-4 py-2.5 pl-16">
                                        <span class="text-sm">{{ $variant->name }}</span>
                                        @if (! $variant->is_active) <span class="text-xs text-(--color-warning-text) font-medium"> · désactivée</span> @endif
                                    </td>
                                    @foreach ($prestations as $prestation)
                                        <td class="px-3 py-2 text-center" wire:key="cell-{{ $piece->id }}-{{ $variant->id }}-{{ $prestation->id }}">
                                            @include('livewire.services._price-input', ['piece' => $piece, 'variant' => $variant, 'prestation' => $prestation, 'value' => $prices[$piece->id][$variant->id][$prestation->id] ?? null])
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" wire:click="deleteVariant({{ $variant->id }})" title="Supprimer la variante" aria-label="Supprimer {{ $variant->name }}"
                                                data-confirm-title="Supprimer cette variante" data-confirm-label="Supprimer" data-confirm-tone="danger"
                                                wire:confirm="Supprimer la variante « {{ $variant->name }} » ? Si elle figure déjà dans des commandes, elle sera seulement désactivée."
                                                class="w-8 h-8 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-muted) hover:border-(--color-error) hover:text-(--color-error) ml-auto">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach

                            @if ($variantFormPieceId === $piece->id)
                                <tr wire:key="variant-form-{{ $piece->id }}" class="border-b border-(--color-border) bg-(--color-bg)/60">
                                    <td colspan="{{ $prestations->count() + 2 }}" class="px-4 py-3 pl-16">
                                        <form novalidate wire:submit="saveVariant" class="flex flex-wrap items-start gap-2">
                                            <div>
                                                <input type="text" wire:model="variantName" maxlength="100" placeholder="Nom de la variante (ex. Manche longue)" autofocus
                                                       class="w-72 max-w-full h-9 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                                                @error('variantName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                                            </div>
                                            <button type="submit" class="h-9 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Ajouter</button>
                                            <button type="button" wire:click="cancelVariant" class="h-9 px-3 rounded-lg border border-(--color-border) text-sm hover:border-(--color-primary)">Annuler</button>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
