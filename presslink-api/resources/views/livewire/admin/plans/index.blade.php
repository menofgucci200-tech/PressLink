@php
    $input = 'w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums focus:outline-none focus:border-(--color-primary)';
    $label = 'block text-xs font-medium text-(--color-text-secondary) mb-1.5';
@endphp
<div class="max-w-5xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Formules et prix</h1>
        <p class="text-sm text-(--color-text-muted)">Prix, commandes par mois et fonctions de chaque formule, essai gratuit, options et commandes en plus. Les changements s'appliquent tout de suite.</p>
    </div>

    <form novalidate wire:submit="save">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            @foreach ($planCases as $plan)
                <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5" data-plan="{{ $plan->value }}">
                    <div class="flex items-baseline justify-between mb-4">
                        <h2 class="font-display text-lg font-bold">{{ $plan->label() }}</h2>
                        <span class="text-xs text-(--color-text-muted)">{{ $pressingsPerPlan[$plan->value] }} pressing(s)</span>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="{{ $label }}" for="monthly-{{ $plan->value }}">Prix par mois (FCFA)</label>
                            <input id="monthly-{{ $plan->value }}" type="number" min="0" wire:model="plans.{{ $plan->value }}.monthly" placeholder="Sur devis" class="{{ $input }}">
                            @error("plans.{$plan->value}.monthly") <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="annual-{{ $plan->value }}">Prix par an (FCFA)</label>
                            <input id="annual-{{ $plan->value }}" type="number" min="0" wire:model="plans.{{ $plan->value }}.annual" placeholder="Sur devis" class="{{ $input }}">
                            @error("plans.{$plan->value}.annual") <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}" for="orders-{{ $plan->value }}">Commandes par mois</label>
                            <input id="orders-{{ $plan->value }}" type="number" min="1" wire:model="plans.{{ $plan->value }}.orders_limit" placeholder="Illimité" class="{{ $input }}">
                            @error("plans.{$plan->value}.orders_limit") <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <div class="{{ $label }}">Fonctions incluses</div>
                            <div class="space-y-1.5">
                                @foreach ($features as $feature)
                                    <label class="flex items-center gap-2.5 text-sm cursor-pointer">
                                        <input type="checkbox" wire:model="plans.{{ $plan->value }}.features" value="{{ $feature->value }}" class="w-4 h-4 rounded border-(--color-border) accent-(--color-primary)">
                                        {{ $feature->label() }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-[11px] text-(--color-text-muted) mt-2">Comptoir, suivi client, encaissement, catalogue, clients et signalements sont dans toutes les formules.</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <h2 class="font-display text-base font-bold mb-1">Essai et offre</h2>
                <p class="text-sm text-(--color-text-muted) mb-3">Toutes les fonctions sont ouvertes pendant l'essai. S'applique aux pressings créés ensuite.</p>
                <label class="{{ $label }}" for="trial-days">Durée (jours)</label>
                <input id="trial-days" type="number" min="0" max="365" wire:model="trialDays" class="{{ $input }}">
                @error('trialDays') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror

                <label class="{{ $label }} mt-4" for="launch-offer">Offre de lancement (vide = aucune)</label>
                <textarea id="launch-offer" rows="3" wire:model="launchOffer" class="w-full px-3 py-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)"></textarea>
                <p class="text-[11px] text-(--color-text-muted) mt-1">Affichée sur la page Nos offres du site et la page Formules des pressings.</p>

                <label class="mt-4 flex items-start gap-2.5 text-sm cursor-pointer">
                    <input type="checkbox" wire:model="blockAfterPeriodEnd" class="mt-0.5 w-4 h-4 rounded border-(--color-border) accent-(--color-primary)">
                    <span>Bloquer les nouvelles commandes {{ \App\Models\Subscription::PERIOD_GRACE_DAYS }} jours après la fin de la période payée
                        <span class="block text-[11px] text-(--color-text-muted)">Vérifiez d'abord les dates de fin des pressings actifs (tableau de bord : « Périodes payées terminées »).</span></span>
                </label>
                @error('launchOffer') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <h2 class="font-display text-base font-bold mb-1">Prix des options</h2>
                <p class="text-sm text-(--color-text-muted) mb-3">Une fonction prise seule, par mois (FCFA). Vide = prix communiqué par téléphone.</p>
                <div class="space-y-2">
                    @foreach ($features as $feature)
                        <div class="flex items-center gap-3">
                            <label class="flex-1 text-sm" for="option-{{ $feature->value }}">{{ $feature->label() }}</label>
                            <input id="option-{{ $feature->value }}" type="number" min="0" wire:model="optionPrices.{{ $feature->value }}" placeholder="—" class="w-28 h-9 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums focus:outline-none focus:border-(--color-primary)">
                        </div>
                        @error("optionPrices.{$feature->value}") <p class="text-xs text-(--color-error)">{{ $message }}</p> @enderror
                    @endforeach
                </div>
            </div>

            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5" data-testid="payment-fee-rates">
                <h2 class="font-display text-base font-bold mb-1">Frais de paiement en ligne</h2>
                <p class="text-sm text-(--color-text-muted) mb-3">Frais de l'opérateur ajoutés au prix et payés par le pressing, en % (0 = PressLink les prend en charge). Ajustez-les d'après les frais réels retenus, visibles dans « Demandes et paiements ».</p>
                <div class="space-y-2">
                    @foreach (\App\Models\SubscriptionRequest::ONLINE_METHODS as $code => $label)
                        <div class="flex items-center gap-3">
                            <label class="flex-1 text-sm" for="fee-{{ $code }}">{{ $label }}</label>
                            <input id="fee-{{ $code }}" type="number" min="0" max="{{ \App\Models\SubscriptionRequest::MAX_PAYMENT_FEE_RATE }}" step="0.1" wire:model="paymentFeeRates.{{ $code }}" class="w-24 h-9 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums focus:outline-none focus:border-(--color-primary)">
                            <span class="text-sm text-(--color-text-muted)">%</span>
                        </div>
                        @error("paymentFeeRates.{$code}") <p class="text-xs text-(--color-error)">{{ $message }}</p> @enderror
                    @endforeach
                </div>
            </div>

            <div class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5">
                <h2 class="font-display text-base font-bold mb-1">Commandes en plus</h2>
                <p class="text-sm text-(--color-text-muted) mb-3">Proposées sur la page Abonnement, valables jusqu'à la fin du mois.</p>
                <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-2 text-[11px] font-medium text-(--color-text-muted) mb-1">
                    <span>Commandes</span><span>Prix (FCFA)</span><span class="w-8"></span>
                </div>
                <div class="space-y-2">
                    @foreach ($orderPacks as $i => $pack)
                        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-2 items-center" wire:key="pack-{{ $i }}">
                            <input type="number" min="1" wire:model="orderPacks.{{ $i }}.count" aria-label="Nombre de commandes" class="w-full min-w-0 h-9 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums focus:outline-none focus:border-(--color-primary)">
                            <input type="number" min="0" wire:model="orderPacks.{{ $i }}.price" placeholder="—" aria-label="Prix" class="w-full min-w-0 h-9 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm tabular-nums focus:outline-none focus:border-(--color-primary)">
                            <button type="button" wire:click="removePack({{ $i }})" aria-label="Retirer" class="w-8 h-9 rounded-lg text-(--color-text-muted) hover:text-(--color-error)">✕</button>
                        </div>
                        @error("orderPacks.{$i}.count") <p class="text-xs text-(--color-error)">{{ $message }}</p> @enderror
                    @endforeach
                </div>
                <button type="button" wire:click="addPack" class="mt-3 text-sm font-semibold text-(--color-primary) hover:underline">+ Ajouter une ligne</button>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="h-11 px-6 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Enregistrer les formules</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
