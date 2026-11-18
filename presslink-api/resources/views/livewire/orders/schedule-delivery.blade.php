@php
    $field = 'w-full h-11 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $money = fn (int $amount) => number_format($amount, 0, ',', ' ').' F';
@endphp
<div>
    @if ($canSchedule)
        <div class="mb-5 rounded-xl border border-(--color-border) bg-(--color-surface) p-4" data-testid="schedule-delivery">
            @unless ($open)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm">
                        <div class="font-semibold">Livrer chez le client</div>
                        <div class="text-(--color-text-muted)">Le client peut la demander dans l'appli, ou vous la programmez ici. Frais de livraison : {{ $fee > 0 ? $money($fee) : 'gratuit' }}.</div>
                    </div>
                    <button type="button" wire:click="openForm" class="h-10 px-4 rounded-lg border border-(--color-primary) text-sm font-semibold text-(--color-primary) hover:bg-(--color-primary-tint)">Programmer la livraison</button>
                </div>
            @else
                <div class="font-semibold mb-3">Programmer la livraison</div>
                <form wire:submit="schedule" novalidate class="flex flex-col gap-3">
                    <div>
                        <label for="delivery-address" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Adresse</label>
                        <select id="delivery-address" wire:model.live="addressChoice" class="{{ $field }}">
                            @foreach ($this->addressChoices() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('addressChoice') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if ($addressChoice === 'new')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="delivery-commune" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Commune</label>
                                <select id="delivery-commune" wire:model="commune" class="{{ $field }}">
                                    <option value="">Choisir…</option>
                                    @foreach ($communes as $commune)
                                        <option value="{{ $commune }}">{{ $commune }}</option>
                                    @endforeach
                                </select>
                                @error('commune') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="delivery-neighborhood" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Quartier (facultatif)</label>
                                <input id="delivery-neighborhood" type="text" maxlength="80" wire:model="neighborhood" placeholder="ex. Riviera 3" class="{{ $field }}">
                            </div>
                        </div>
                        <div>
                            <label for="delivery-landmark" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Repère</label>
                            <input id="delivery-landmark" type="text" maxlength="255" wire:model="landmark" placeholder="ex. Portail vert face à la pharmacie" class="{{ $field }}">
                            @error('landmark') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="delivery-date" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Jour</label>
                            <select id="delivery-date" wire:model="date" class="{{ $field }}">
                                @foreach ($this->dateOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('date') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="delivery-slot" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Créneau</label>
                            <select id="delivery-slot" wire:model="slot" class="{{ $field }}">
                                @foreach ($this->slotOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('slot') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($couriers->isNotEmpty())
                        <div>
                            <label for="delivery-courier" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Confier à</label>
                            <select id="delivery-courier" wire:model="courierId" class="{{ $field }}">
                                <option value="">Personne pour l'instant (tous les coursiers la voient)</option>
                                @foreach ($couriers as $courier)
                                    <option value="{{ $courier->id }}">{{ $courier->name }}</option>
                                @endforeach
                            </select>
                            @error('courierId') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label for="delivery-note" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Note pour le coursier (facultatif)</label>
                        <input id="delivery-note" type="text" maxlength="500" wire:model="note" placeholder="ex. Appeler en arrivant" class="{{ $field }}">
                    </div>

                    <p class="text-xs text-(--color-text-muted)">
                        À encaisser à la porte : {{ $money($toCollect) }}{{ $fee > 0 ? ', dont '.$money($fee).' de frais de livraison' : '' }}. Le client est prévenu dans l'appli et peut annuler si le créneau ne lui convient pas.
                    </p>
                    @error('schedule') <p class="text-sm text-(--color-error)">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                        <button type="button" wire:click="closeForm" class="h-11 px-4 rounded-lg border border-(--color-border) text-sm font-medium text-(--color-text-secondary) hover:bg-(--color-bg)">Annuler</button>
                        <button type="submit" wire:loading.attr="disabled" class="h-11 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60" data-testid="confirm-schedule">Programmer</button>
                    </div>
                </form>
            @endunless
        </div>
    @endif
</div>
