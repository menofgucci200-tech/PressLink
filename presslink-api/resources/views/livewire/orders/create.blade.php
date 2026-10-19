<div class="max-w-3xl mx-auto" data-skip-back>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Nouvelle commande</h1>
        <p class="text-sm text-(--color-text-muted)">Enregistrez un dépôt en quatre étapes : client, services et pièces, détails et paiement, confirmation.</p>
    </div>

    <div class="flex items-center mb-7">
        @php $labels = [1 => 'Client', 2 => 'Services et pièces', 3 => 'Détails et paiement', 4 => 'Confirmation']; @endphp
        @foreach ($labels as $n => $label)
            <div class="flex items-center flex-1 min-w-0 last:flex-none">
                <div class="flex items-center gap-2.5 flex-none">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-[13px] font-semibold transition-all duration-300 {{ $n === $step ? 'scale-110' : '' }}
                        {{ $n < $step ? 'bg-(--color-success) text-white' : ($n === $step ? 'bg-(--color-primary) text-white' : 'bg-(--color-border) text-(--color-text-muted)') }}">
                        @if ($n < $step) ✓ @else {{ $n }} @endif
                    </div>
                    <span class="text-[13.5px] font-medium transition-colors duration-300 {{ $n === $step ? '' : 'hidden sm:inline' }} {{ $n <= $step ? 'text-(--color-text-primary)' : 'text-(--color-text-muted)' }}">{{ $label }}</span>
                </div>
                @if ($n < 4)
                    <div class="flex-1 h-px mx-2 sm:mx-3.5 bg-(--color-border) overflow-hidden">
                        <div class="h-full bg-(--color-success) transition-all duration-500 ease-out" style="width:{{ $n < $step ? '100' : '0' }}%"></div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div wire:key="wizard-step-{{ $step }}" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-7 animate-step">

        {{-- Étape 1 : Client --}}
        @if ($step === 1)
            <h2 class="font-semibold text-[17px] mb-1">Sélectionner le client</h2>
            <p class="text-[13px] text-(--color-text-muted) mb-5">Recherchez un client existant ou créez-en un nouveau.</p>

            <div class="relative mb-5">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input type="text" wire:model.live.debounce.300ms="clientSearch" placeholder="Nom ou numéro de téléphone"
                       class="w-full h-11 pl-10 pr-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
            </div>

            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Clients</div>
            <div class="flex flex-col gap-2.5 mb-5">
                @forelse ($clients as $client)
                    <button type="button" wire:click="pickCustomer({{ $client->id }})"
                            class="flex items-center gap-3.5 w-full text-left px-4 py-3.5 rounded-lg border transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px {{ $client->is_active === false ? 'opacity-60' : '' }} {{ $selectedCustomerId === $client->id ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border)' }}">
                        <span class="w-9 h-9 rounded-full bg-(--color-primary-tint) text-(--color-primary) flex items-center justify-center text-[13px] font-semibold flex-none transition-transform duration-150 {{ $selectedCustomerId === $client->id ? 'scale-110' : '' }}">
                            {{ mb_strtoupper(mb_substr($client->first_name, 0, 1).mb_substr($client->last_name, 0, 1)) }}
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ $client->fullName() }}</span>
                            <span class="block text-[12.5px] text-(--color-text-secondary) tabular-nums">{{ $client->phone }}</span>
                        </span>
                        @if ($client->is_active === false)
                            <span class="ml-auto text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-error-tint) text-(--color-error)">Compte désactivé</span>
                        @endif
                    </button>
                @empty
                    <p class="text-sm text-(--color-text-muted)">Aucun client trouvé.</p>
                @endforelse
            </div>

            @if (! $showNewClientForm)
                <button type="button" wire:click="$set('showNewClientForm', true)" class="text-sm font-medium text-(--color-primary)">+ Créer un nouveau client</button>
            @else
                <div class="border border-(--color-border) rounded-lg p-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <input type="text" wire:model="newFirstName" placeholder="Prénom" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                        @error('newFirstName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input type="text" wire:model="newLastName" placeholder="Nom" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                        @error('newLastName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input type="text" wire:model="newPhone" placeholder="+2250708124400" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                        @error('newPhone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <p class="sm:col-span-3 text-xs text-(--color-text-muted)">
                        Mot de passe par défaut pour l'app :
                        <span class="font-mono font-semibold">{{ \App\Models\Customer::DEFAULT_WALK_IN_PASSWORD }}</span>
                        (modifiable ensuite par le client dans son profil).
                    </p>
                    <div class="sm:col-span-3 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showNewClientForm', false)" class="h-9 px-3 rounded-lg border border-(--color-border) text-sm">Annuler</button>
                        <button type="button" wire:click="createAndPickCustomer" class="h-9 px-3 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Créer</button>
                    </div>
                </div>
            @endif
        @endif

        {{-- Étape 2 : Services et pièces --}}
        @if ($step === 2)
            <h2 class="font-semibold text-[17px] mb-1">Services et pièces</h2>
            <p class="text-[13px] text-(--color-text-muted) mb-5">Choisissez le service, puis touchez les pièces à ajouter à la commande.</p>

            {{-- 1. Le service --}}
            @if ($prestations->isEmpty())
                <div class="rounded-xl border border-dashed border-(--color-border) p-6 text-center mb-6">
                    <p class="text-sm text-(--color-text-secondary) mb-3">Aucun service n'est proposé pour l'instant.</p>
                    <a href="{{ route('services.index') }}" wire:navigate class="text-sm font-semibold text-(--color-primary) hover:underline">Définir mes services →</a>
                </div>
            @else
                <p class="text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">1 · Quel service ?</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-7">
                    @foreach ($prestations as $prestation)
                        @php $isSelected = $prestationId === $prestation->id; @endphp
                        <button type="button" wire:click="selectPrestation({{ $prestation->id }})" wire:key="prestation-{{ $prestation->id }}" aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                class="relative flex flex-col items-center gap-2 rounded-2xl border-2 px-3 py-4 text-center transition-all duration-150 active:scale-[.97]
                                       {{ $isSelected ? 'border-(--color-primary) bg-(--color-primary-tint) shadow-sm' : 'border-(--color-border) hover:border-(--color-primary)' }}">
                            @if ($isSelected)
                                <span class="absolute top-2 right-2 w-5 h-5 rounded-full bg-(--color-primary) text-white flex items-center justify-center">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
                                </span>
                            @endif
                            <x-service-icon kind="service" :name="$prestation->iconKey()" :size="48"/>
                            <span class="text-sm font-semibold leading-tight">{{ $prestation->name }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- 2. Les pièces --}}
                @if ($selectedPrestation === null)
                    <p class="text-sm text-(--color-text-muted) mb-6">Touchez un service pour voir ses pièces et leurs prix.</p>
                @else
                    <p class="text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">2 · Quelles pièces ? <span class="normal-case font-medium">— {{ $selectedPrestation->name }}</span></p>

                    @if ($pieces->isEmpty())
                        <div class="rounded-xl border border-dashed border-(--color-border) p-5 text-sm text-(--color-text-secondary) mb-4">
                            Aucune pièce n'a de prix pour « {{ $selectedPrestation->name }} ».
                            <a href="{{ route('services.index') }}" wire:navigate class="font-semibold text-(--color-primary) hover:underline">Renseigner les prix →</a>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-3">
                        @foreach ($pieces as $piece)
                            @php
                                $variantPrices = $piece->variants->filter(fn ($v) => $piece->prices->contains(fn ($p) => $p->service_variant_id === $v->id));
                                $basePrice = $piece->prices->firstWhere('service_variant_id', null)?->price_fcfa;
                                $hasVariants = $variantPrices->isNotEmpty();
                                $quantity = $this->quantityOfPiece($piece->id);
                                $fromPrice = $hasVariants ? $piece->prices->whereIn('service_variant_id', $variantPrices->pluck('id'))->min('price_fcfa') : $basePrice;
                            @endphp
                            <div wire:key="piece-{{ $piece->id }}-{{ $selectedPrestation->id }}" class="relative">
                                <button type="button" wire:click="addPiece({{ $piece->id }})"
                                        class="w-full flex flex-col items-center gap-1.5 rounded-2xl border-2 px-2 pt-4 pb-3 text-center transition-all duration-150 active:scale-[.97]
                                               {{ $quantity > 0 ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border) hover:border-(--color-primary)' }}">
                                    <x-service-icon kind="piece" :name="$piece->iconKey()" :size="44"/>
                                    <span class="text-sm font-semibold leading-tight">{{ $piece->name }}</span>
                                    <span class="text-xs text-(--color-text-secondary) tabular-nums">{{ $hasVariants ? 'dès ' : '' }}{{ number_format((int) $fromPrice, 0, ',', ' ') }} F</span>
                                </button>
                                @if ($quantity > 0)
                                    <span class="absolute -top-2 -right-2 min-w-6 h-6 px-1.5 rounded-full bg-(--color-primary) text-white text-xs font-bold flex items-center justify-center tabular-nums shadow animate-step">{{ $quantity }}</span>
                                    @if (! $hasVariants)
                                        <button type="button" wire:click="decrementPiece({{ $piece->id }})" aria-label="Retirer une {{ $piece->name }}"
                                                class="absolute -top-2 -left-2 w-6 h-6 rounded-full bg-(--color-surface) border border-(--color-border) text-sm font-bold leading-none flex items-center justify-center hover:border-(--color-primary) hover:text-(--color-primary)">−</button>
                                    @endif
                                @endif
                            </div>
                        @endforeach

                        <button type="button" wire:click="$toggle('showCustomForm')" wire:key="custom-tile"
                                class="flex flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed border-(--color-border) px-2 py-4 text-center text-sm font-semibold text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary) transition-colors">
                            <span class="w-11 h-11 rounded-2xl bg-(--color-bg) flex items-center justify-center text-xl">+</span>
                            Autre pièce
                        </button>
                    </div>

                    {{-- Choix de la variante d'une pièce (ex. manche courte / longue) --}}
                    @if ($variantPieceId !== null)
                        @php
                            $variantPiece = $pieces->firstWhere('id', $variantPieceId);
                            $choices = $variantPiece?->variants->filter(fn ($v) => $variantPiece->prices->contains(fn ($p) => $p->service_variant_id === $v->id)) ?? collect();
                        @endphp
                        @if ($variantPiece && $choices->isNotEmpty())
                            <div class="rounded-xl border border-(--color-primary) bg-(--color-primary-tint) p-4 mb-3 animate-step">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm font-semibold">{{ $variantPiece->name }} : laquelle ?</p>
                                    <button type="button" wire:click="$set('variantPieceId', null)" class="text-xs text-(--color-text-secondary) hover:underline">Annuler</button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($choices as $variant)
                                        <button type="button" wire:click="addVariant({{ $variantPiece->id }}, {{ $variant->id }})" wire:key="variant-{{ $variant->id }}"
                                                class="h-11 px-4 rounded-xl bg-(--color-surface) border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) active:scale-[.97] transition">
                                            {{ $variant->name }} <span class="text-(--color-text-secondary) font-medium tabular-nums">· {{ number_format((int) $variantPiece->prices->firstWhere('service_variant_id', $variant->id)->price_fcfa, 0, ',', ' ') }} F</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif

                    @if ($showCustomForm)
                        <div class="rounded-xl border border-(--color-border) p-4 mb-3 animate-step">
                            <p class="text-sm font-semibold mb-3">Pièce hors catalogue · {{ $selectedPrestation->name }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-[1fr_9rem_auto] gap-3 items-end">
                                <div>
                                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom de la pièce</label>
                                    <input type="text" wire:model="customName" placeholder="Ex. Nappe brodée" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                                    @error('customName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Prix (FCFA)</label>
                                    <input type="number" min="0" wire:model="customPrice" placeholder="1000" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                                    @error('customPrice') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                                </div>
                                <button type="button" wire:click="addCustomPiece" wire:loading.attr="disabled" wire:target="addCustomPiece"
                                        class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">+ Ajouter</button>
                            </div>
                        </div>
                    @endif
                @endif
            @endif

            {{-- 3. La commande --}}
            <p class="text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mt-6 mb-2">Dans la commande</p>
            @if ($items === [])
                <p class="text-sm text-(--color-text-muted) mb-4">Aucune pièce ajoutée pour l'instant.</p>
            @else
                <div class="flex flex-col mb-4">
                    @foreach ($items as $i => $item)
                        <div wire:key="item-{{ $i }}-{{ $item['base_name'] }}-{{ $item['prestation_id'] }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3 border-b border-(--color-border) first:border-t animate-step">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold truncate">{{ $item['base_name'] }}</div>
                                    <div class="text-xs text-(--color-text-secondary)">{{ $item['prestation_name'] }} · {{ number_format($item['unit_price_fcfa'], 0, ',', ' ') }} F</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" wire:click="decrementItem({{ $i }})" aria-label="Retirer une unité"
                                        class="w-8 h-8 rounded-md border border-(--color-border) flex items-center justify-center text-sm transition-colors duration-150 hover:border-(--color-primary) hover:text-(--color-primary)">−</button>
                                <span class="w-5 text-center font-semibold tabular-nums">{{ $item['quantity'] }}</span>
                                <button type="button" wire:click="incrementItem({{ $i }})" aria-label="Ajouter une unité"
                                        class="w-8 h-8 rounded-md border border-(--color-border) flex items-center justify-center text-sm transition-colors duration-150 hover:border-(--color-primary) hover:text-(--color-primary)">+</button>
                                <span class="w-24 text-right font-semibold tabular-nums">{{ number_format($item['unit_price_fcfa'] * $item['quantity'], 0, ',', ' ') }} F</span>
                            </div>
                            @php
                                $currentColor = $item['color'];
                                $currentHex = \App\Support\ColorPalette::hex($currentColor);
                                $isMulti = $currentColor === \App\Support\ColorPalette::MULTICOLOR;
                                $isCustom = $currentColor !== null && ! $isMulti && ! isset(\App\Support\ColorPalette::presets()[$currentColor]);
                            @endphp
                            <div class="basis-full flex flex-wrap items-center gap-1.5" role="group" aria-label="Couleur de la pièce (facultatif)">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mr-1">Couleur</span>
                                @foreach (\App\Support\ColorPalette::presets() as $colorName => $colorHex)
                                    @php
                                        $isColor = $currentColor === $colorName;
                                    @endphp
                                    <button type="button" wire:click="setItemColor({{ $i }}, '{{ $isColor ? '' : $colorName }}')" title="{{ $colorName }}" aria-label="{{ $colorName }}" aria-pressed="{{ $isColor ? 'true' : 'false' }}"
                                            class="w-7 h-7 rounded-full border border-(--color-border) flex items-center justify-center transition-transform duration-150 hover:scale-110 {{ $isColor ? 'ring-2 ring-offset-2 ring-(--color-primary) ring-offset-(--color-surface)' : '' }}"
                                            style="background-color: {{ $colorHex }}">
                                        @if ($isColor)
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="{{ in_array($colorName, ['Blanc', 'Jaune', 'Beige', 'Gris'], true) ? '#111' : '#fff' }}" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
                                        @endif
                                    </button>
                                @endforeach
                                <button type="button" wire:click="setItemColor({{ $i }}, '{{ $isMulti ? '' : \App\Support\ColorPalette::MULTICOLOR }}')" title="Multicolore" aria-label="Multicolore" aria-pressed="{{ $isMulti ? 'true' : 'false' }}"
                                        class="w-7 h-7 rounded-full border border-(--color-border) flex items-center justify-center transition-transform duration-150 hover:scale-110 {{ $isMulti ? 'ring-2 ring-offset-2 ring-(--color-primary) ring-offset-(--color-surface)' : '' }}"
                                        style="background: conic-gradient(#dc2626, #facc15, #16a34a, #2563eb, #7c3aed, #dc2626)">
                                    @if ($isMulti)
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
                                    @endif
                                </button>
                                {{-- Autre couleur : le sélecteur du téléphone / de l'ordinateur --}}
                                <label title="Autre couleur" class="relative w-7 h-7 rounded-full border border-dashed border-(--color-text-muted) flex items-center justify-center cursor-pointer overflow-hidden transition-transform duration-150 hover:scale-110 text-(--color-text-secondary) {{ $isCustom ? 'ring-2 ring-offset-2 ring-(--color-primary) ring-offset-(--color-surface) border-solid' : '' }}"
                                       style="{{ $isCustom && $currentHex ? 'background-color: '.$currentHex : '' }}">
                                    @unless ($isCustom)
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                    @endunless
                                    <input type="color" value="{{ $currentHex ?? '#2563eb' }}" wire:change="setItemColorFromPicker({{ $i }}, $event.target.value)" aria-label="Autre couleur"
                                           class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                                </label>
                                @if ($currentColor)
                                    <span class="text-xs font-medium text-(--color-text-secondary) ml-1">{{ $currentColor }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div wire:key="total-{{ $this->total }}" class="flex justify-between items-center pt-4 border-t border-dashed border-(--color-border)">
                    <span class="text-sm text-(--color-text-secondary)">Total</span>
                    <span class="font-display text-2xl font-bold tabular-nums animate-step">{{ number_format($this->total, 0, ',', ' ') }} FCFA</span>
                </div>
            @endif
        @endif

        {{-- Étape 3 : Détails --}}
        @if ($step === 3)
            <h2 class="font-semibold text-[17px] mb-5">Détails et paiement</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Date prévue de retrait</label>
                    <input type="date" wire:model="expectedAt" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                </div>
            </div>
            <div class="mb-5">
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Notes (optionnel)</label>
                <textarea wire:model="notes" rows="3" placeholder="Ex. tache sur la manche gauche" class="w-full px-3 py-2 rounded-lg border border-(--color-border) text-sm"></textarea>
            </div>

            {{-- Paiement : déjà payé, acompte (avec le reste à payer) ou au retrait --}}
            @if ($this->total > 0)
                @php
                    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
                    $choices = [
                        'full' => ['title' => 'Déjà payée', 'hint' => 'Le client règle les '.$fcfa($this->total).' F maintenant', 'icon' => '<path d="M20 6 9 17l-5-5"></path>'],
                        'partial' => ['title' => 'Acompte', 'hint' => 'Une partie maintenant, le reste au retrait', 'icon' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 3a9 9 0 0 1 0 18Z" fill="currentColor"></path>'],
                        'later' => ['title' => 'Au retrait', 'hint' => 'Le client paiera en récupérant', 'icon' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path>'],
                    ];
                    $remaining = max(0, $this->total - $this->amountPaidNow);
                @endphp
                <div class="mb-5">
                    <div class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Paiement</div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5" role="radiogroup" aria-label="Paiement">
                        @foreach ($choices as $value => $choice)
                            @php $isOn = $paymentChoice === $value; @endphp
                            <label wire:key="pay-{{ $value }}"
                                   class="relative flex items-start gap-3 p-3.5 rounded-xl border transition-all duration-150
                                          {{ $isOn ? 'border-(--color-primary) bg-(--color-primary-tint) shadow-[0_0_0_1px_var(--color-primary)]' : 'border-(--color-border) hover:border-(--color-text-muted)' }}">
                                <input type="radio" wire:model.live="paymentChoice" value="{{ $value }}" class="sr-only">
                                <span class="flex-none w-8 h-8 rounded-lg flex items-center justify-center {{ $isOn ? 'bg-(--color-primary) text-white' : 'bg-(--color-bg) text-(--color-text-secondary)' }}">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">{!! $choice['icon'] !!}</svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold">{{ $choice['title'] }}</span>
                                    <span class="block text-xs text-(--color-text-muted) mt-0.5">{{ $choice['hint'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('paymentChoice') <p class="text-xs text-(--color-error) mt-1.5">{{ $message }}</p> @enderror

                    @if ($paymentChoice === 'partial')
                        <div class="mt-3.5 grid grid-cols-1 sm:grid-cols-2 gap-4 animate-step">
                            <div>
                                <label for="deposit-amount" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Montant versé</label>
                                <div class="relative">
                                    <input id="deposit-amount" type="number" inputmode="numeric" min="1" max="{{ $this->total - 1 }}" step="50"
                                           wire:model.live.debounce.250ms="depositAmount" autofocus placeholder="Ex. {{ $fcfa((int) (round($this->total / 2 / 100) * 100)) }}"
                                           class="w-full h-10 pl-3 pr-12 rounded-lg border border-(--color-border) text-sm tabular-nums">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">FCFA</span>
                                </div>
                                @php $half = (int) (round($this->total / 2 / 100) * 100); @endphp
                                @if ($half > 0 && $half < $this->total)
                                    <button type="button" wire:click="$set('depositAmount', '{{ $half }}')" class="mt-1.5 text-xs font-medium text-(--color-primary) hover:underline">
                                        La moitié : {{ $fcfa($half) }} F
                                    </button>
                                @endif
                                @error('depositAmount') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="rounded-xl bg-(--color-warning-tint) px-4 py-3 self-start">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-warning-text)">Reste à payer au retrait</div>
                                <div class="font-display text-xl font-bold tabular-nums mt-0.5 text-(--color-warning-text)">{{ $fcfa($remaining) }} F</div>
                            </div>
                        </div>
                    @endif

                    @if ($paymentChoice === 'full' || $paymentChoice === 'partial')
                        <div class="mt-3.5 animate-step">
                            <div class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Moyen de paiement</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($paymentMethods as $method)
                                    <label wire:key="method-{{ $method->value }}"
                                           class="inline-flex items-center h-9 px-3.5 rounded-full border text-[13px] font-medium transition-colors duration-150
                                                  {{ $paymentMethod === $method->value ? 'border-(--color-primary) bg-(--color-primary) text-white' : 'border-(--color-border) hover:border-(--color-text-muted)' }}">
                                        <input type="radio" wire:model.live="paymentMethod" value="{{ $method->value }}" class="sr-only">
                                        {{ $method->label() }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex justify-between items-center pt-4 border-t border-(--color-border)">
                <span class="text-sm font-medium">{{ $selectedCustomer?->fullName() }}</span>
                <span class="text-right">
                    <span class="block font-semibold tabular-nums">{{ number_format($this->total, 0, ',', ' ') }} FCFA</span>
                    @if ($paymentChoice !== '' && $this->total > 0)
                        <span class="block text-xs tabular-nums {{ $this->total - $this->amountPaidNow > 0 ? 'text-(--color-warning-text)' : 'text-(--color-success-text)' }}">
                            {{ $this->total - $this->amountPaidNow > 0 ? 'Reste '.number_format(max(0, $this->total - $this->amountPaidNow), 0, ',', ' ').' F au retrait' : 'Soldée' }}
                        </span>
                    @endif
                </span>
            </div>
        @endif

        {{-- Étape 4 : Confirmation --}}
        @if ($step === 4)
            <h2 class="font-semibold text-[17px] mb-5">Vérifier la commande</h2>

            <div class="mb-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Client</div>
                <div class="text-sm font-medium">{{ $selectedCustomer?->fullName() }}</div>
                <div class="text-[13px] text-(--color-text-secondary) tabular-nums">{{ $selectedCustomer?->phone }}</div>
            </div>

            <div class="mb-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Pièces</div>
                @foreach ($items as $item)
                    <div class="flex justify-between text-sm py-1">
                        <span>{{ $item['name'] }} × {{ $item['quantity'] }}</span>
                        <span class="tabular-nums">{{ number_format($item['unit_price_fcfa'] * $item['quantity'], 0, ',', ' ') }} F</span>
                    </div>
                @endforeach
            </div>

            <div class="mb-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Retrait prévu</div>
                <div class="text-sm">{{ \Illuminate\Support\Carbon::parse($expectedAt)->translatedFormat('d F Y') }}</div>
            </div>

            @if ($this->total > 0)
                @php $remainingAtPickup = max(0, $this->total - $this->amountPaidNow); @endphp
                <div class="mb-5">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Paiement</div>
                    <div class="text-sm">
                        @if ($paymentChoice === 'full')
                            Déjà payée · {{ \App\Enums\PaymentMethod::from($paymentMethod)->label() }}
                        @elseif ($paymentChoice === 'partial')
                            Acompte de {{ number_format($this->amountPaidNow, 0, ',', ' ') }} F · {{ \App\Enums\PaymentMethod::from($paymentMethod)->label() }}
                        @else
                            À payer au retrait
                        @endif
                    </div>
                </div>
            @endif

            @if ($errorMessage)
                <div class="mb-5 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
            @endif

            <div class="pt-4 border-t border-(--color-border)">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">Total</span>
                    <span class="font-display text-2xl font-bold tabular-nums">{{ number_format($this->total, 0, ',', ' ') }} FCFA</span>
                </div>
                @if ($this->total > 0 && $this->amountPaidNow > 0)
                    <div class="flex justify-between items-center text-sm mt-1.5">
                        <span class="text-(--color-text-secondary)">Payé au dépôt</span>
                        <span class="tabular-nums font-medium text-(--color-success-text)">− {{ number_format($this->amountPaidNow, 0, ',', ' ') }} F</span>
                    </div>
                @endif
                @if ($this->total > 0)
                    <div class="flex justify-between items-center text-sm mt-1.5">
                        <span class="font-semibold">Reste à payer au retrait</span>
                        <span class="tabular-nums font-bold {{ $remainingAtPickup > 0 ? 'text-(--color-warning-text)' : 'text-(--color-success-text)' }}">{{ $remainingAtPickup > 0 ? number_format($remainingAtPickup, 0, ',', ' ').' F' : 'Rien, soldée' }}</span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="flex justify-between mt-5">
        <button type="button" wire:click="back" @if($step===1) disabled @endif
                class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium transition-colors duration-150 {{ $step === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:border-(--color-text-muted)' }}">
            ← Retour
        </button>

        @if ($step < 4)
            <button type="button" wire:click="next" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold transition-colors duration-150 hover:bg-(--color-primary-600)">
                Continuer →
            </button>
        @else
            <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                    class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold transition-colors duration-150 hover:bg-(--color-primary-600) disabled:opacity-60">
                <span wire:loading.remove wire:target="create">Créer la commande</span>
                <span wire:loading wire:target="create">Création…</span>
            </button>
        @endif
    </div>
</div>
