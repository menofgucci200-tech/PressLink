<div>
    <div class="flex items-start justify-between gap-6 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Tarifs</h1>
            <p class="text-sm text-(--color-text-muted)">Grille de prix utilisée lors de la création des commandes.</p>
        </div>
        <button wire:click="$toggle('showCreateForm')" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Ajouter un service
        </button>
    </div>

    @if ($showCreateForm)
        <form wire:submit="createService" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 mb-5">
            <div class="grid grid-cols-3 gap-4 items-start">
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom</label>
                    <input type="text" wire:model="name" placeholder="Chemise" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Prix de base (FCFA)</label>
                    <input type="number" wire:model="priceFcfa" placeholder="1000" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                    @error('priceFcfa') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5 pt-5 border-t border-(--color-border)">
                <div class="flex items-center justify-between mb-3">
                    <label class="text-xs font-medium text-(--color-text-secondary)">Variantes (facultatif — ex. "Manche courte", "Manche longue")</label>
                    <button type="button" wire:click="addVariantRow" class="text-xs font-medium text-(--color-primary) hover:underline">
                        + Ajouter une variante
                    </button>
                </div>

                @if (empty($variantRows))
                    <p class="text-xs text-(--color-text-muted)">Aucune variante — le service n'aura que son prix de base.</p>
                @else
                    <div class="flex flex-col gap-2">
                        @foreach ($variantRows as $index => $row)
                            <div class="grid grid-cols-3 gap-3 items-start">
                                <div class="col-span-2">
                                    <input type="text" wire:model="variantRows.{{ $index }}.name" placeholder="Nom de la variante"
                                        class="w-full h-9 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                                    @error("variantRows.{$index}.name") <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="number" wire:model="variantRows.{{ $index }}.priceFcfa" placeholder="Prix FCFA"
                                        class="w-full h-9 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
                                    <button type="button" wire:click="removeVariantRow({{ $index }})"
                                        class="flex-none w-9 h-9 rounded-lg border border-(--color-border) flex items-center justify-center text-(--color-text-muted) hover:border-(--color-error) hover:text-(--color-error)">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path></svg>
                                    </button>
                                </div>
                                @error("variantRows.{$index}.priceFcfa") <p class="text-xs text-(--color-error) col-span-3 -mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-2 justify-end mt-5">
                <button type="button" wire:click="$set('showCreateForm', false)" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium">Annuler</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="createService"
                        class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold disabled:opacity-60">
                    <span wire:loading.remove wire:target="createService">Créer</span>
                    <span wire:loading wire:target="createService">Création…</span>
                </button>
            </div>
        </form>
    @endif

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($services->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun service pour le moment.</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Service</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) text-right">Prix de base</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Variantes</th>
                        <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Statut</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($services as $service)
                        <tr class="border-t border-(--color-border) hover:bg-(--color-bg)">
                            <td class="px-5 py-3.5 font-medium">{{ $service->name }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ number_format($service->price_fcfa, 0, ',', ' ') }} F</td>
                            <td class="px-5 py-3.5">
                                <a href="{{ route('services.variants', $service) }}" class="text-(--color-primary) font-medium">
                                    {{ $service->variants_count }} variante(s)
                                </a>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="text-xs font-medium {{ $service->is_active ? 'text-(--color-success-text)' : 'text-(--color-text-muted)' }}">
                                    {{ $service->is_active ? 'Actif' : 'Désactivé' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button wire:click="toggleActive({{ $service->id }})"
                                        wire:loading.attr="disabled" wire:target="toggleActive({{ $service->id }})"
                                        class="text-xs font-medium text-(--color-primary) disabled:opacity-60">
                                    {{ $service->is_active ? 'Désactiver' : 'Activer' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
