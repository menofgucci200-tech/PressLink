<div class="max-w-3xl mx-auto" data-skip-back>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Nouvelle commande</h1>
        <p class="text-sm text-(--color-text-muted)">Enregistrez un dépôt en quatre étapes : client, articles, détails et paiement, confirmation.</p>
    </div>

    <div class="flex items-center mb-7">
        @php $labels = [1 => 'Client', 2 => 'Articles', 3 => 'Détails et paiement', 4 => 'Confirmation']; @endphp
        @foreach ($labels as $n => $label)
            <div class="flex items-center flex-1 min-w-0 last:flex-none">
                <div class="flex items-center gap-2.5 flex-none">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-[13px] font-semibold transition-all duration-300 {{ $n === $step ? 'scale-110' : '' }}
                        {{ $n < $step ? 'bg-(--color-success) text-white' : ($n === $step ? 'bg-(--color-primary) text-white' : 'bg-(--color-border) text-(--color-text-muted)') }}">
                        @if ($n < $step) ✓ @else {{ $n }} @endif
                    </div>
                    <span class="text-[13.5px] font-medium transition-colors duration-300 {{ $n === $step ? '' : 'hidden sm:inline' }} {{ $n <= $step ? 'text-(--color-text-primary)' : 'text-(--color-text-muted)' }}">{{ $label }}</span>
                </div>
                @if ($n < 4)
                    <div class="flex-1 h-px mx-2 sm:mx-3.5 bg-(--color-border) overflow-hidden">
                        <div class="h-full bg-(--color-success) transition-all duration-500 ease-out" style="width:{{ $n < $step ? '100' : '0' }}%"></div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div wire:key="wizard-step-{{ $step }}" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-7 animate-step">

        {{-- Étape 1 : Client --}}
        @if ($step === 1)
            <h2 class="font-semibold text-[17px] mb-1">Sélectionner le client</h2>
            <p class="text-[13px] text-(--color-text-muted) mb-5">Recherchez un client existant ou créez-en un nouveau.</p>

            <div class="relative mb-5">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-(--color-text-muted)"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                <input type="text" wire:model.live.debounce.300ms="clientSearch" placeholder="Nom ou numéro de téléphone"
                       class="w-full h-11 pl-10 pr-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-primary)">
            </div>

            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-3">Clients</div>
            <div class="flex flex-col gap-2.5 mb-5">
                @forelse ($clients as $client)
                    <button type="button" wire:click="pickCustomer({{ $client->id }})"
                            class="flex items-center gap-3.5 w-full text-left px-4 py-3.5 rounded-lg border transition-all duration-150 hover:border-(--color-text-muted) hover:-translate-y-px {{ $client->is_active === false ? 'opacity-60' : '' }} {{ $selectedCustomerId === $client->id ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border)' }}">
                        <span class="w-9 h-9 rounded-full bg-(--color-primary-tint) text-(--color-primary) flex items-center justify-center text-[13px] font-semibold flex-none transition-transform duration-150 {{ $selectedCustomerId === $client->id ? 'scale-110' : '' }}">
                            {{ mb_strtoupper(mb_substr($client->first_name, 0, 1).mb_substr($client->last_name, 0, 1)) }}
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ $client->fullName() }}</span>
                            <span class="block text-[12.5px] text-(--color-text-secondary) tabular-nums">{{ $client->phone }}</span>
                        </span>
                        @if ($client->is_active === false)
                            <span class="ml-auto text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-(--color-error-tint) text-(--color-error)">Compte désactivé</span>
                        @endif
                    </button>
                @empty
                    <p class="text-sm text-(--color-text-muted)">Aucun client trouvé.</p>
                @endforelse
            </div>

            @if (! $showNewClientForm)
                <button type="button" wire:click="$set('showNewClientForm', true)" class="text-sm font-medium text-(--color-primary)">+ Créer un nouveau client</button>
            @else
                <div class="border border-(--color-border) rounded-lg p-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <input type="text" wire:model="newFirstName" placeholder="Prénom" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                        @error('newFirstName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input type="text" wire:model="newLastName" placeholder="Nom" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                        @error('newLastName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-phone-input wire:model="newPhone" />
                        @error('newPhone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <p class="sm:col-span-3 text-xs text-(--color-text-muted)">
                        En installant l'application PressLink avec ce numéro, le client retrouvera toutes ses commandes.
                    </p>
                    <div class="sm:col-span-3 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showNewClientForm', false)" class="h-9 px-3 rounded-lg border border-(--color-border) text-sm">Annuler</button>
                        <button type="button" wire:click="createAndPickCustomer" class="h-9 px-3 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Créer</button>
                    </div>
                </div>
            @endif
        @endif

        {{-- Étape 2 : Articles --}}
        @if ($step === 2)
            <h2 class="font-semibold text-[17px] mb-1">Ajouter des articles</h2>
            <p class="text-[13px] text-(--color-text-muted) mb-5">Choisissez un service, sa variante et sa couleur si besoin.</p>

            <div class="border border-(--color-border) rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Service</label>
                        <select wire:model.live="pickerService" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                            <option value="">— Sélectionner —</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}">
                                    {{ $service->name }}
                                    @if ($service->variants_count > 0)
                                        (variantes)
                                    @else
                                        · {{ number_format($service->price_fcfa, 0, ',', ' ') }} F
                                    @endif
                                </option>
                            @endforeach
                            <option value="other">Autre article (non listé)</option>
                        </select>
                        @error('pickerService') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if ($pickerService !== '' && $pickerService !== 'other' && $this->pickerServiceVariants->isNotEmpty())
                        <div>
                            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Variante</label>
                            <select wire:model.live="pickerVariant" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                                <option value="">— Sélectionner —</option>
                                @foreach ($this->pickerServiceVariants as $variant)
                                    <option value="{{ $variant->id }}">{{ $variant->name }} · {{ number_format($variant->price_fcfa, 0, ',', ' ') }} F</option>
                                @endforeach
                                <option value="other">Autre (non listée)</option>
                            </select>
                            @error('pickerVariant') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                @if ($pickerService === 'other' || $pickerVariant === 'other')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Nom de l'article</label>
                            <input type="text" wire:model="pickerCustomName" placeholder="Ex. Nappe" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                            @error('pickerCustomName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Prix (FCFA)</label>
                            <input type="number" wire:model="pickerCustomPrice" placeholder="1000" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                            @error('pickerCustomPrice') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Couleur (optionnel)</label>
                        <input type="text" wire:model="pickerColor" placeholder="Ex. Bleu" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                        @error('pickerColor') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Quantité</label>
                            <input type="number" min="1" wire:model="pickerQuantity" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                            @error('pickerQuantity') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" wire:click="addPickedItem" wire:loading.attr="disabled" wire:target="addPickedItem"
                                class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60 flex-none">
                            <span wire:loading.remove wire:target="addPickedItem">+ Ajouter</span>
                            <span wire:loading wire:target="addPickedItem">…</span>
                        </button>
                    </div>
                </div>
            </div>

            @if ($items === [])
                <p class="text-sm text-(--color-text-muted) mb-4">Aucun article ajouté pour l'instant.</p>
            @else
                <div class="flex flex-col mb-4">
                    @foreach ($items as $i => $item)
                        <div wire:key="item-{{ $i }}-{{ $item['name'] }}" class="flex items-center justify-between py-3.5 border-b border-(--color-border) first:border-t animate-step">
                            <span class="text-sm font-medium">{{ $item['name'] }}</span>
                            <div class="flex items-center gap-3">
                                <button type="button" wire:click="decrementItem({{ $i }})"
                                        class="w-7 h-7 rounded-md border border-(--color-border) flex items-center justify-center text-sm transition-colors duration-150 hover:border-(--color-primary) hover:text-(--color-primary)">−</button>
                                <span class="w-4 text-center font-semibold tabular-nums">{{ $item['quantity'] }}</span>
                                <button type="button" wire:click="incrementItem({{ $i }})"
                                        class="w-7 h-7 rounded-md border border-(--color-border) flex items-center justify-center text-sm transition-colors duration-150 hover:border-(--color-primary) hover:text-(--color-primary)">+</button>
                                <span class="w-20 text-right font-semibold tabular-nums">{{ number_format($item['unit_price_fcfa'] * $item['quantity'], 0, ',', ' ') }} F</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div wire:key="total-{{ $this->total }}" class="flex justify-between items-center pt-4 border-t border-dashed border-(--color-border)">
                    <span class="text-sm text-(--color-text-secondary)">Total</span>
                    <span class="font-display text-2xl font-bold tabular-nums animate-step">{{ number_format($this->total, 0, ',', ' ') }} FCFA</span>
                </div>
            @endif
        @endif

        {{-- Étape 3 : Détails --}}
        @if ($step === 3)
            <h2 class="font-semibold text-[17px] mb-5">Détails et paiement</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Date prévue de retrait</label>
                    <input type="date" wire:model="expectedAt" class="w-full h-10 px-3 rounded-lg border border-(--color-border) text-sm">
                </div>
            </div>
            <div class="mb-5">
                <label class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Notes (optionnel)</label>
                <textarea wire:model="notes" rows="3" placeholder="Ex. tache sur la manche gauche" class="w-full px-3 py-2 rounded-lg border border-(--color-border) text-sm"></textarea>
            </div>

            {{-- Paiement : déjà payé, acompte (avec le reste à payer) ou au retrait --}}
            @if ($this->total > 0)
                @php
                    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
                    $choices = [
                        'full' => ['title' => 'Déjà payée', 'hint' => 'Le client règle les '.$fcfa($this->total).' F maintenant', 'icon' => '<path d="M20 6 9 17l-5-5"></path>'],
                        'partial' => ['title' => 'Acompte', 'hint' => 'Une partie maintenant, le reste au retrait', 'icon' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 3a9 9 0 0 1 0 18Z" fill="currentColor"></path>'],
                        'later' => ['title' => 'Au retrait', 'hint' => 'Le client paiera en récupérant', 'icon' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path>'],
                    ];
                    $remaining = max(0, $this->total - $this->amountPaidNow);
                @endphp
                <div class="mb-5">
                    <div class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Paiement</div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5" role="radiogroup" aria-label="Paiement">
                        @foreach ($choices as $value => $choice)
                            @php $isOn = $paymentChoice === $value; @endphp
                            <label wire:key="pay-{{ $value }}"
                                   class="relative flex items-start gap-3 p-3.5 rounded-xl border transition-all duration-150
                                          {{ $isOn ? 'border-(--color-primary) bg-(--color-primary-tint) shadow-[0_0_0_1px_var(--color-primary)]' : 'border-(--color-border) hover:border-(--color-text-muted)' }}">
                                <input type="radio" wire:model.live="paymentChoice" value="{{ $value }}" class="sr-only">
                                <span class="flex-none w-8 h-8 rounded-lg flex items-center justify-center {{ $isOn ? 'bg-(--color-primary) text-white' : 'bg-(--color-bg) text-(--color-text-secondary)' }}">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">{!! $choice['icon'] !!}</svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold">{{ $choice['title'] }}</span>
                                    <span class="block text-xs text-(--color-text-muted) mt-0.5">{{ $choice['hint'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('paymentChoice') <p class="text-xs text-(--color-error) mt-1.5">{{ $message }}</p> @enderror

                    @if ($paymentChoice === 'partial')
                        <div class="mt-3.5 grid grid-cols-1 sm:grid-cols-2 gap-4 animate-step">
                            <div>
                                <label for="deposit-amount" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Montant versé</label>
                                <div class="relative">
                                    <input id="deposit-amount" type="number" inputmode="numeric" min="1" max="{{ $this->total - 1 }}" step="50"
                                           wire:model.live.debounce.250ms="depositAmount" autofocus placeholder="Ex. {{ $fcfa((int) (round($this->total / 2 / 100) * 100)) }}"
                                           class="w-full h-10 pl-3 pr-12 rounded-lg border border-(--color-border) text-sm tabular-nums">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-(--color-text-muted)">FCFA</span>
                                </div>
                                @php $half = (int) (round($this->total / 2 / 100) * 100); @endphp
                                @if ($half > 0 && $half < $this->total)
                                    <button type="button" wire:click="$set('depositAmount', '{{ $half }}')" class="mt-1.5 text-xs font-medium text-(--color-primary) hover:underline">
                                        La moitié : {{ $fcfa($half) }} F
                                    </button>
                                @endif
                                @error('depositAmount') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="rounded-xl bg-(--color-warning-tint) px-4 py-3 self-start">
                                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-warning-text)">Reste à payer au retrait</div>
                                <div class="font-display text-xl font-bold tabular-nums mt-0.5 text-(--color-warning-text)">{{ $fcfa($remaining) }} F</div>
                            </div>
                        </div>
                    @endif

                    @if ($paymentChoice === 'full' || $paymentChoice === 'partial')
                        <div class="mt-3.5 animate-step">
                            <div class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Moyen de paiement</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($paymentMethods as $method)
                                    <label wire:key="method-{{ $method->value }}"
                                           class="inline-flex items-center h-9 px-3.5 rounded-full border text-[13px] font-medium transition-colors duration-150
                                                  {{ $paymentMethod === $method->value ? 'border-(--color-primary) bg-(--color-primary) text-white' : 'border-(--color-border) hover:border-(--color-text-muted)' }}">
                                        <input type="radio" wire:model.live="paymentMethod" value="{{ $method->value }}" class="sr-only">
                                        {{ $method->label() }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex justify-between items-center pt-4 border-t border-(--color-border)">
                <span class="text-sm font-medium">{{ $selectedCustomer?->fullName() }}</span>
                <span class="text-right">
                    <span class="block font-semibold tabular-nums">{{ number_format($this->total, 0, ',', ' ') }} FCFA</span>
                    @if ($paymentChoice !== '' && $this->total > 0)
                        <span class="block text-xs tabular-nums {{ $this->total - $this->amountPaidNow > 0 ? 'text-(--color-warning-text)' : 'text-(--color-success-text)' }}">
                            {{ $this->total - $this->amountPaidNow > 0 ? 'Reste '.number_format(max(0, $this->total - $this->amountPaidNow), 0, ',', ' ').' F au retrait' : 'Soldée' }}
                        </span>
                    @endif
                </span>
            </div>
        @endif

        {{-- Étape 4 : Confirmation --}}
        @if ($step === 4)
            <h2 class="font-semibold text-[17px] mb-5">Vérifier la commande</h2>

            <div class="mb-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Client</div>
                <div class="text-sm font-medium">{{ $selectedCustomer?->fullName() }}</div>
                <div class="text-[13px] text-(--color-text-secondary) tabular-nums">{{ $selectedCustomer?->phone }}</div>
            </div>

            <div class="mb-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Articles</div>
                @foreach ($items as $item)
                    <div class="flex justify-between text-sm py-1">
                        <span>{{ $item['name'] }} × {{ $item['quantity'] }}</span>
                        <span class="tabular-nums">{{ number_format($item['unit_price_fcfa'] * $item['quantity'], 0, ',', ' ') }} F</span>
                    </div>
                @endforeach
            </div>

            <div class="mb-5">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Retrait prévu</div>
                <div class="text-sm">{{ \Illuminate\Support\Carbon::parse($expectedAt)->translatedFormat('d F Y') }}</div>
            </div>

            @if ($this->total > 0)
                @php $remainingAtPickup = max(0, $this->total - $this->amountPaidNow); @endphp
                <div class="mb-5">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted) mb-2">Paiement</div>
                    <div class="text-sm">
                        @if ($paymentChoice === 'full')
                            Déjà payée · {{ \App\Enums\PaymentMethod::from($paymentMethod)->label() }}
                        @elseif ($paymentChoice === 'partial')
                            Acompte de {{ number_format($this->amountPaidNow, 0, ',', ' ') }} F · {{ \App\Enums\PaymentMethod::from($paymentMethod)->label() }}
                        @else
                            À payer au retrait
                        @endif
                    </div>
                </div>
            @endif

            @if ($errorMessage)
                <div class="mb-5 p-3 rounded-lg bg-(--color-error-tint) text-(--color-error) text-sm">{{ $errorMessage }}</div>
            @endif

            <div class="pt-4 border-t border-(--color-border)">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium">Total</span>
                    <span class="font-display text-2xl font-bold tabular-nums">{{ number_format($this->total, 0, ',', ' ') }} FCFA</span>
                </div>
                @if ($this->total > 0 && $this->amountPaidNow > 0)
                    <div class="flex justify-between items-center text-sm mt-1.5">
                        <span class="text-(--color-text-secondary)">Payé au dépôt</span>
                        <span class="tabular-nums font-medium text-(--color-success-text)">− {{ number_format($this->amountPaidNow, 0, ',', ' ') }} F</span>
                    </div>
                @endif
                @if ($this->total > 0)
                    <div class="flex justify-between items-center text-sm mt-1.5">
                        <span class="font-semibold">Reste à payer au retrait</span>
                        <span class="tabular-nums font-bold {{ $remainingAtPickup > 0 ? 'text-(--color-warning-text)' : 'text-(--color-success-text)' }}">{{ $remainingAtPickup > 0 ? number_format($remainingAtPickup, 0, ',', ' ').' F' : 'Rien, soldée' }}</span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="flex justify-between mt-5">
        <button type="button" wire:click="back" @if($step===1) disabled @endif
                class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium transition-colors duration-150 {{ $step === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:border-(--color-text-muted)' }}">
            ← Retour
        </button>

        @if ($step < 4)
            <button type="button" wire:click="next" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold transition-colors duration-150 hover:bg-(--color-primary-600)">
                Continuer →
            </button>
        @else
            <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                    class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold transition-colors duration-150 hover:bg-(--color-primary-600) disabled:opacity-60">
                <span wire:loading.remove wire:target="create">Créer la commande</span>
                <span wire:loading wire:target="create">Création…</span>
            </button>
        @endif
    </div>
</div>
