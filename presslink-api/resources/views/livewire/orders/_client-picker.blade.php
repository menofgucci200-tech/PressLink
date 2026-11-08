{{-- Recherche ou création du client (écran de commande, dépôt rapide). --}}
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
