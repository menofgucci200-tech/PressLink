<div>
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('services.index') }}" class="w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center hover:border-(--color-primary)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
        </a>
        <div>
            <h1 class="font-display text-xl font-bold">{{ $service->name }}</h1>
            <p class="text-xs text-(--color-text-muted)">Prix de base : {{ number_format($service->price_fcfa, 0, ',', ' ') }} F</p>
        </div>
    </div>

    <div class="flex items-start justify-between gap-6 mb-6 mt-6">
        <p class="text-sm text-(--color-text-muted) max-w-md">
            Si ce service a des variantes actives, le staff devra en choisir une (avec son propre prix) lors de la création d'une commande, plutôt que d'utiliser le prix de base.
        </p>
        <button wire:click="$toggle('showCreateForm')" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) flex-none">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Ajouter une variante
        </button>
    </div>

    @if ($showCreateForm)
        <form wire:submit="createVariant" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-5 grid grid-cols-3 gap-4 items-start">
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom</label>
                <input type="text" wire:model="name" placeholder="Manche longue" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Prix (FCFA)</label>
                <input type="number" wire:model="priceFcfa" placeholder="1500" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                @error('priceFcfa') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end gap-2 justify-end">
                <button type="button" wire:click="$set('showCreateForm', false)" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium">Annuler</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="createVariant"
                        class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold disabled:opacity-60">
                    <span wire:loading.remove wire:target="createVariant">Créer</span>
                    <span wire:loading wire:target="createVariant">Création…</span>
                </button>
            </div>
        </form>
    @endif

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($variants->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucune variante — le prix de base s'applique.</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Variante</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Prix</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Statut</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($variants as $variant)
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg)">
                            <td class="px-5 py-3.5 font-medium">{{ $variant->name }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ number_format($variant->price_fcfa, 0, ',', ' ') }} F</td>
                            <td class="px-5 py-3.5">
                                <span class="text-xs font-medium {{ $variant->is_active ? 'text-(--color-success-text)' : 'text-(--color-text-muted)' }}">
                                    {{ $variant->is_active ? 'Actif' : 'Désactivé' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button wire:click="toggleActive({{ $variant->id }})"
                                        wire:loading.attr="disabled" wire:target="toggleActive({{ $variant->id }})"
                                        class="text-xs font-medium text-(--color-primary) disabled:opacity-60">
                                    {{ $variant->is_active ? 'Désactiver' : 'Activer' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
