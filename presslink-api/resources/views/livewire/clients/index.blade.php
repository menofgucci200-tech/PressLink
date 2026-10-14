<div>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Clients</h1>
            <p class="text-sm text-(--color-text-muted)">{{ is_countable($clients) ? $clients->total() : 0 }} client(s)</p>
        </div>
        <button wire:click="$toggle('showCreateForm')" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Nouveau client
        </button>
    </div>

    @if ($errorMessage)
        <div class="mb-5 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
    @endif

    @if ($showCreateForm)
        <form novalidate wire:submit="createClient" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-5 grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Prénom</label>
                <input type="text" wire:model="firstName" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('firstName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom</label>
                <input type="text" wire:model="lastName" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('lastName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Téléphone</label>
                <input type="text" wire:model="phone" placeholder="+2250708124400" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <p class="sm:col-span-3 text-xs text-(--color-text-muted)">
                Le client pourra se connecter à l'app avec ce numéro et le mot de passe par défaut
                <span class="font-mono font-semibold">{{ \App\Models\Customer::DEFAULT_WALK_IN_PASSWORD }}</span>,
                qu'il pourra changer ensuite dans son profil.
            </p>
            <div class="sm:col-span-3 flex gap-2 justify-end">
                <button type="button" wire:click="$set('showCreateForm', false)" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium">Annuler</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="createClient"
                        class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold disabled:opacity-60">
                    <span wire:loading.remove wire:target="createClient">Créer</span>
                    <span wire:loading wire:target="createClient">Création…</span>
                </button>
            </div>
        </form>
    @endif

    <div class="relative max-w-sm mb-5">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Nom ou téléphone…"
               class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-(--color-surface)">
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-clip list-loading-target"
         wire:loading.delay.class="is-loading" wire:target="search, sortBy, gotoPage, nextPage, previousPage">
        @if ($clients->isEmpty())
            @if ($search !== '')
                <x-empty-state icon="search" title="Aucun client ne correspond à « {{ $search }} »">
                    Cherchez par prénom, nom ou numéro de téléphone (ex. 07 12…).
                    <x-slot:actions>
                        <button type="button" wire:click="$set('search', '')" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">Effacer la recherche</button>
                        <button type="button" wire:click="$set('showCreateForm', true)" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Ajouter ce client</button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <x-empty-state icon="users" title="Aucun client pour le moment">
                    Ajoutez vos clients ici, ou créez-les directement lors d'une nouvelle commande. Ils pourront suivre leurs commandes dans l'application.
                    <x-slot:actions>
                        <button type="button" wire:click="$set('showCreateForm', true)" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Ajouter un client</button>
                    </x-slot:actions>
                </x-empty-state>
            @endif
        @else
            @php $stickyTh = 'lg:sticky lg:top-16 lg:z-[5] bg-(--color-bg)'; @endphp
            <div class="overflow-x-auto lg:overflow-visible"><table class="w-full min-w-[640px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="text-left">
                        <x-sort-th column="nom" label="Client" :sort="$sort" :direction="$direction" class="{{ $stickyTh }}" />
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) {{ $stickyTh }}">Téléphone</th>
                        <x-sort-th column="commandes" label="Commandes" :sort="$sort" :direction="$direction" align="right" class="{{ $stickyTh }}" />
                        <th class="w-10 {{ $stickyTh }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clients as $client)
                        <tr wire:key="client-{{ $client->id }}" data-href="{{ route('clients.show', $client) }}" tabindex="0" role="link" class="border-t border-(--color-border)">
                            <td class="px-5 py-3.5 font-medium">{{ $client->fullName() }}
                            @unless ($client->is_active)
                                <span class="ml-1.5 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-error-tint) text-(--color-error)" title="Compte désactivé par PressLink : le client ne peut plus se connecter à l'application.">Compte désactivé</span>
                            @endunless</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary) tabular-nums">
                                {{ $client->phone }}
                                <x-copy-button :value="$client->phone" label="Copier le téléphone" class="copy-btn--reveal ml-0.5" />
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $client->orders_count }}</td>
                            <td class="pr-5 py-3.5 text-right">
                                <a href="{{ route('clients.show', $client) }}" wire:navigate tabindex="-1" class="row-chevron text-(--color-text-muted)" aria-hidden="true">›</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    @if (method_exists($clients, 'links'))
        <div class="mt-5">{{ $clients->links() }}</div>
    @endif
</div>
