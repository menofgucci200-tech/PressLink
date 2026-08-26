<div>
    <div class="flex items-start justify-between gap-6 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Signalements</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $openCount }} signalement(s) en attente de traitement</p>
        </div>
    </div>

    <div class="flex gap-2 mb-5">
        @foreach (['open' => 'En attente', 'resolved' => 'Résolus', '' => 'Tous'] as $value => $label)
            <button wire:click="$set('status', '{{ $value }}')"
                class="h-9 px-3.5 rounded-lg text-sm font-medium border {{ $status === $value ? 'bg-(--color-primary) text-white border-(--color-primary)' : 'bg-(--color-surface) text-(--color-text-secondary) border-(--color-border) hover:bg-(--color-bg)' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden w-fit max-w-full">
        @if ($issues->isEmpty())
            <div class="p-10 text-center text-sm text-(--color-text-muted)">
                Aucun signalement {{ $status === 'open' ? 'en attente' : ($status === 'resolved' ? 'résolu' : '') }} pour le moment.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="text-sm">
                    <thead>
                        <tr class="text-left text-xs text-(--color-text-muted) border-b border-(--color-border)">
                            <th class="px-4 py-3 font-medium">Commande</th>
                            <th class="px-4 py-3 font-medium">Client</th>
                            <th class="px-4 py-3 font-medium">Problème</th>
                            <th class="px-4 py-3 font-medium">Description</th>
                            <th class="px-4 py-3 font-medium">Statut</th>
                            <th class="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($issues as $issue)
                            <tr class="border-b border-(--color-border) last:border-0">
                                <td class="px-4 py-3 whitespace-nowrap align-top">
                                    <a href="{{ route('orders.show', $issue->order) }}" class="font-medium text-(--color-primary) hover:underline">
                                        {{ $issue->order->order_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap align-top">{{ $issue->customer?->fullName() }}</td>
                                <td class="px-4 py-3 whitespace-nowrap align-top">{{ $issue->category->label() }}</td>
                                <td class="px-4 py-3 text-(--color-text-secondary) max-w-xs break-words align-top">
                                    {{ $issue->description ?: '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap align-top">
                                    @if ($issue->status->value === 'resolved')
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-(--color-success-text)">
                                            <span class="w-1.5 h-1.5 rounded-full bg-(--color-success-text) flex-none"></span>
                                            Résolu
                                        </span>
                                        @if ($issue->resolution_note)
                                            <div class="text-xs text-(--color-text-secondary) mt-1 max-w-[16rem] whitespace-normal">
                                                « {{ $issue->resolution_note }} »
                                            </div>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-(--color-error)">
                                            <span class="w-1.5 h-1.5 rounded-full bg-(--color-error) flex-none"></span>
                                            En attente
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap align-top">
                                    @if ($issue->status->value !== 'resolved' && $resolvingIssueId !== $issue->id)
                                        <button wire:click="startResolving({{ $issue->id }})"
                                            class="text-xs font-medium text-(--color-primary) hover:underline">
                                            Marquer résolu
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if ($resolvingIssueId === $issue->id)
                                <tr class="border-b border-(--color-border) last:border-0 bg-(--color-bg)">
                                    <td colspan="6" class="px-4 py-4">
                                        <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">
                                            Comment ce problème a-t-il été résolu ? (ex. "Chemise retrouvée et remise au client")
                                        </label>
                                        <textarea wire:model="resolutionNote" rows="2" autofocus
                                            placeholder="Note de résolution (facultatif)"
                                            class="w-full max-w-lg px-3 py-2 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)"></textarea>
                                        <div class="flex gap-2 mt-2">
                                            <button wire:click="confirmResolve"
                                                class="h-8 px-3.5 rounded-lg bg-(--color-primary) text-white text-xs font-semibold hover:bg-(--color-primary-600)">
                                                Confirmer la résolution
                                            </button>
                                            <button wire:click="cancelResolving"
                                                class="h-8 px-3.5 rounded-lg border border-(--color-border) text-xs font-medium text-(--color-text-secondary) hover:bg-(--color-surface)">
                                                Annuler
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t border-(--color-border)">
                {{ $issues->links() }}
            </div>
        @endif
    </div>
</div>
