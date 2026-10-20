@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $ready = $selectedCustomer !== null && $items !== [];
    $validateLabel = match (true) {
        $selectedCustomer === null => 'Choisir le client',
        $items === [] => 'Ajouter des pièces',
        default => 'Valider · '.$fcfa($this->total).' F',
    };
@endphp
<div class="max-w-6xl mx-auto pb-28 lg:pb-0" data-skip-back
     x-data="{
        q: '',
        qty: { open: false, id: null, name: '', value: '' },
        ticketOpen: false,
        norm(s) { return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim() },
        matches(name) { return this.q === '' || name.includes(this.norm(this.q)) },
        isDesktop() { return window.matchMedia('(pointer: fine)').matches },
        focus(target) {
            this.$nextTick(() => {
                const el = target === 'client' ? this.$refs.clientSearch : (this.$refs.pieceSearch || this.$refs.pieces);
                if (!el) return;
                if (this.isDesktop() && el.focus) { el.focus({ preventScroll: true }); }
                el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            });
        },
        addFirstPiece() {
            const tile = Array.from(this.$root.querySelectorAll('[data-piece-tile]')).find(t => t.offsetParent !== null);
            if (tile) { tile.querySelector('[data-add]').click(); this.q = ''; }
        },
        openQty(id, name, value) {
            this.qty = { open: true, id, name, value: value > 0 ? String(value) : '' };
            this.$nextTick(() => this.$refs.qtyInput && this.$refs.qtyInput.select());
        },
        submitQty() {
            const n = parseInt(this.qty.value, 10);
            if (!isNaN(n)) { this.$wire.setPieceQuantity(this.qty.id, n); }
            this.qty.open = false;
        },
        onKey(e) {
            const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName);
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); this.$wire.create(); return; }
            if (e.key === 'Escape') { this.qty.open = false; return; }
            if (!typing && e.key === '/') { e.preventDefault(); this.focus(this.$refs.clientSearch ? 'client' : 'pieces'); }
        },
     }"
     x-init="if (!$wire.selectedCustomerId) focus('client')"
     @comptoir-focus.window="focus($event.detail.target)"
     @keydown.window="onKey($event)">

    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Nouvelle commande</h1>
            <p class="text-sm text-(--color-text-muted)">Client, pièces, puis <span class="font-semibold text-(--color-text-secondary)">Valider</span>. La date et le paiement sont déjà remplis.</p>
        </div>
        <p class="hidden lg:flex items-center gap-3 text-xs text-(--color-text-muted)">
            <span><kbd class="px-1.5 py-0.5 rounded border border-(--color-border) font-mono">/</kbd> rechercher</span>
            <span><kbd class="px-1.5 py-0.5 rounded border border-(--color-border) font-mono">Ctrl</kbd> + <kbd class="px-1.5 py-0.5 rounded border border-(--color-border) font-mono">Entrée</kbd> valider</span>
        </p>
    </div>

    {{-- Commande tout juste validée : le reçu à remettre au client --}}
    @if ($lastCreated)
        <div wire:key="created-{{ $lastCreated['id'] }}" class="mb-5 flex flex-wrap items-center gap-3 p-4 rounded-xl bg-(--color-success-tint) border border-[color-mix(in_srgb,var(--color-success)_30%,transparent)] animate-step">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-success-text)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="flex-none"><path d="M20 6 9 17l-5-5"></path></svg>
            <div class="flex-1 min-w-0 text-sm">
                <span class="font-semibold text-(--color-success-text)">Commande {{ $lastCreated['number'] }} enregistrée.</span>
                <span class="text-(--color-text-secondary)">{{ $lastCreated['customer'] }} · {{ $fcfa($lastCreated['total']) }} F. L'écran est prêt pour le client suivant.</span>
            </div>
            <a href="{{ route('orders.show', $lastCreated['id']) }}" wire:navigate class="text-sm font-medium text-(--color-text-secondary) hover:underline">Voir la commande</a>
            <a href="{{ route('orders.receipt', ['order' => $lastCreated['id'], 'imprimer' => 1]) }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-success) text-white text-sm font-semibold hover:opacity-90">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path></svg>
                Imprimer le reçu
            </a>
        </div>
    @endif

    @if ($draftRestored && $items !== [])
        <div class="mb-5 flex flex-wrap items-center gap-3 px-4 py-3 rounded-xl bg-(--color-warning-tint) text-sm animate-step">
            <span class="flex-1 text-(--color-warning-text)"><span class="font-semibold">Commande en cours reprise</span> : elle n'avait pas été validée.</span>
            <button type="button" wire:click="clearDraft" class="font-semibold text-(--color-warning-text) hover:underline">Vider et recommencer</button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_22rem] gap-5 items-start">

        {{-- ───────────── Gauche : client, service, pièces ───────────── --}}
        <div class="flex flex-col gap-5 min-w-0">

            {{-- 1. Le client --}}
            <section class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-5">
                @if ($selectedCustomer)
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-(--color-primary) text-white flex items-center justify-center text-sm font-semibold flex-none">
                            {{ mb_strtoupper(mb_substr($selectedCustomer->first_name, 0, 1).mb_substr($selectedCustomer->last_name, 0, 1)) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Client</div>
                            <div class="text-sm font-semibold truncate">{{ $selectedCustomer->fullName() }} <span class="font-normal text-(--color-text-secondary) tabular-nums">· {{ $selectedCustomer->phone }}</span></div>
                        </div>
                        @if ($lastOrder && $lastOrder->items->isNotEmpty())
                            <button type="button" wire:click="repeatLastOrder" wire:loading.attr="disabled" wire:target="repeatLastOrder"
                                    class="order-last sm:order-none max-sm:basis-full inline-flex items-center justify-center gap-1.5 h-10 px-3 rounded-lg border border-(--color-primary) text-(--color-primary) text-[13px] font-semibold hover:bg-(--color-primary-tint)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path><path d="M3 3v5h5"></path></svg>
                                Refaire sa dernière commande
                                <span class="font-normal text-(--color-text-secondary)">({{ (int) $lastOrder->items->sum('quantity') }} pièces)</span>
                            </button>
                        @endif
                        <button type="button" wire:click="clearCustomer" class="text-[13px] font-medium text-(--color-text-secondary) hover:text-(--color-primary) hover:underline">Changer</button>
                    </div>
                @else
                    <label for="client-search" class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">
                        <span class="w-5 h-5 rounded-full bg-(--color-primary) text-white text-[11px] flex items-center justify-center">1</span> Client
                    </label>
                    <div class="relative">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                        <input id="client-search" x-ref="clientSearch" type="search" autocomplete="off" wire:model.live.debounce.250ms="clientSearch"
                               @keydown.enter.prevent="$wire.pickFirstCustomer()"
                               placeholder="Nom ou numéro de téléphone"
                               class="w-full h-12 pl-10 pr-3 rounded-xl border-2 border-(--color-border) text-[15px] focus:outline-none focus:border-(--color-primary)">
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($clients as $client)
                            <button type="button" wire:click="pickCustomer({{ $client->id }})" wire:key="client-{{ $client->id }}"
                                    class="flex items-center gap-2.5 pl-1.5 pr-3.5 py-1.5 rounded-full border border-(--color-border) text-left transition-all duration-150 hover:border-(--color-primary) hover:bg-(--color-primary-tint) {{ $client->is_active === false ? 'opacity-60' : '' }} {{ $loop->first && $clientSearch !== '' ? 'border-(--color-primary)' : '' }}">
                                <span class="w-8 h-8 rounded-full bg-(--color-primary-tint) text-(--color-primary) flex items-center justify-center text-xs font-semibold flex-none">
                                    {{ mb_strtoupper(mb_substr($client->first_name, 0, 1).mb_substr($client->last_name, 0, 1)) }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[13px] font-semibold leading-tight">{{ $client->fullName() }}</span>
                                    <span class="block text-[11.5px] text-(--color-text-secondary) tabular-nums leading-tight">{{ $client->phone }}</span>
                                </span>
                                @if ($client->is_active === false)
                                    <span class="text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-error-tint) text-(--color-error)">Compte désactivé</span>
                                @endif
                            </button>
                        @endforeach
                        @if (! $showNewClientForm)
                            <button type="button" wire:click="startNewCustomer"
                                    class="flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-dashed border-(--color-border) text-[13px] font-semibold text-(--color-primary) hover:border-(--color-primary)">
                                + {{ $clientSearch !== '' && $clients->isEmpty() ? 'Créer « '.\Illuminate\Support\Str::limit($clientSearch, 24).' »' : 'Nouveau client' }}
                            </button>
                        @endif
                    </div>
                    @if ($clientSearch !== '' && $clients->isEmpty() && ! $showNewClientForm)
                        <p class="mt-2 text-xs text-(--color-text-muted)">Aucun client trouvé : créez-le en un toucher.</p>
                    @endif

                    @if ($showNewClientForm)
                        <form wire:submit="createAndPickCustomer" class="mt-4 border border-(--color-border) rounded-xl p-4 grid grid-cols-1 sm:grid-cols-3 gap-3 animate-step">
                            <div>
                                <x-phone-input wire:model="newPhone" />
                                @error('newPhone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <input type="text" wire:model="newFirstName" placeholder="Prénom" class="w-full h-11 px-3 rounded-lg border border-(--color-border) text-sm">
                                @error('newFirstName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <input type="text" wire:model="newLastName" placeholder="Nom" class="w-full h-11 px-3 rounded-lg border border-(--color-border) text-sm">
                                @error('newLastName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                            </div>
                            @if ($knownCustomerId)
                                @include('livewire.clients._known-number', ['name' => $knownCustomerName])
                            @endif
                            <p class="sm:col-span-3 text-xs text-(--color-text-muted)">
                                En installant l'application PressLink avec ce numéro, le client retrouvera toutes ses commandes.
                            </p>
                            <div class="sm:col-span-3 flex justify-end gap-2">
                                <button type="button" wire:click="$set('showNewClientForm', false)" class="h-10 px-4 rounded-lg text-sm font-medium text-(--color-text-secondary) hover:underline">Annuler</button>
                                <button type="submit" class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Créer et choisir ce client</button>
                            </div>
                        </form>
                    @endif
                @endif
            </section>

            {{-- 2. Le service et les pièces --}}
            <section x-ref="pieces" tabindex="-1" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-5 outline-none">
                @if ($prestations->isEmpty())
                    <div class="rounded-xl border border-dashed border-(--color-border) p-6 text-center">
                        <p class="text-sm text-(--color-text-secondary) mb-3">Aucun service n'est proposé pour l'instant.</p>
                        <a href="{{ route('services.index') }}" wire:navigate class="text-sm font-semibold text-(--color-primary) hover:underline">Définir mes services →</a>
                    </div>
                @else
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">
                        <span class="w-5 h-5 rounded-full bg-(--color-primary) text-white text-[11px] flex items-center justify-center">2</span> Service et pièces
                    </div>

                    {{-- Le service reste choisi d'une pièce à l'autre --}}
                    <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 mb-4" role="tablist" aria-label="Service">
                        @foreach ($prestations as $prestation)
                            @php $isSelected = $prestationId === $prestation->id; @endphp
                            <button type="button" role="tab" wire:click="selectPrestation({{ $prestation->id }})" wire:key="prestation-{{ $prestation->id }}" aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                                    class="flex-none flex items-center gap-2 h-12 pl-1.5 pr-4 rounded-xl border-2 text-sm font-semibold transition-all duration-150 active:scale-[.97]
                                           {{ $isSelected ? 'border-(--color-primary) bg-(--color-primary) text-white' : 'border-(--color-border) hover:border-(--color-primary)' }}">
                                <x-service-icon kind="service" :name="$prestation->iconKey()" :size="34"/>
                                {{ $prestation->name }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Pinceau : la couleur des prochaines pièces --}}
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 mb-4 px-3 py-2.5 rounded-xl bg-(--color-bg)">
                        <span class="text-[13px] font-medium text-(--color-text-secondary)">
                            Couleur des pièces :
                            <span class="font-semibold {{ $activeColor ? 'text-(--color-text-primary)' : 'text-(--color-text-muted)' }}">{{ $activeColor ?? 'aucune' }}</span>
                        </span>
                        @include('livewire.orders._color-swatches', ['current' => $activeColor, 'method' => 'setActiveColor', 'pickerMethod' => 'setActiveColorFromPicker'])
                    </div>

                    @if ($selectedPrestation === null)
                        <p class="text-sm text-(--color-text-muted)">Touchez un service pour voir ses pièces et leurs prix.</p>
                    @else
                        @if ($pieces->isEmpty())
                            <div class="rounded-xl border border-dashed border-(--color-border) p-5 text-sm text-(--color-text-secondary) mb-4">
                                Aucune pièce n'a de prix pour « {{ $selectedPrestation->name }} ».
                                <a href="{{ route('services.index') }}" wire:navigate class="font-semibold text-(--color-primary) hover:underline">Renseigner les prix →</a>
                            </div>
                        @endif

                        @if ($pieces->count() > 8)
                            <div class="relative mb-3">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                                <input x-ref="pieceSearch" type="search" x-model="q" @keydown.enter.prevent="addFirstPiece()" placeholder="Chercher une pièce (Entrée pour l'ajouter)"
                                       class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                            </div>
                        @endif

                        <div class="grid grid-cols-3 sm:grid-cols-4 xl:grid-cols-5 gap-2.5">
                            @foreach ($pieces as $piece)
                                @php
                                    $variantPrices = $piece->variants->filter(fn ($v) => $piece->prices->contains(fn ($p) => $p->service_variant_id === $v->id));
                                    $basePrice = $piece->prices->firstWhere('service_variant_id', null)?->price_fcfa;
                                    $hasVariants = $variantPrices->isNotEmpty();
                                    $quantity = $this->quantityOfPiece($piece->id);
                                    $fromPrice = $hasVariants ? $piece->prices->whereIn('service_variant_id', $variantPrices->pluck('id'))->min('price_fcfa') : $basePrice;
                                    $brushQuantity = (int) (collect($items)->first(fn ($item) => $item['service_id'] === $piece->id && $item['service_variant_id'] === null && $item['prestation_id'] === $selectedPrestation->id && $item['color'] === $activeColor)['quantity'] ?? 0);
                                    $searchName = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($piece->name));
                                @endphp
                                <div wire:key="piece-{{ $piece->id }}-{{ $selectedPrestation->id }}" class="relative" data-piece-tile x-show="matches(@js($searchName))">
                                    <button type="button" data-add wire:click="addPiece({{ $piece->id }})"
                                            class="w-full flex flex-col items-center gap-1 rounded-2xl border-2 px-1.5 pt-3 pb-2.5 text-center transition-all duration-150 active:scale-[.96]
                                                   {{ $quantity > 0 ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border) hover:border-(--color-primary)' }}">
                                        <x-service-icon kind="piece" :name="$piece->iconKey()" :size="40"/>
                                        <span class="text-[13px] font-semibold leading-tight">{{ $piece->name }}</span>
                                        <span class="text-[11.5px] text-(--color-text-secondary) tabular-nums">{{ $hasVariants ? 'dès ' : '' }}{{ $fcfa((int) $fromPrice) }} F</span>
                                    </button>
                                    @if (! $hasVariants && $quantity > 0)
                                        {{-- Le nombre : touché, il permet de taper directement la quantité (ex. 12) --}}
                                        <button type="button" @click="openQty({{ $piece->id }}, @js($piece->name.($activeColor ? ' · '.$activeColor : '')), {{ $brushQuantity }})"
                                                aria-label="Taper la quantité de {{ $piece->name }}" title="Taper la quantité"
                                                class="absolute -top-2 -right-2 min-w-7 h-7 px-1.5 rounded-full bg-(--color-primary) text-white text-xs font-bold flex items-center justify-center tabular-nums shadow ring-2 ring-(--color-surface) transition-transform hover:scale-110 animate-step">
                                            {{ $quantity }}
                                        </button>
                                        <button type="button" wire:click="decrementPiece({{ $piece->id }})" aria-label="Retirer une {{ $piece->name }}"
                                                class="absolute top-1.5 left-1.5 w-7 h-7 rounded-full bg-(--color-surface) border border-(--color-border) text-sm font-bold leading-none flex items-center justify-center hover:border-(--color-primary) hover:text-(--color-primary)">−</button>
                                    @elseif ($quantity > 0)
                                        <span class="absolute -top-2 -right-2 min-w-7 h-7 px-1.5 rounded-full bg-(--color-primary) text-white text-xs font-bold flex items-center justify-center tabular-nums shadow ring-2 ring-(--color-surface)">{{ $quantity }}</span>
                                    @endif
                                </div>
                            @endforeach

                            <button type="button" wire:click="$set('showCustomForm', true)" wire:key="custom-tile" data-piece-tile-custom
                                    class="flex flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-(--color-border) px-1.5 py-3 text-center text-[13px] font-semibold text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary) transition-colors">
                                <span class="w-10 h-10 rounded-2xl bg-(--color-bg) flex items-center justify-center text-xl">+</span>
                                Autre pièce
                            </button>
                        </div>
                        <p class="mt-3 text-xs text-(--color-text-muted)">Touchez une pièce pour en ajouter une. Touchez ensuite son nombre pour taper la quantité d'un coup (ex. 12).</p>
                    @endif
                @endif
            </section>
        </div>

        {{-- ───────────── Droite : le ticket, toujours visible ───────────── --}}
        <aside class="max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-30 lg:sticky lg:top-20">
            <div class="bg-(--color-surface) border border-(--color-border) max-lg:border-x-0 max-lg:border-b-0 max-lg:rounded-t-2xl lg:rounded-xl max-lg:shadow-[0_-8px_24px_rgba(15,23,42,.12)]">

                {{-- Téléphone : le résumé et le bouton Valider, sous le pouce --}}
                <div class="lg:hidden flex items-center gap-3 px-4 py-3">
                    <button type="button" @click="ticketOpen = !ticketOpen" class="flex-1 min-w-0 flex items-center gap-2 text-left" :aria-expanded="ticketOpen">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="flex-none text-(--color-text-secondary) transition-transform" :class="ticketOpen ? 'rotate-180' : ''"><path d="m18 15-6-6-6 6"></path></svg>
                        <span class="min-w-0">
                            <span class="block text-xs text-(--color-text-secondary) truncate">{{ $this->pieceCount }} pièce{{ $this->pieceCount > 1 ? 's' : '' }}{{ $selectedCustomer ? ' · '.$selectedCustomer->fullName() : '' }}</span>
                            <span class="block font-display text-lg font-bold tabular-nums leading-tight">{{ $fcfa($this->total) }} F</span>
                        </span>
                    </button>
                    <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                            class="h-12 px-5 rounded-xl text-[15px] font-semibold transition-colors disabled:opacity-60 {{ $ready ? 'bg-(--color-primary) text-white' : 'bg-(--color-primary-tint) text-(--color-primary)' }}">
                        <span wire:loading.remove wire:target="create">{{ $ready ? 'Valider' : $validateLabel }}</span>
                        <span wire:loading wire:target="create">…</span>
                    </button>
                </div>

                <div :class="ticketOpen ? '' : 'max-lg:hidden'" class="max-lg:max-h-[65vh] max-lg:overflow-y-auto max-lg:border-t max-lg:border-(--color-border) p-4 lg:p-5">
                    <div class="flex items-baseline justify-between mb-3">
                        <h2 class="font-semibold">Ticket</h2>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="text-(--color-text-secondary)">{{ $this->pieceCount }} pièce{{ $this->pieceCount > 1 ? 's' : '' }}</span>
                            @if ($items !== [])
                                <button type="button" wire:click="clearDraft" wire:confirm="Vider le ticket ?" class="text-(--color-text-muted) hover:text-(--color-error) hover:underline">Vider</button>
                            @endif
                        </div>
                    </div>

                    @if ($items === [])
                        <p class="text-sm text-(--color-text-muted) py-6 text-center border-y border-dashed border-(--color-border)">Les pièces touchées apparaissent ici.</p>
                    @else
                        <div class="flex flex-col border-y border-(--color-border) divide-y divide-(--color-border)">
                            @foreach ($items as $i => $item)
                                @php $hex = \App\Support\ColorPalette::hex($item['color']); @endphp
                                <div wire:key="item-{{ $i }}-{{ md5($item['base_name'].$item['prestation_id'].$item['color']) }}" class="py-2.5" x-data="{ colors: false }">
                                    <div class="flex items-start gap-2.5">
                                        {{-- Pastille : la couleur de la ligne, modifiable --}}
                                        <button type="button" @click="colors = !colors" title="{{ $item['color'] ? 'Couleur : '.$item['color'] : 'Ajouter une couleur' }}" aria-label="Couleur de la ligne"
                                                class="mt-0.5 w-5 h-5 flex-none rounded-full border {{ $item['color'] ? 'border-(--color-border)' : 'border-dashed border-(--color-text-muted)' }}"
                                                style="{{ $item['color'] === \App\Support\ColorPalette::MULTICOLOR ? 'background: conic-gradient(#dc2626, #facc15, #16a34a, #2563eb, #7c3aed, #dc2626)' : ($hex ? 'background-color: '.$hex : '') }}"></button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-baseline justify-between gap-2">
                                                <span class="text-sm font-semibold leading-tight">{{ $item['base_name'] }}</span>
                                                <span class="text-sm font-semibold tabular-nums">{{ $fcfa($item['unit_price_fcfa'] * $item['quantity']) }}</span>
                                            </div>
                                            <div class="flex items-center justify-between gap-2 mt-1">
                                                <span class="text-xs text-(--color-text-secondary) min-w-0">{{ collect([$item['prestation_name'], $item['color']])->filter()->implode(' · ') }} · {{ $fcfa($item['unit_price_fcfa']) }} F</span>
                                                <span class="flex items-center gap-1 flex-none">
                                                    <button type="button" wire:click="decrementItem({{ $i }})" aria-label="Retirer une unité"
                                                            class="w-7 h-7 rounded-lg border border-(--color-border) flex items-center justify-center text-sm hover:border-(--color-primary) hover:text-(--color-primary)">−</button>
                                                    <input type="number" inputmode="numeric" min="0" max="{{ \App\Livewire\Orders\Create::MAX_QUANTITY }}" value="{{ $item['quantity'] }}" aria-label="Quantité"
                                                           wire:change="setItemQuantity({{ $i }}, parseInt($event.target.value) || 0)" @focus="$event.target.select()"
                                                           class="w-11 h-7 rounded-lg border border-(--color-border) text-center text-sm font-semibold tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                                    <button type="button" wire:click="incrementItem({{ $i }})" aria-label="Ajouter une unité"
                                                            class="w-7 h-7 rounded-lg border border-(--color-border) flex items-center justify-center text-sm hover:border-(--color-primary) hover:text-(--color-primary)">+</button>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-show="colors" x-cloak x-transition.opacity class="mt-2 p-2 rounded-lg bg-(--color-bg)">
                                        @include('livewire.orders._color-swatches', ['current' => $item['color'], 'method' => 'setItemColor', 'pickerMethod' => 'setItemColorFromPicker', 'args' => $i.','])
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Prête le / remarque : préremplis, modifiables d'un toucher --}}
                    <div class="mt-4 flex flex-col gap-2.5 text-sm" x-data="{ note: @js($notes !== '') }">
                        <label class="flex items-center justify-between gap-3">
                            <span class="text-(--color-text-secondary)">Prête le</span>
                            <input type="date" wire:model.live="expectedAt" class="h-9 px-2.5 rounded-lg border border-(--color-border) text-sm">
                        </label>
                        <button type="button" x-show="!note" @click="note = true; $nextTick(() => $refs.note.focus())" class="self-start text-[13px] font-medium text-(--color-primary) hover:underline">+ Ajouter une remarque</button>
                        <textarea x-show="note" x-cloak x-ref="note" wire:model.blur="notes" rows="2" placeholder="Ex. tache sur la manche gauche" class="w-full px-3 py-2 rounded-lg border border-(--color-border) text-sm"></textarea>
                    </div>

                    {{-- Paiement : « Au retrait » par défaut --}}
                    @if ($this->total > 0)
                        <div class="mt-4">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Paiement</div>
                            <div class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-(--color-bg)" role="radiogroup" aria-label="Paiement">
                                @foreach (['later' => 'Au retrait', 'full' => 'Payée', 'partial' => 'Acompte'] as $value => $label)
                                    <button type="button" role="radio" aria-checked="{{ $paymentChoice === $value ? 'true' : 'false' }}" wire:click="$set('paymentChoice', '{{ $value }}')"
                                            class="h-9 rounded-lg text-[13px] font-semibold transition-colors {{ $paymentChoice === $value ? 'bg-(--color-surface) text-(--color-primary) shadow-sm' : 'text-(--color-text-secondary) hover:text-(--color-text-primary)' }}">{{ $label }}</button>
                                @endforeach
                            </div>
                            @error('paymentChoice') <p class="text-xs text-(--color-error) mt-1.5">{{ $message }}</p> @enderror

                            @if ($paymentChoice === 'partial')
                                @php $half = (int) (round($this->total / 2 / 100) * 100); @endphp
                                <div class="mt-2.5 animate-step">
                                    <div class="relative">
                                        <input type="number" inputmode="numeric" min="1" max="{{ $this->total - 1 }}" step="50" wire:model.live.debounce.250ms="depositAmount" aria-label="Montant versé"
                                               placeholder="Montant versé" class="w-full h-10 pl-3 pr-14 rounded-lg border border-(--color-border) text-sm tabular-nums">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">FCFA</span>
                                    </div>
                                    @if ($half > 0 && $half < $this->total)
                                        <button type="button" wire:click="$set('depositAmount', '{{ $half }}')" class="mt-1.5 text-xs font-medium text-(--color-primary) hover:underline">La moitié : {{ $fcfa($half) }} F</button>
                                    @endif
                                    @error('depositAmount') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                                </div>
                            @endif

                            @if ($paymentChoice === 'full' || $paymentChoice === 'partial')
                                <div class="mt-2.5 flex flex-wrap gap-1.5 animate-step">
                                    @foreach ($paymentMethods as $method)
                                        <button type="button" wire:key="method-{{ $method->value }}" wire:click="$set('paymentMethod', '{{ $method->value }}')"
                                                class="h-8 px-3 rounded-full border text-xs font-medium transition-colors {{ $paymentMethod === $method->value ? 'border-(--color-primary) bg-(--color-primary) text-white' : 'border-(--color-border) hover:border-(--color-text-muted)' }}">
                                            {{ $method->label() }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Total --}}
                    <div class="mt-4 pt-3 border-t border-dashed border-(--color-border)">
                        <div class="flex justify-between items-baseline">
                            <span class="text-sm text-(--color-text-secondary)">Total</span>
                            <span wire:key="total-{{ $this->total }}" class="font-display text-2xl font-bold tabular-nums animate-step">{{ $fcfa($this->total) }} F</span>
                        </div>
                        @if ($this->total > 0 && $this->amountPaidNow > 0)
                            @php $remaining = max(0, $this->total - $this->amountPaidNow); @endphp
                            <div class="flex justify-between text-xs mt-1">
                                <span class="text-(--color-text-secondary)">Reste à payer au retrait</span>
                                <span class="font-semibold tabular-nums {{ $remaining > 0 ? 'text-(--color-warning-text)' : 'text-(--color-success-text)' }}">{{ $remaining > 0 ? $fcfa($remaining).' F' : 'Rien, soldée' }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($errorMessage)
                        <div class="mt-3 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
                    @endif

                    {{-- L'action principale : un seul gros bouton, qui dit ce qu'il manque --}}
                    <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                            class="max-lg:hidden mt-4 w-full py-3.5 rounded-xl text-[15px] font-semibold flex items-center justify-center gap-2 transition-colors disabled:opacity-60
                                   {{ $ready ? 'bg-(--color-primary) text-white hover:bg-(--color-primary-600) shadow-sm' : 'bg-(--color-primary-tint) text-(--color-primary)' }}">
                        <span wire:loading.remove wire:target="create" class="flex items-center gap-2">
                            @if ($ready)
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            @endif
                            {{ $validateLabel }}
                        </span>
                        <span wire:loading wire:target="create">Enregistrement…</span>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    {{-- Variantes d'une pièce (manche courte / longue…) --}}
    @if ($variantPieceId !== null && $selectedPrestation !== null)
        @php
            $variantPiece = $pieces->firstWhere('id', $variantPieceId);
            $choices = $variantPiece?->variants->filter(fn ($v) => $variantPiece->prices->contains(fn ($p) => $p->service_variant_id === $v->id)) ?? collect();
        @endphp
        @if ($variantPiece && $choices->isNotEmpty())
            <div class="fixed inset-0 z-50 bg-slate-900/40 flex items-end sm:items-center justify-center p-4" wire:click.self="$set('variantPieceId', null)" @keydown.escape.window="$wire.set('variantPieceId', null)">
                <div class="w-full max-w-sm bg-(--color-surface) rounded-2xl p-5 shadow-xl animate-step">
                    <div class="flex items-center gap-3 mb-4">
                        <x-service-icon kind="piece" :name="$variantPiece->iconKey()" :size="40"/>
                        <p class="flex-1 font-semibold">{{ $variantPiece->name }} : laquelle ?</p>
                        <button type="button" wire:click="$set('variantPieceId', null)" class="text-sm text-(--color-text-secondary) hover:underline">Annuler</button>
                    </div>
                    <div class="flex flex-col gap-2">
                        @foreach ($choices as $variant)
                            <button type="button" wire:click="addVariant({{ $variantPiece->id }}, {{ $variant->id }})" wire:key="variant-{{ $variant->id }}" @if ($loop->first) autofocus @endif
                                    class="h-12 px-4 rounded-xl border-2 border-(--color-border) text-[15px] font-semibold flex items-center justify-between hover:border-(--color-primary) active:scale-[.98] transition">
                                {{ $variant->name }}
                                <span class="text-(--color-text-secondary) font-medium tabular-nums">{{ $fcfa((int) $variantPiece->prices->firstWhere('service_variant_id', $variant->id)->price_fcfa) }} F</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Pièce hors catalogue --}}
    @if ($showCustomForm && $selectedPrestation !== null)
        <div class="fixed inset-0 z-50 bg-slate-900/40 flex items-end sm:items-center justify-center p-4" wire:click.self="$set('showCustomForm', false)">
            <form wire:submit="addCustomPiece" class="w-full max-w-sm bg-(--color-surface) rounded-2xl p-5 shadow-xl animate-step">
                <p class="font-semibold mb-4">Autre pièce · {{ $selectedPrestation->name }}</p>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom de la pièce</label>
                <input type="text" wire:model="customName" placeholder="Ex. Nappe brodée" autofocus class="w-full h-11 px-3 rounded-lg border border-(--color-border) text-sm">
                @error('customName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                <label class="block text-xs font-medium text-(--color-text-secondary) mt-3 mb-1.5">Prix (FCFA)</label>
                <input type="number" inputmode="numeric" min="0" wire:model="customPrice" placeholder="1000" class="w-full h-11 px-3 rounded-lg border border-(--color-border) text-sm">
                @error('customPrice') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" wire:click="$set('showCustomForm', false)" class="h-11 px-4 rounded-lg text-sm font-medium text-(--color-text-secondary) hover:underline">Annuler</button>
                    <button type="submit" class="h-11 px-5 rounded-xl bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Ajouter au ticket</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Quantité tapée d'un coup (« 12 chemises ») --}}
    <div x-show="qty.open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-slate-900/40 flex items-end sm:items-center justify-center p-4" @click.self="qty.open = false">
        <form @submit.prevent="submitQty()" class="w-full max-w-xs bg-(--color-surface) rounded-2xl p-5 shadow-xl">
            <p class="font-semibold mb-3">Combien : <span x-text="qty.name"></span> ?</p>
            <input x-ref="qtyInput" type="number" inputmode="numeric" min="0" max="{{ \App\Livewire\Orders\Create::MAX_QUANTITY }}" x-model="qty.value" aria-label="Quantité"
                   class="w-full h-14 rounded-xl border-2 border-(--color-border) text-center text-2xl font-bold tabular-nums focus:outline-none focus:border-(--color-primary)">
            <div class="grid grid-cols-4 gap-2 mt-3">
                <template x-for="n in [2, 5, 10, 20]" :key="n">
                    <button type="button" @click="qty.value = String(n)" class="h-10 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary)" x-text="n"></button>
                </template>
            </div>
            <button type="submit" class="mt-4 w-full h-12 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">OK</button>
        </form>
    </div>
</div>
