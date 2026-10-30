@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
    $validateLabel = match (true) {
        ! $acceptsCustomers => 'Pressing fermé',
        $selectedCustomer === null => 'Choisir le client',
        $items === [] => 'Ajouter des pièces',
        default => 'Valider · '.$fcfa($this->total).' F',
    };
@endphp
<div class="max-w-6xl mx-auto pb-28 lg:pb-0" data-skip-back
     x-data="ticketCommande({
        items: $wire.$entangle('items'),
        prestationId: $wire.$entangle('prestationId'),
        activeColor: $wire.$entangle('activeColor'),
        paymentChoice: $wire.$entangle('paymentChoice'),
        depositAmount: $wire.$entangle('depositAmount'),
        paymentMethod: $wire.$entangle('paymentMethod'),
        clientId: $wire.$entangle('selectedCustomerId'),
        ouvert: @js($acceptsCustomers),
     })"
     @comptoir-focus.window="focus($event.detail.target)"
     @keydown.window="onKey($event)">
    @if ($catalogue !== [])
        {{-- Lu une fois par ticket.js : services, pièces, prix et couleurs --}}
        @php
            $ticketData = [
                'catalogue' => $catalogue,
                'palette' => \App\Support\ColorPalette::all(),
                'presets' => \App\Support\ColorPalette::presets(),
                'multicolore' => \App\Support\ColorPalette::MULTICOLOR,
                'max' => \App\Livewire\Orders\Create::MAX_QUANTITY,
            ];
        @endphp
        <script type="application/json" id="catalogue-commande">@json($ticketData)</script>
    @endif

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
            <x-receipt-actions label="le reçu" :download="$lastCreated['pdf']" :share="$lastCreated['share']" />
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
            <button type="button" @click="vider()" class="font-semibold text-(--color-warning-text) hover:underline">Vider et recommencer</button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_22rem] gap-5 items-start">

        {{-- ───────────── Gauche : client, service, pièces ───────────── --}}
        <div class="flex flex-col gap-5 min-w-0">

            {{-- 1. Le client --}}
            <section data-guide="commande-client" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-5">
                @if ($selectedCustomer)
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-(--color-primary) text-white flex items-center justify-center text-sm font-semibold flex-none">
                            {{ mb_strtoupper(mb_substr($selectedCustomer->first_name, 0, 1).mb_substr($selectedCustomer->last_name, 0, 1)) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Client</div>
                            <div class="text-sm font-semibold truncate">{{ $selectedCustomer->fullName() }} <span class="font-normal text-(--color-text-secondary) tabular-nums">· {{ \App\Support\PhoneNumber::format($selectedCustomer->phone) }}</span></div>
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
                                    <span class="block text-[11.5px] text-(--color-text-secondary) tabular-nums leading-tight">{{ \App\Support\PhoneNumber::format($client->phone) }}</span>
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

            {{-- 2. Le service et les pièces : tout se passe dans le navigateur (ticket.js), sans attendre le serveur --}}
            <section x-ref="pieces" tabindex="-1" data-guide="commande-pieces" wire:ignore class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-5 outline-none">
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
                            <button type="button" role="tab" @click="choisirService({{ $prestation->id }})" :aria-selected="prestationId === {{ $prestation->id }}"
                                    class="flex-none flex items-center gap-2 h-12 pl-1.5 pr-4 rounded-xl border-2 text-sm font-semibold transition-all duration-150 active:scale-[.97]"
                                    :class="prestationId === {{ $prestation->id }} ? 'border-(--color-primary) bg-(--color-primary) text-white' : 'border-(--color-border) hover:border-(--color-primary)'">
                                <x-service-icon kind="service" :name="$prestation->iconKey()" :size="34"/>
                                {{ $prestation->name }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Pinceau : la couleur des prochaines pièces --}}
                    {{-- Téléphone : les pastilles se déplient d'un toucher, pour laisser la place aux pièces --}}
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 mb-4 px-3 py-2.5 rounded-xl bg-(--color-bg)" x-data="{ pastilles: false }">
                        <button type="button" @click="pastilles = !pastilles" class="sm:pointer-events-none flex items-center gap-2 text-[13px] font-medium text-(--color-text-secondary)" :aria-expanded="pastilles">
                            <span x-show="activeColor" class="w-4 h-4 rounded-full border border-(--color-border)" :style="pastille(activeColor)"></span>
                            Couleur des pièces :
                            <span class="font-semibold" :class="activeColor ? 'text-(--color-text-primary)' : 'text-(--color-text-muted)'" x-text="activeColor || 'aucune'"></span>
                            <span class="sm:hidden text-(--color-primary) font-semibold" x-text="pastilles ? 'Fermer' : 'Choisir'"></span>
                        </button>
                        <div class="max-sm:w-full" :class="pastilles ? '' : 'max-sm:hidden'" @click="if ($event.target.closest('button')) pastilles = false">
                            @include('livewire.orders._color-swatches', ['current' => 'activeColor', 'pick' => 'choisirPinceau(%s)', 'picker' => 'pinceauDepuisSelecteur($event.target.value)'])
                        </div>
                    </div>

                    <p x-show="!prestationId" class="text-sm text-(--color-text-muted)">Touchez un service pour voir ses pièces et leurs prix.</p>

                    @if (collect($pieceGroups)->contains(fn ($group) => count($group) > 8))
                        <div class="relative mb-3">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                            <input x-ref="pieceSearch" type="search" x-model="q" @keydown.enter.prevent="ajouterPremiere()" placeholder="Chercher une pièce (Entrée pour l'ajouter)"
                                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                        </div>
                    @endif

                    @foreach ($pieceGroups as $prestationId => $group)
                        @php $prestationName = $prestations->firstWhere('id', $prestationId)?->name; @endphp
                        <div x-show="prestationId === {{ $prestationId }}" @if ($loop->index > 0) x-cloak @endif>
                            @if ($group === [])
                                <div class="rounded-xl border border-dashed border-(--color-border) p-5 text-sm text-(--color-text-secondary) mb-4">
                                    Aucune pièce n'a de prix pour « {{ $prestationName }} ».
                                    <a href="{{ route('services.index') }}" wire:navigate class="font-semibold text-(--color-primary) hover:underline">Renseigner les prix →</a>
                                </div>
                            @endif
                            <div class="grid grid-cols-3 sm:grid-cols-4 xl:grid-cols-5 gap-2.5">
                                @foreach ($group as $tile)
                                    @php $piece = $tile['piece']; $count = 'quantite('.$prestationId.', '.$piece->id.')'; @endphp
                                    <div class="relative" data-piece-tile x-show="matches(@js($tile['search']))">
                                        <button type="button" data-add @click="ajouter({{ $piece->id }})"
                                                class="w-full flex flex-col items-center gap-1 rounded-2xl border-2 px-1.5 pt-3 pb-2.5 text-center transition-all duration-150 active:scale-[.96]"
                                                :class="{{ $count }} > 0 ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border) hover:border-(--color-primary)'">
                                            <x-service-icon kind="piece" :name="$piece->iconKey()" :size="40"/>
                                            <span class="text-[13px] font-semibold leading-tight">{{ $piece->name }}</span>
                                            <span class="text-[11.5px] text-(--color-text-secondary) tabular-nums">{{ $tile['hasVariants'] ? 'dès ' : '' }}{{ $fcfa((int) $tile['from']) }} F</span>
                                        </button>
                                        @if (! $tile['hasVariants'])
                                            {{-- Le nombre : touché, il permet de taper directement la quantité (ex. 12) --}}
                                            <button type="button" x-show="{{ $count }} > 0" x-cloak @click="ouvrirQuantite({{ $piece->id }})" x-text="{{ $count }}"
                                                    aria-label="Taper la quantité de {{ $piece->name }}" title="Taper la quantité"
                                                    class="absolute -top-2 -right-2 min-w-7 h-7 px-1.5 rounded-full bg-(--color-primary) text-white text-xs font-bold flex items-center justify-center tabular-nums shadow ring-2 ring-(--color-surface) transition-transform hover:scale-110"></button>
                                            <button type="button" x-show="{{ $count }} > 0" x-cloak @click="retirer({{ $piece->id }})" aria-label="Retirer une {{ $piece->name }}"
                                                    class="absolute top-1.5 left-1.5 w-7 h-7 rounded-full bg-(--color-surface) border border-(--color-border) text-sm font-bold leading-none flex items-center justify-center hover:border-(--color-primary) hover:text-(--color-primary)">−</button>
                                        @else
                                            <span x-show="{{ $count }} > 0" x-cloak x-text="{{ $count }}" class="absolute -top-2 -right-2 min-w-7 h-7 px-1.5 rounded-full bg-(--color-primary) text-white text-xs font-bold flex items-center justify-center tabular-nums shadow ring-2 ring-(--color-surface)"></span>
                                        @endif
                                    </div>
                                @endforeach

                                <button type="button" @click="ouvrirAutre()" data-piece-tile-custom
                                        class="flex flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-(--color-border) px-1.5 py-3 text-center text-[13px] font-semibold text-(--color-text-secondary) hover:border-(--color-primary) hover:text-(--color-primary) transition-colors">
                                    <span class="w-10 h-10 rounded-2xl bg-(--color-bg) flex items-center justify-center text-xl">+</span>
                                    Autre pièce
                                </button>
                            </div>
                        </div>
                    @endforeach
                    <p x-show="prestationId" class="mt-3 text-xs text-(--color-text-muted)">Touchez une pièce pour en ajouter une. Touchez ensuite son nombre pour taper la quantité d'un coup (ex. 12).</p>
                @endif
            </section>
        </div>

        {{-- ───────────── Droite : le ticket, toujours visible ───────────── --}}
        <aside data-guide="commande-ticket" class="max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-30 lg:sticky lg:top-20">
            <div class="bg-(--color-surface) border border-(--color-border) max-lg:border-x-0 max-lg:border-b-0 max-lg:rounded-t-2xl lg:rounded-xl max-lg:shadow-[0_-8px_24px_rgba(15,23,42,.12)]">

                {{-- Téléphone : le résumé et le bouton Valider, sous le pouce --}}
                <div class="lg:hidden flex items-center gap-3 px-4 py-3">
                    <button type="button" @click="ticketOpen = !ticketOpen" class="flex-1 min-w-0 flex items-center gap-2 text-left" :aria-expanded="ticketOpen">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" class="flex-none text-(--color-text-secondary) transition-transform" :class="ticketOpen ? 'rotate-180' : ''"><path d="m18 15-6-6-6 6"></path></svg>
                        <span class="min-w-0">
                            <span class="block text-xs text-(--color-text-secondary) truncate"><span x-text="pieces + (pieces > 1 ? ' pièces' : ' pièce')"></span>{{ $selectedCustomer ? ' · '.$selectedCustomer->fullName() : '' }}</span>
                            <span class="block font-display text-lg font-bold tabular-nums leading-tight" x-text="fcfa(total) + ' F'"></span>
                        </span>
                    </button>
                    <button type="button" @click="valider()" wire:loading.attr="disabled" wire:target="create" data-guide="commande-valider"
                            class="h-12 px-5 rounded-xl text-[15px] font-semibold transition-colors disabled:opacity-60"
                            :class="pret ? 'bg-(--color-primary) text-white' : 'bg-(--color-primary-tint) text-(--color-primary)'">
                        <span wire:loading.remove wire:target="create" x-text="pret ? 'Valider' : libelleValider"></span>
                        <span wire:loading wire:target="create">…</span>
                    </button>
                </div>

                <div :class="ticketOpen ? '' : 'max-lg:hidden'" class="max-lg:max-h-[65vh] max-lg:overflow-y-auto max-lg:border-t max-lg:border-(--color-border) p-4 lg:p-5">
                    <div class="flex items-baseline justify-between mb-3">
                        <h2 class="font-semibold">Ticket</h2>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="text-(--color-text-secondary)" x-text="pieces + (pieces > 1 ? ' pièces' : ' pièce')"></span>
                            <button type="button" x-show="items.length > 0" x-cloak @click="if (confirm('Vider le ticket ?')) vider()" class="text-(--color-text-muted) hover:text-(--color-error) hover:underline">Vider</button>
                        </div>
                    </div>

                    <div wire:ignore>
                        <p x-show="items.length === 0" class="text-sm text-(--color-text-muted) py-6 text-center border-y border-dashed border-(--color-border)">Les pièces touchées apparaissent ici.</p>
                        <div x-show="items.length > 0" x-cloak class="flex flex-col border-y border-(--color-border) divide-y divide-(--color-border)">
                            <template x-for="(item, i) in items" :key="i + '|' + item.base_name + '|' + item.prestation_id + '|' + item.color">
                                <div class="py-2.5">
                                    <div class="flex items-start gap-2.5">
                                        {{-- Pastille : la couleur de la ligne, modifiable --}}
                                        <button type="button" @click="couleursLigne = couleursLigne === i ? null : i" :title="item.color ? 'Couleur : ' + item.color : 'Ajouter une couleur'" aria-label="Couleur de la ligne"
                                                class="mt-0.5 w-5 h-5 flex-none rounded-full border" :class="item.color ? 'border-(--color-border)' : 'border-dashed border-(--color-text-muted)'"
                                                :style="pastille(item.color)"></button>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-baseline justify-between gap-2">
                                                <span class="text-sm font-semibold leading-tight" x-text="item.base_name"></span>
                                                <span class="text-sm font-semibold tabular-nums" x-text="fcfa(item.unit_price_fcfa * item.quantity)"></span>
                                            </div>
                                            <div class="flex items-center justify-between gap-2 mt-1">
                                                <span class="text-xs text-(--color-text-secondary) min-w-0" x-text="[item.prestation_name, item.color].filter(Boolean).join(' · ') + ' · ' + fcfa(item.unit_price_fcfa) + ' F'"></span>
                                                <span class="flex items-center gap-1 flex-none">
                                                    <button type="button" @click="moins(i)" aria-label="Retirer une unité"
                                                            class="w-7 h-7 rounded-lg border border-(--color-border) flex items-center justify-center text-sm hover:border-(--color-primary) hover:text-(--color-primary)">−</button>
                                                    <input type="number" inputmode="numeric" min="0" max="{{ \App\Livewire\Orders\Create::MAX_QUANTITY }}" :value="item.quantity" aria-label="Quantité"
                                                           @change="fixerQuantite(i, parseInt($event.target.value) || 0)" @focus="$event.target.select()"
                                                           class="w-11 h-7 rounded-lg border border-(--color-border) text-center text-sm font-semibold tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                                    <button type="button" @click="plus(i)" aria-label="Ajouter une unité"
                                                            class="w-7 h-7 rounded-lg border border-(--color-border) flex items-center justify-center text-sm hover:border-(--color-primary) hover:text-(--color-primary)">+</button>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-show="couleursLigne === i" x-transition.opacity class="mt-2 p-2 rounded-lg bg-(--color-bg)">
                                        @include('livewire.orders._color-swatches', ['current' => 'item.color', 'pick' => 'couleurLigne(i, %s)', 'picker' => 'couleurLigneDepuisSelecteur(i, $event.target.value)'])
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Prête le / remarque : préremplis, modifiables d'un toucher --}}
                    <div class="mt-4 flex flex-col gap-2.5 text-sm" x-data="{ note: @js($notes !== '') }">
                        <label class="flex items-center justify-between gap-3">
                            <span class="text-(--color-text-secondary)">Prête le</span>
                            <input type="date" wire:model="expectedAt" class="h-9 px-2.5 rounded-lg border border-(--color-border) text-sm">
                        </label>
                        @error('expectedAt') <p class="text-xs text-(--color-error)">{{ $message }}</p> @enderror
                        <button type="button" x-show="!note" @click="note = true; $nextTick(() => $refs.note.focus())" class="self-start text-[13px] font-medium text-(--color-primary) hover:underline">+ Ajouter une remarque</button>
                        <textarea x-show="note" x-cloak x-ref="note" wire:model="notes" rows="2" placeholder="Ex. tache sur la manche gauche" class="w-full px-3 py-2 rounded-lg border border-(--color-border) text-sm"></textarea>
                    </div>

                    {{-- Paiement : « Au retrait » par défaut --}}
                    <div x-show="total > 0" x-cloak class="mt-4">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-1.5">Paiement</div>
                        <div class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-(--color-bg)" role="radiogroup" aria-label="Paiement">
                            @foreach (['later' => 'Au retrait', 'full' => 'Payée', 'partial' => 'Acompte'] as $value => $label)
                                <button type="button" role="radio" :aria-checked="paymentChoice === '{{ $value }}'" @click="choisirPaiement('{{ $value }}')"
                                        class="h-9 rounded-lg text-[13px] font-semibold transition-colors"
                                        :class="paymentChoice === '{{ $value }}' ? 'bg-(--color-surface) text-(--color-primary) shadow-sm' : 'text-(--color-text-secondary) hover:text-(--color-text-primary)'">{{ $label }}</button>
                            @endforeach
                        </div>
                        @error('paymentChoice') <p class="text-xs text-(--color-error) mt-1.5">{{ $message }}</p> @enderror

                        <div x-show="paymentChoice === 'partial'" x-cloak class="mt-2.5">
                            <div class="relative">
                                <input type="number" inputmode="numeric" min="1" :max="total - 1" step="50" x-model="depositAmount" aria-label="Montant versé"
                                       placeholder="Montant versé" class="w-full h-10 pl-3 pr-14 rounded-lg border border-(--color-border) text-sm tabular-nums">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">FCFA</span>
                            </div>
                            <button type="button" x-show="moitie > 0 && moitie < total" @click="depositAmount = String(moitie)" class="mt-1.5 text-xs font-medium text-(--color-primary) hover:underline" x-text="'La moitié : ' + fcfa(moitie) + ' F'"></button>
                            @error('depositAmount') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div x-show="paymentChoice === 'full' || paymentChoice === 'partial'" x-cloak class="mt-2.5 flex flex-wrap gap-1.5">
                            @foreach ($paymentMethods as $method)
                                <button type="button" @click="paymentMethod = '{{ $method->value }}'"
                                        class="h-8 px-3 rounded-full border text-xs font-medium transition-colors"
                                        :class="paymentMethod === '{{ $method->value }}' ? 'border-(--color-primary) bg-(--color-primary) text-white' : 'border-(--color-border) hover:border-(--color-text-muted)'">
                                    {{ $method->label() }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Total --}}
                    <div class="mt-4 pt-3 border-t border-dashed border-(--color-border)">
                        <div class="flex justify-between items-baseline">
                            <span class="text-sm text-(--color-text-secondary)">Total</span>
                            <span class="font-display text-2xl font-bold tabular-nums" x-text="fcfa(total) + ' F'">0 F</span>
                        </div>
                        <div x-show="total > 0 && payeMaintenant > 0" x-cloak class="flex justify-between text-xs mt-1">
                            <span class="text-(--color-text-secondary)">Reste à payer au retrait</span>
                            <span class="font-semibold tabular-nums" :class="total - payeMaintenant > 0 ? 'text-(--color-warning-text)' : 'text-(--color-success-text)'"
                                  x-text="total - payeMaintenant > 0 ? fcfa(total - payeMaintenant) + ' F' : 'Rien, soldée'"></span>
                        </div>
                    </div>

                    @if ($errorMessage)
                        <div class="mt-3 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
                    @endif

                    {{-- L'action principale : un seul gros bouton, qui dit ce qu'il manque --}}
                    <button type="button" @click="valider()" wire:loading.attr="disabled" wire:target="create" data-guide="commande-valider"
                            class="max-lg:hidden mt-4 w-full py-3.5 rounded-xl text-[15px] font-semibold flex items-center justify-center gap-2 transition-colors disabled:opacity-60"
                            :class="pret ? 'bg-(--color-primary) text-white hover:bg-(--color-primary-600) shadow-sm' : 'bg-(--color-primary-tint) text-(--color-primary)'">
                        <span wire:loading.remove wire:target="create" class="flex items-center gap-2">
                            <svg x-show="pret" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                            <span x-text="libelleValider">{{ $validateLabel }}</span>
                        </span>
                        <span wire:loading wire:target="create">Enregistrement…</span>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    {{-- Variantes d'une pièce (manche courte / longue…) --}}
    <div wire:ignore x-show="variantes.open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-slate-900/40 flex items-end sm:items-center justify-center p-4" @click.self="variantes.open = false">
        <div class="w-full max-w-sm bg-(--color-surface) rounded-2xl p-5 shadow-xl">
            <div class="flex items-center gap-3 mb-4">
                <p class="flex-1 font-semibold"><span x-text="variantes.piece ? variantes.piece.name : ''"></span> : laquelle ?</p>
                <button type="button" @click="variantes.open = false" class="text-sm text-(--color-text-secondary) hover:underline">Annuler</button>
            </div>
            <div class="flex flex-col gap-2">
                <template x-for="variante in (variantes.piece ? variantes.piece.variants : [])" :key="variante.id">
                    <button type="button" @click="ajouterVariante(variante)"
                            class="h-12 px-4 rounded-xl border-2 border-(--color-border) text-[15px] font-semibold flex items-center justify-between hover:border-(--color-primary) active:scale-[.98] transition">
                        <span x-text="variante.name"></span>
                        <span class="text-(--color-text-secondary) font-medium tabular-nums" x-text="fcfa(variante.price) + ' F'"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- Pièce hors catalogue --}}
    <div wire:ignore x-show="autre.open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-slate-900/40 flex items-end sm:items-center justify-center p-4" @click.self="autre.open = false">
        <form @submit.prevent="ajouterAutre()" class="w-full max-w-sm bg-(--color-surface) rounded-2xl p-5 shadow-xl">
            <p class="font-semibold mb-4">Autre pièce · <span x-text="catalogue.services[prestationId] ? catalogue.services[prestationId].name : ''"></span></p>
            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom de la pièce</label>
            <input type="text" x-ref="autreNom" x-model="autre.name" placeholder="Ex. Nappe brodée" maxlength="150" class="w-full h-11 px-3 rounded-lg border border-(--color-border) text-sm">
            <label class="block text-xs font-medium text-(--color-text-secondary) mt-3 mb-1.5">Prix (FCFA)</label>
            <input type="number" inputmode="numeric" min="0" x-model="autre.price" placeholder="1000" class="w-full h-11 px-3 rounded-lg border border-(--color-border) text-sm">
            <p x-show="autre.erreur" x-text="autre.erreur" class="text-xs text-(--color-error) mt-2"></p>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" @click="autre.open = false" class="h-11 px-4 rounded-lg text-sm font-medium text-(--color-text-secondary) hover:underline">Annuler</button>
                <button type="submit" class="h-11 px-5 rounded-xl bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Ajouter au ticket</button>
            </div>
        </form>
    </div>

    {{-- Quantité tapée d'un coup (« 12 chemises ») --}}
    <div wire:ignore x-show="qty.open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-slate-900/40 flex items-end sm:items-center justify-center p-4" @click.self="qty.open = false">
        <form @submit.prevent="validerQuantite()" class="w-full max-w-xs bg-(--color-surface) rounded-2xl p-5 shadow-xl">
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
