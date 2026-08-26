<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Nouvelle commande</h1>
        <p class="text-sm text-(--color-text-muted)">Enregistrez un dépôt en quatre étapes.</p>
    </div>

    <div class="flex items-center mb-7">
        @php $labels = [1 => 'Client', 2 => 'Articles', 3 => 'Détails', 4 => 'Confirmation']; @endphp
        @foreach ($labels as $n => $label)
            <div class="flex items-center flex-1 min-w-0 last:flex-none">
                <div class="flex items-center gap-2.5 flex-none">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-[13px] font-semibold
                        {{ $n < $step ? 'bg-(--color-success) text-white' : ($n === $step ? 'bg-(--color-primary) text-white' : 'bg-(--color-border) text-(--color-text-muted)') }}">
                        @if ($n < $step) ✓ @else {{ $n }} @endif
                    </div>
                    <span class="text-[13.5px] font-medium {{ $n <= $step ? 'text-(--color-text-primary)' : 'text-(--color-text-muted)' }}">{{ $label }}</span>
                </div>
                @if ($n < 4)
                    <div class="flex-1 h-px mx-3.5 {{ $n < $step ? 'bg-(--color-success)' : 'bg-(--color-border)' }}"></div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-7">

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
                            class="flex items-center gap-3.5 w-full text-left px-4 py-3.5 rounded-lg border {{ $selectedCustomerId === $client->id ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border)' }}">
                        <span class="w-9 h-9 rounded-full bg-(--color-primary-tint) text-(--color-primary) flex items-center justify-center text-[13px] font-semibold flex-none">
                            {{ mb_strtoupper(mb_substr($client->first_name, 0, 1).mb_substr($client->last_name, 0, 1)) }}
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ $client->fullName() }}</span>
                            <span class="block text-[12.5px] text-(--color-text-secondary) tabular-nums">{{ $client->phone }}</span>
                        </span>
                    </button>
                @empty
                    <p class="text-sm text-(--color-text-muted)">Aucun client trouvé.</p>
                @endforelse
            </div>

            @if (! $showNewClientForm)
                <button type="button" wire:click="$set('showNewClientForm', true)" class="text-sm font-medium text-(--color-primary)">+ Créer un nouveau client</button>
            @else
                <div class="border border-(--color-border) rounded-lg p-4 grid grid-cols-3 gap-3">
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
                    <div class="col-span-3 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showNewClientForm', false)" class="h-9 px-3 rounded-lg border border-(--color-border) text-sm">Annuler</button>
                        <button type="button" wire:click="createAndPickCustomer" class="h-9 px-3 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Créer</button>
                    </div>
                </div>
            @endif
        @endif

        {{-- Étape 2 : Articles --}}
        @if ($step === 2)
            <h2 class="font-semibold text-[17px] mb-1">Ajouter des articles</h2>
            <p class="text-[13px] text-(--color-text-muted) mb-5">Sélectionnez les vêtements déposés.</p>

            <div class="flex flex-wrap gap-2 mb-6">
                @foreach ($services as $service)
                    <button type="button" wire:click="addItem({{ $service->id }})"
                            class="h-9 px-3.5 rounded-lg border border-(--color-border) text-[13px] font-medium hover:border-(--color-primary)">
                        {{ $service->name }} · {{ number_format($service->price_fcfa, 0, ',', ' ') }} F
                    </button>
                @endforeach
            </div>

            @if ($items === [])
                <p class="text-sm text-(--color-text-muted) mb-4">Aucun article ajouté pour l'instant.</p>
            @else
                <div class="flex flex-col mb-4">
                    @foreach ($items as $i => $item)
                        <div class="flex items-center justify-between py-3.5 border-b border-(--color-border) first:border-t">
                            <span class="text-sm font-medium">{{ $item['name'] }}</span>
                            <div class="flex items-center gap-3">
                                <button type="button" wire:click="decrementItem({{ $i }})" class="w-7 h-7 rounded-md border border-(--color-border) flex items-center justify-center text-sm">−</button>
                                <span class="w-4 text-center font-semibold tabular-nums">{{ $item['quantity'] }}</span>
                                <button type="button" wire:click="incrementItem({{ $i }})" class="w-7 h-7 rounded-md border border-(--color-border) flex items-center justify-center text-sm">+</button>
                                <span class="w-20 text-right font-semibold tabular-nums">{{ number_format($item['unit_price_fcfa'] * $item['quantity'], 0, ',', ' ') }} F</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between items-center pt-4 border-t border-dashed border-(--color-border)">
                    <span class="text-sm text-(--color-text-secondary)">Total</span>
                    <span class="font-display text-2xl font-bold tabular-nums">{{ number_format($this->total, 0, ',', ' ') }} FCFA</span>
                </div>
            @endif
        @endif

        {{-- Étape 3 : Détails --}}
        @if ($step === 3)
            <h2 class="font-semibold text-[17px] mb-5">Détails</h2>

            <div class="grid grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Date prévue de retrait</label>
                    <input type="date" wire:model="expectedAt" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                </div>
            </div>
            <div class="mb-5">
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Notes (optionnel)</label>
                <textarea wire:model="notes" rows="3" placeholder="Ex. tache sur la manche gauche" class="w-full px-3 py-2 rounded-lg border border-(--color-border) text-sm"></textarea>
            </div>

            <div class="flex justify-between items-center pt-4 border-t border-(--color-border)">
                <span class="text-sm font-medium">{{ $selectedCustomer?->fullName() }}</span>
                <span class="font-semibold tabular-nums">{{ number_format($this->total, 0, ',', ' ') }} FCFA</span>
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
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Articles</div>
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

            @if ($errorMessage)
                <div class="mb-5 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
            @endif

            <div class="flex justify-between items-center pt-4 border-t border-(--color-border)">
                <span class="text-sm font-medium">Total</span>
                <span class="font-display text-2xl font-bold tabular-nums">{{ number_format($this->total, 0, ',', ' ') }} FCFA</span>
            </div>
        @endif
    </div>

    <div class="flex justify-between mt-5">
        <button type="button" wire:click="back" @if($step===1) disabled @endif
                class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium {{ $step === 1 ? 'opacity-40 cursor-not-allowed' : '' }}">
            ← Retour
        </button>

        @if ($step < 4)
            <button type="button" wire:click="next" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                Continuer →
            </button>
        @else
            <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                    class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                <span wire:loading.remove wire:target="create">Créer la commande</span>
                <span wire:loading wire:target="create">Création…</span>
            </button>
        @endif
    </div>
</div>
