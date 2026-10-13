@php
    $th = 'px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)';
    $select = 'h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $subscriptionTones = [
        'trialing' => 'bg-(--color-info-tint) text-(--color-info)',
        'active' => 'bg-(--color-success-tint) text-(--color-success-text)',
        'expired' => 'bg-(--color-warning-tint) text-(--color-warning-text)',
        'canceled' => 'bg-(--color-border) text-(--color-text-secondary)',
    ];
    $summary = [
        ['label' => 'Actifs', 'count' => $statusCounts['active'] ?? 0, 'set' => ['status', 'active']],
        ['label' => 'Suspendus', 'count' => $statusCounts['suspended'] ?? 0, 'set' => ['status', 'suspended']],
        ['label' => 'En essai', 'count' => $subscriptionCounts['trialing'] ?? 0, 'set' => ['subscriptionStatus', 'trialing']],
        ['label' => 'Abonnés', 'count' => $subscriptionCounts['active'] ?? 0, 'set' => ['subscriptionStatus', 'active']],
        ['label' => 'Expirés', 'count' => $subscriptionCounts['expired'] ?? 0, 'set' => ['subscriptionStatus', 'expired']],
    ];
@endphp

<div>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3 mb-6">
        <div>
            <h1 class="font-display text-2xl font-bold mb-1">Pressings</h1>
            <p class="text-sm text-(--color-text-muted)">{{ $pressings->total() }} pressing(s){{ $hasFilters ? ' pour ces filtres' : ' sur la plateforme' }}.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.export', ['type' => 'pressings', ...array_filter($this->filters())]) }}"
               class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Exporter
            </a>
            <a href="{{ route('admin.pressings.create') }}" wire:navigate class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Nouveau pressing
            </a>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-5">
        @foreach ($summary as $chip)
            @php $isOn = $this->{$chip['set'][0]} === $chip['set'][1]; @endphp
            <button type="button" wire:click="$set('{{ $chip['set'][0] }}', '{{ $isOn ? '' : $chip['set'][1] }}')"
                    class="inline-flex items-center gap-2 h-9 px-3.5 rounded-full border text-sm font-medium transition-colors {{ $isOn ? 'border-(--color-primary) bg-(--color-primary-tint) text-(--color-primary)' : 'border-(--color-border) bg-(--color-surface) hover:border-(--color-text-muted)' }}">
                {{ $chip['label'] }}
                <span class="tabular-nums font-bold">{{ $chip['count'] }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <div class="relative w-full sm:w-auto sm:flex-1 sm:max-w-xs">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Nom, code, ville, téléphone…"
                   class="w-full h-10 pl-9 pr-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)">
        </div>
        <select wire:model.live="status" class="{{ $select }}" aria-label="Statut du pressing">
            <option value="">Tous les statuts</option>
            @foreach (\App\Enums\PressingStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="plan" class="{{ $select }}" aria-label="Formule">
            <option value="">Toutes les formules</option>
            @foreach (\App\Enums\SubscriptionPlan::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
        <select wire:model.live="subscriptionStatus" class="{{ $select }}" aria-label="Abonnement">
            <option value="">Tous les abonnements</option>
            @foreach ($subscriptionStatuses as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
            <option value="none">Sans abonnement</option>
        </select>
        <select wire:model.live="city" class="{{ $select }}" aria-label="Ville">
            <option value="">Toutes les villes</option>
            @foreach ($cities as $c)
                <option value="{{ $c }}">{{ $c }}</option>
            @endforeach
        </select>
        <select wire:model.live="sort" class="{{ $select }}" aria-label="Trier">
            <option value="recent">Plus récents</option>
            <option value="name">Nom (A → Z)</option>
            <option value="activity">Plus actifs ({{ \App\Livewire\Admin\Pressings\Index::RECENT_DAYS }} j)</option>
            <option value="last_order">Dernière commande</option>
        </select>
        @if ($hasFilters)
            <button wire:click="resetFilters" class="text-xs font-medium text-(--color-text-muted) hover:text-(--color-primary) underline">Réinitialiser</button>
        @endif
        <span wire:loading.delay class="text-xs text-(--color-text-muted)">Chargement…</span>
    </div>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        @if ($pressings->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-(--color-text-muted)">Aucun pressing trouvé.</div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[980px] whitespace-nowrap text-sm">
                <thead>
                    <tr class="bg-(--color-bg) text-left">
                        <th class="{{ $th }}">Pressing</th>
                        <th class="{{ $th }}">Abonnement</th>
                        <th class="{{ $th }} text-right">Équipe</th>
                        <th class="{{ $th }} text-right">Clients</th>
                        <th class="{{ $th }} text-right">Commandes</th>
                        <th class="{{ $th }}">Dernière commande</th>
                        <th class="{{ $th }}">Statut</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pressings as $pressing)
                        @php
                            $subscription = $pressing->subscription;
                            $lastOrderAt = $pressing->last_order_at ? \Illuminate\Support\Carbon::parse($pressing->last_order_at) : null;
                            $isDemo = $pressing->code === \App\Actions\Demo\ResetDemoPressing::PRESSING_CODE;
                        @endphp
                        <tr wire:key="pressing-{{ $pressing->id }}" class="border-t border-(--color-border) hover:bg-(--color-bg)">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('admin.pressings.show', $pressing) }}" wire:navigate class="font-medium hover:text-(--color-primary)">{{ $pressing->name }}</a>
                                @if ($isDemo)
                                    <span class="ml-1.5 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-warning-tint) text-(--color-warning-text)">Démo</span>
                                @endif
                                <div class="text-xs text-(--color-text-muted)"><span class="font-mono">{{ $pressing->code }}</span>{{ $pressing->city ? ' · '.$pressing->city : '' }}</div>
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($subscription)
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ $subscription->plan->label() }}</span>
                                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $subscriptionTones[$subscription->status->value] }}">{{ $subscription->status->label() }}</span>
                                    </div>
                                    @if ($subscription->status === \App\Enums\SubscriptionStatus::Trialing && $subscription->trial_ends_at)
                                        <div class="text-xs mt-0.5 {{ $subscription->trial_ends_at->isPast() ? 'text-(--color-error)' : ($subscription->trial_ends_at->lte(now()->addDays(7)) ? 'text-(--color-warning-text)' : 'text-(--color-text-muted)') }}">
                                            {{ $subscription->trial_ends_at->isPast() ? 'Essai terminé le ' : 'Fin d\'essai le ' }}{{ $subscription->trial_ends_at->format('d/m/Y') }}
                                        </div>
                                    @elseif ($subscription->orders_limit)
                                        <div class="text-xs text-(--color-text-muted) mt-0.5 tabular-nums">Quota {{ $subscription->orders_used }} / {{ number_format($subscription->orders_limit, 0, ',', ' ') }}</div>
                                    @endif
                                @else
                                    <span class="text-(--color-text-muted)">Sans abonnement</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $pressing->staff_count }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums">{{ $pressing->customers_count }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums">
                                <div class="font-semibold">{{ $pressing->recent_orders_count }}</div>
                                <div class="text-xs text-(--color-text-muted)">{{ $pressing->orders_count }} au total</div>
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($lastOrderAt)
                                    <span class="{{ $lastOrderAt->lt(now()->subDays(\App\Livewire\Admin\Dashboard::DORMANT_AFTER_DAYS)) ? 'text-(--color-warning-text) font-medium' : 'text-(--color-text-secondary)' }}"
                                          title="{{ $lastOrderAt->format('d/m/Y H:i') }}">{{ $lastOrderAt->diffForHumans() }}</span>
                                @else
                                    <span class="text-(--color-text-muted)">Aucune</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="text-xs font-medium {{ $pressing->status->value === 'active' ? 'text-(--color-success-text)' : 'text-(--color-warning-text)' }}">
                                    {{ $pressing->status->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button wire:click="toggleStatus({{ $pressing->id }})"
                                        data-confirm-title="{{ $pressing->status->value === 'active' ? 'Suspendre le pressing' : 'Réactiver le pressing' }}" data-confirm-label="{{ $pressing->status->value === 'active' ? 'Suspendre' : 'Réactiver' }}" data-confirm-tone="{{ $pressing->status->value === 'active' ? 'danger' : 'primary' }}"
                                        wire:confirm="{{ $pressing->status->value === 'active' ? 'Suspendre '.$pressing->name.' ? Il ne pourra plus créer de commande ni accueillir de nouveaux clients.' : 'Réactiver '.$pressing->name.' ?' }}"
                                        wire:loading.attr="disabled" wire:target="toggleStatus({{ $pressing->id }})"
                                        class="text-xs font-medium {{ $pressing->status->value === 'active' ? 'text-(--color-error)' : 'text-(--color-primary)' }} hover:underline disabled:opacity-60">
                                    {{ $pressing->status->value === 'active' ? 'Suspendre' : 'Réactiver' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="mt-5">{{ $pressings->links() }}</div>
</div>
