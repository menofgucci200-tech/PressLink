@php
    $select = 'w-full h-11 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $ghost = 'h-11 px-4 rounded-lg border border-(--color-border) text-sm font-medium text-(--color-text-secondary) hover:bg-(--color-bg)';
    $primary = 'h-11 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60';
    $money = fn (int $amount) => number_format($amount, 0, ',', ' ').' F';
    $pickupCount = $stops->where('type', 'pickup')->count();
    $deliveryCount = $stops->where('type', 'delivery')->count();
@endphp
<div class="max-w-3xl">
    <div class="mb-5">
        <h1 class="font-display text-2xl font-bold mb-1">Tournée</h1>
        <p class="text-sm text-(--color-text-muted)">Les ramassages acceptés et les livraisons prévues, dans l'ordre des créneaux. Sur place : linge ramassé, commande livrée et payée, ou client absent.</p>
        @unless ($dispatches)
            <p class="mt-1 text-sm text-(--color-text-muted)">Vous voyez les passages qui vous sont confiés et ceux qui ne sont confiés à personne.</p>
        @endunless
    </div>

    @unless ($launched)
        <div class="mb-5 rounded-xl border border-(--color-info) bg-(--color-info-tint) px-4 py-3 text-sm">
            <span class="font-semibold">Pas encore visible par vos clients.</span> Le service s'ouvrira bientôt dans l'appli PressLink.
        </div>
    @endunless

    <div class="flex gap-2 overflow-x-auto pb-1 mb-4 -mx-1 px-1" role="tablist" aria-label="Jour de la tournée">
        @foreach ($this->dayOptions() as $value => $label)
            <button type="button" wire:click="$set('day', '{{ $value }}')" role="tab" aria-selected="{{ $day === $value ? 'true' : 'false' }}"
                class="flex-none h-9 px-3.5 rounded-lg text-sm font-medium border {{ $day === $value ? 'bg-(--color-primary) text-white border-(--color-primary)' : 'bg-(--color-surface) text-(--color-text-secondary) border-(--color-border) hover:bg-(--color-bg)' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($dispatches && $couriers->isNotEmpty())
        <div class="mb-4 flex items-center gap-2">
            <label for="round-courier-filter" class="flex-none text-sm font-medium text-(--color-text-secondary)">Coursier</label>
            <select id="round-courier-filter" wire:model.live="courierFilter" class="{{ $select }} sm:max-w-64" data-testid="courier-filter">
                <option value="">Tous les passages</option>
                <option value="none">Confiés à personne</option>
                @foreach ($couriers as $courier)
                    <option value="{{ $courier->id }}">{{ $courier->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($stops->isNotEmpty())
        <div class="mb-4 grid grid-cols-3 gap-2 text-center" data-testid="round-summary">
            <div class="rounded-xl bg-(--color-surface) border border-(--color-border) px-2 py-2.5">
                <div class="text-lg font-bold tabular-nums">{{ $pickupCount }}</div>
                <div class="text-[11px] text-(--color-text-muted)">ramassage{{ $pickupCount > 1 ? 's' : '' }}</div>
            </div>
            <div class="rounded-xl bg-(--color-surface) border border-(--color-border) px-2 py-2.5">
                <div class="text-lg font-bold tabular-nums">{{ $deliveryCount }}</div>
                <div class="text-[11px] text-(--color-text-muted)">livraison{{ $deliveryCount > 1 ? 's' : '' }}</div>
            </div>
            <div class="rounded-xl bg-(--color-surface) border border-(--color-border) px-2 py-2.5">
                <div class="text-lg font-bold tabular-nums">{{ $money($toCollect) }}</div>
                <div class="text-[11px] text-(--color-text-muted)">à encaisser</div>
            </div>
        </div>
    @endif

    @if ($stops->isEmpty())
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl">
            <x-empty-state icon="check" tone="success" title="Aucun passage prévu ce jour-là">
                Les ramassages que vous acceptez (menu Ramassages), les livraisons que vos clients demandent depuis l'appli et celles que vous programmez depuis une commande prête s'affichent ici.
            </x-empty-state>
        </div>
    @else
        <ol class="flex flex-col gap-3">
            @foreach ($stops as $stop)
                @php
                    $model = $stop['model'];
                    $customer = $model->customer;
                    $isPickup = $stop['type'] === 'pickup';
                    $isActing = $acting === $stop['key'];
                    $date = $isPickup ? $model->pickup_date : $model->delivery_date;
                @endphp
                <li wire:key="{{ $stop['key'] }}" class="bg-(--color-surface) border {{ $stop['late'] ? 'border-(--color-error)' : 'border-(--color-border)' }} rounded-xl p-4" data-testid="round-stop">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="inline-flex items-center h-6 px-2 rounded-full text-xs font-semibold {{ $isPickup ? 'text-(--color-warning-text) bg-(--color-warning-tint)' : 'text-(--color-primary) bg-(--color-primary-tint)' }}">
                                    {{ $isPickup ? 'Ramassage' : 'Livraison' }}
                                </span>
                                <span class="text-sm font-semibold tabular-nums">{{ $model->slot_from }} – {{ $model->slot_to }}</span>
                                @if ($stop['late'])
                                    <span class="text-xs font-semibold text-(--color-error)">En retard{{ $date->isToday() ? '' : ' · '.$date->locale('fr')->isoFormat('ddd D MMM') }}</span>
                                @endif
                            </div>
                            <div class="font-semibold">{{ $customer->fullName() }}</div>
                        </div>
                        <a href="tel:{{ $customer->phone }}" class="flex-none inline-flex items-center gap-1.5 h-10 px-3 rounded-lg border border-(--color-border) text-sm font-medium text-(--color-primary) hover:bg-(--color-bg)" aria-label="Appeler {{ $customer->fullName() }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/></svg>
                            <span class="tabular-nums">{{ \App\Support\PhoneNumber::format($customer->phone) }}</span>
                        </a>
                    </div>

                    <div class="mt-3 text-sm">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">{{ $model->address_label }}</div>
                        <div class="font-medium">{{ $model->place() }}</div>
                        <div class="text-(--color-text-secondary)">{{ $model->landmark }}</div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        @if ($isPickup)
                            @if ($model->items_estimate)
                                <span>Environ {{ $model->items_estimate }} pièce{{ $model->items_estimate > 1 ? 's' : '' }}</span>
                            @endif
                            <span class="text-(--color-text-muted)">Frais : {{ $model->pickup_fee_fcfa > 0 ? $money($model->pickup_fee_fcfa).', avec la commande' : 'gratuit' }}</span>
                        @else
                            <a href="{{ route('orders.show', $model->order) }}" wire:navigate class="font-medium text-(--color-primary) hover:underline">{{ $model->order->order_number }}</a>
                            <span>{{ $model->order->pieceCount() }} pièce{{ $model->order->pieceCount() > 1 ? 's' : '' }}</span>
                            @if ($model->amountToCollect() > 0)
                                <span class="font-semibold" data-testid="amount-to-collect">À encaisser : {{ $money($model->amountToCollect()) }}</span>
                            @else
                                <span class="font-semibold text-(--color-success-text)">Déjà payée</span>
                            @endif
                        @endif
                    </div>
                    @if (! $isPickup && $model->delivery_fee_fcfa > 0)
                        <p class="mt-1 text-xs text-(--color-text-muted)">Dont frais de livraison : {{ $money($model->delivery_fee_fcfa) }}</p>
                    @endif

                    @if ($model->note)
                        <p class="mt-2 text-sm text-(--color-text-secondary) break-words">« {{ $model->note }} »</p>
                    @endif

                    @if ($dispatches && $couriers->isNotEmpty())
                        <div class="mt-3 flex items-center gap-2">
                            <label for="courier-{{ $stop['key'] }}" class="flex-none text-xs font-medium text-(--color-text-secondary)">Confié à</label>
                            <select id="courier-{{ $stop['key'] }}" wire:change="assign('{{ $stop['key'] }}', $event.target.value)" class="{{ $select }} h-10 sm:max-w-64" data-testid="assign-courier">
                                <option value="" @selected($model->courier_id === null)>Personne (tous les coursiers)</option>
                                @foreach ($couriers as $courier)
                                    <option value="{{ $courier->id }}" @selected($model->courier_id === $courier->id)>{{ $courier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif ($model->courier_id === auth()->id())
                        <p class="mt-3 text-xs font-semibold text-(--color-primary)">Confié à vous</p>
                    @endif

                    @if ($canRun)
                        @if ($isActing && $mode === 'collect')
                            <div class="mt-4 rounded-lg bg-(--color-bg) p-3 animate-step">
                                <label for="round-pieces" class="block text-sm font-medium mb-1.5">Pièces comptées chez le client</label>
                                <input id="round-pieces" type="number" inputmode="numeric" min="1" max="9999" wire:model="pieces" class="{{ $select }} text-lg font-semibold" autofocus>
                                @error('pieces') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                                <label for="round-note" class="block text-xs font-medium text-(--color-text-secondary) mt-3 mb-1.5">Remarque (facultatif)</label>
                                <input id="round-note" type="text" maxlength="500" wire:model="note" placeholder="ex. Tache sur la chemise blanche" class="{{ $select }}">
                                <p class="mt-2 text-xs text-(--color-text-muted)">Le dépôt est créé tout de suite, avec son numéro. Le détail et les prix se saisissent à l'atelier, comme un dépôt rapide.</p>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button type="button" wire:click="stopActing" class="{{ $ghost }}">Annuler</button>
                                    <button type="button" wire:click="confirmCollect" wire:loading.attr="disabled" class="{{ $primary }}" data-testid="confirm-collect">Créer le dépôt</button>
                                </div>
                            </div>
                        @elseif ($isActing && $mode === 'deliver')
                            @php $amount = $model->amountToCollect(); @endphp
                            <div class="mt-4 rounded-lg bg-(--color-bg) p-3 animate-step">
                                @if ($amount > 0)
                                    <div class="text-sm font-medium mb-2">Encaisser {{ $money($amount) }} : le client a payé par</div>
                                    <div class="grid grid-cols-2 gap-2">
                                        @foreach ($this->paymentMethods() as $value => $label)
                                            <label class="flex items-center gap-2 min-h-11 px-3 py-1.5 rounded-lg border text-sm leading-tight cursor-pointer {{ $paymentMethod === $value ? 'border-(--color-primary) bg-(--color-primary-tint)' : 'border-(--color-border) bg-(--color-surface)' }}">
                                                <input type="radio" wire:model.live="paymentMethod" value="{{ $value }}" class="sr-only">
                                                <x-payment-method-logo :method="$value" :size="22" />
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('paymentMethod') <p class="text-xs text-(--color-error) mt-2">{{ $message }}</p> @enderror
                                    <p class="mt-2 text-xs text-(--color-text-muted)">Comme au comptoir, la commande n'est remise que payée. L'encaissement compte dans la caisse du jour.</p>
                                @else
                                    <p class="text-sm">La commande est déjà payée : confirmez la remise au client.</p>
                                @endif
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button type="button" wire:click="stopActing" class="{{ $ghost }}">Annuler</button>
                                    <button type="button" wire:click="confirmDeliver" wire:loading.attr="disabled" class="{{ $primary }}" data-testid="confirm-deliver">{{ $amount > 0 ? 'Payée et livrée' : 'Livrée' }}</button>
                                </div>
                            </div>
                        @elseif ($isActing && $mode === 'missed')
                            <div class="mt-4 rounded-lg bg-(--color-bg) p-3 animate-step">
                                <p class="text-sm">{{ $isPickup ? 'Le ramassage sera clos et le client prévenu : il pourra refaire une demande.' : 'La commande reste prête au pressing ; le client est prévenu et peut choisir un autre créneau.' }}</p>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button type="button" wire:click="stopActing" class="{{ $ghost }}">Retour</button>
                                    <button type="button" wire:click="confirmMissed" wire:loading.attr="disabled" class="h-11 px-4 rounded-lg bg-(--color-error) text-white text-sm font-semibold disabled:opacity-60" data-testid="confirm-missed">Client absent</button>
                                </div>
                            </div>
                        @elseif ($isActing && $mode === 'reschedule')
                            <div class="mt-4 rounded-lg bg-(--color-bg) p-3 animate-step">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="round-date" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Jour</label>
                                        <select id="round-date" wire:model="date" class="{{ $select }}">
                                            @foreach ($this->rescheduleDates() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('date') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="round-slot" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Créneau</label>
                                        <select id="round-slot" wire:model="slot" class="{{ $select }}">
                                            @foreach ($this->slotOptions($model) as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('slot') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-(--color-text-muted)">Le client est prévenu et peut annuler si le créneau ne lui convient pas.</p>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button type="button" wire:click="stopActing" class="{{ $ghost }}">Annuler</button>
                                    <button type="button" wire:click="confirmReschedule" wire:loading.attr="disabled" class="{{ $primary }}">Reprogrammer</button>
                                </div>
                            </div>
                        @else
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <button type="button" wire:click="startMissed('{{ $stop['type'] }}', {{ $model->id }})" class="{{ $ghost }} px-2">Client absent</button>
                                @if ($isPickup)
                                    <button type="button" wire:click="startCollect({{ $model->id }})" class="{{ $primary }} px-2">Linge ramassé</button>
                                @else
                                    <button type="button" wire:click="startDeliver({{ $model->id }})" class="{{ $primary }} px-2">Livrée</button>
                                @endif
                            </div>
                            @unless ($isPickup)
                                <button type="button" wire:click="startReschedule({{ $model->id }})" class="mt-2 text-sm font-medium text-(--color-primary) hover:underline">Reprogrammer la livraison</button>
                            @endunless
                        @endif
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    @if ($donePickups->isNotEmpty() || $doneDeliveries->isNotEmpty())
        <h2 class="mt-8 mb-3 text-sm font-semibold text-(--color-text-secondary)">Fait ce jour-là</h2>
        <ul class="bg-(--color-surface) border border-(--color-border) rounded-xl divide-y divide-(--color-border)" data-testid="round-done">
            @foreach ($donePickups as $request)
                <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                    <span class="min-w-0"><span class="font-medium">{{ $request->customer->fullName() }}</span> <span class="text-(--color-text-muted)">· ramassé à {{ $request->picked_up_at->format('H:i') }}</span></span>
                    @if ($request->order)
                        <a href="{{ route('orders.show', $request->order) }}" wire:navigate class="flex-none font-medium text-(--color-primary) hover:underline">{{ $request->order->order_number }}</a>
                    @endif
                </li>
            @endforeach
            @foreach ($doneDeliveries as $delivery)
                <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                    <span class="min-w-0"><span class="font-medium">{{ $delivery->customer->fullName() }}</span> <span class="text-(--color-text-muted)">· livré à {{ $delivery->delivered_at->format('H:i') }}</span></span>
                    <a href="{{ route('orders.show', $delivery->order) }}" wire:navigate class="flex-none font-medium text-(--color-primary) hover:underline">{{ $delivery->order->order_number }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
