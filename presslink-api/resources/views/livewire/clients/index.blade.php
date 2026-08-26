<div>
    <div class="flex items-start justify-between gap-6 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Clients</h1>
            <p class="text-sm text-(--color-text-muted)">{{ is_countable($clients) ? $clients->total() : 0 }} client(s)</p>
        </div>
        <button wire:click="$toggle('showCreateForm')" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Nouveau client
        </button>
    </div>

    @if ($showCreateForm)
        <form wire:submit="createClient" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-5 grid grid-cols-3 gap-4 items-start">
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
            <div class="col-span-3 flex gap-2 justify-end">
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
               class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-bg) text-sm focus:outline-none focus:border-(--color-primary) focus:bg-white">
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($clients->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun client pour le moment.</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Client</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Téléphone</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Commandes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clients as $client)
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg) cursor-pointer" onclick="window.location='{{ route('clients.show', $client) }}'">
                            <td class="px-5 py-3.5 font-medium">{{ $client->fullName() }}</td>
                            <td class="px-5 py-3.5 text-(--color-text-secondary) tabular-nums">{{ $client->phone }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $client->orders_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if (method_exists($clients, 'links'))
        <div class="mt-5">{{ $clients->links() }}</div>
    @endif
</div>
