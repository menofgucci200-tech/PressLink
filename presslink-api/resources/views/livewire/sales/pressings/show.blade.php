@php
    $tones = [
        'info' => 'bg-(--color-info-tint) text-(--color-info)',
        'warning' => 'bg-(--color-warning-tint) text-(--color-warning-text)',
        'success' => 'bg-(--color-success-tint) text-(--color-success-text)',
        'error' => 'bg-(--color-error-tint) text-(--color-error)',
    ];
    $summary = $pressing->subscription?->summary();
    $card = 'bg-(--color-surface) border border-(--color-border) rounded-xl p-5';
    $input = 'w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $label = 'block text-xs font-medium text-(--color-text-secondary) mb-1.5';
    $dt = 'text-xs text-(--color-text-muted)';
@endphp
<div>
    <a href="{{ route('sales.pressings.index') }}" wire:navigate class="text-sm text-(--color-text-secondary) hover:text-(--color-primary)">← Mes pressings</a>
    <div class="flex flex-wrap items-center gap-3 mt-2 mb-6">
        <h1 class="font-display text-2xl font-bold">{{ $pressing->name }}</h1>
        @if ($summary)
            <span class="inline-flex items-center h-7 px-2.5 rounded-full text-xs font-semibold {{ $tones[$summary['tone']] }}">{{ $summary['label'] }}</span>
        @endif
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach ([
            ['Commandes ce mois', $activity['ordersThisMonth']],
            ['Commandes au total', $activity['ordersTotal']],
            ['Dernière commande', $activity['lastOrderAt'] ? \Illuminate\Support\Carbon::parse($activity['lastOrderAt'])->format('d/m/Y') : '—'],
            ['Membres de l\'équipe', $activity['team']],
        ] as [$statLabel, $statValue])
            <div class="{{ $card }} p-4!">
                <div class="{{ $dt }} mb-1">{{ $statLabel }}</div>
                <div class="font-display text-xl font-bold tabular-nums">{{ $statValue }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="{{ $card }}">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-display text-base font-bold">Coordonnées</h2>
                @unless ($editing)
                    <button type="button" wire:click="edit" class="text-sm font-semibold text-(--color-primary) hover:underline">Modifier</button>
                @endunless
            </div>
            @if ($editing)
                <form novalidate wire:submit="save" class="grid gap-3">
                    <div>
                        <label class="{{ $label }}" for="name">Nom du pressing</label>
                        <input id="name" type="text" wire:model="name" class="{{ $input }}">
                        @error('name') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="phone">Téléphone</label>
                        <input id="phone" type="tel" wire:model="phone" class="{{ $input }} tabular-nums">
                        @error('phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="city">Commune ou quartier</label>
                        <input id="city" type="text" wire:model="city" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="{{ $label }}" for="address">Adresse ou repère</label>
                        <input id="address" type="text" wire:model="address" class="{{ $input }}">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('editing', false)" class="h-9 px-4 rounded-lg border border-(--color-border) text-sm font-medium">Annuler</button>
                        <button type="submit" class="h-9 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Enregistrer</button>
                    </div>
                </form>
            @else
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div><dt class="{{ $dt }}">Téléphone</dt><dd class="tabular-nums">{{ \App\Support\PhoneNumber::national($pressing->phone) }}</dd></div>
                    <div><dt class="{{ $dt }}">Commune</dt><dd>{{ $pressing->city ?: '—' }}</dd></div>
                    <div class="col-span-2"><dt class="{{ $dt }}">Adresse</dt><dd>{{ $pressing->address ?: '—' }}</dd></div>
                    <div><dt class="{{ $dt }}">Inscrit le</dt><dd>{{ $pressing->created_at->format('d/m/Y') }}</dd></div>
                    <div><dt class="{{ $dt }}">Rattaché à vous le</dt><dd>{{ ($pressing->sales_agent_attached_at ?? $pressing->created_at)->format('d/m/Y') }}</dd></div>
                </dl>
                <h3 class="font-semibold text-sm mt-5 mb-2">Gérant</h3>
                @forelse ($pressing->admins as $admin)
                    <div class="text-sm">{{ $admin->name }} · <span class="tabular-nums">{{ \App\Support\PhoneNumber::national($admin->phone) ?: $admin->login }}</span></div>
                @empty
                    <div class="text-sm text-(--color-text-muted)">Pas encore de gérant.</div>
                @endforelse
            @endif
        </div>

        <div class="{{ $card }}">
            <h2 class="font-display text-base font-bold mb-4">Abonnement</h2>
            @if ($pressing->subscription)
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm mb-5">
                    <div><dt class="{{ $dt }}">Formule</dt><dd>{{ $pressing->subscription->isInTrial() ? 'Mois offert' : $pressing->subscription->plan->label() }}</dd></div>
                    <div><dt class="{{ $dt }}">Statut</dt><dd>{{ $summary['label'] ?? '—' }}</dd></div>
                </dl>
            @endif
            <h3 class="font-semibold text-sm mb-2">Paiements de l'abonnement</h3>
            @forelse ($payments as $payment)
                <div class="flex items-center justify-between gap-3 py-2 border-t border-(--color-border) text-sm">
                    <div>
                        <div>{{ $payment->label() }}</div>
                        <div class="{{ $dt }}">{{ ($payment->paid_online_at ?? $payment->handled_at)?->format('d/m/Y') }}</div>
                    </div>
                    <div class="font-semibold tabular-nums">{{ number_format($payment->amount_fcfa, 0, ',', ' ') }} F</div>
                </div>
            @empty
                <p class="text-sm text-(--color-text-muted)">Aucun paiement pour l'instant.</p>
            @endforelse
        </div>
    </div>
</div>
