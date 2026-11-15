@php
    $tabs = [
        'pending' => 'À traiter'.($pendingCount ? " ({$pendingCount})" : ''),
        'accepted' => 'Prévus'.($acceptedCount ? " ({$acceptedCount})" : ''),
        'closed' => 'Terminées',
    ];
    $statusTone = [
        'pending' => 'text-(--color-warning-text) bg-(--color-warning-tint)',
        'accepted' => 'text-(--color-success-text) bg-(--color-success-tint)',
        'refused' => 'text-(--color-error) bg-(--color-error-tint)',
        'cancelled' => 'text-(--color-text-muted) bg-(--color-bg)',
        'expired' => 'text-(--color-text-muted) bg-(--color-bg)',
        'picked_up' => 'text-(--color-primary) bg-(--color-primary-tint)',
        'missed' => 'text-(--color-text-muted) bg-(--color-bg)',
    ];
    $select = 'w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
@endphp
<div class="max-w-3xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Ramassages</h1>
        <p class="text-sm text-(--color-text-muted)">Les clients de l'appli PressLink qui habitent vos communes vous demandent de passer chercher leur linge, ou cherchent le premier pressing disponible. Acceptez sur leur créneau ou proposez-en un autre : ils sont prévenus tout de suite.</p>
    </div>

    @if (! $launched || ! $pickupEnabled)
        <div class="mb-5 rounded-xl border border-(--color-info) bg-(--color-info-tint) px-4 py-3 text-sm" data-testid="pickups-not-receiving">
            @if (! $launched)
                <span class="font-semibold">Pas encore visible par vos clients.</span> Le service s'ouvrira bientôt dans l'appli PressLink.
            @else
                <span class="font-semibold">Vous ne recevez pas de demandes.</span> Le ramassage est désactivé dans vos réglages.
            @endif
            <a href="{{ route('pressing.delivery') }}" wire:navigate class="font-medium text-(--color-primary) hover:underline">Ramassage et livraison →</a>
        </div>
    @endif

    <div class="flex flex-wrap gap-2 mb-5">
        @foreach ($tabs as $value => $label)
            <button type="button" wire:click="$set('tab', '{{ $value }}')"
                class="h-9 px-3.5 rounded-lg text-sm font-medium border {{ $tab === $value ? 'bg-(--color-primary) text-white border-(--color-primary)' : 'bg-(--color-surface) text-(--color-text-secondary) border-(--color-border) hover:bg-(--color-bg)' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($requests->isEmpty())
        <div class="bg-(--color-surface) border border-(--color-border) rounded-xl">
            @if ($tab === 'pending')
                <x-empty-state icon="check" tone="success" title="Aucune demande à traiter">
                    Quand un client de vos communes vous demande de passer chercher son linge, ou cherche le premier pressing disponible, la demande arrive ici et dans le menu. Vous recevez aussi un e-mail.
                </x-empty-state>
            @elseif ($tab === 'accepted')
                <x-empty-state icon="box" title="Aucun ramassage prévu">
                    Les demandes que vous acceptez s'affichent ici et dans la tournée du jour, dans l'ordre de passage.
                </x-empty-state>
            @else
                <x-empty-state icon="box" title="Aucune demande terminée">
                    Les ramassages faits, et les demandes refusées, annulées par le client ou restées sans réponse sont conservés ici.
                </x-empty-state>
            @endif
        </div>
    @else
        @if ($tab === 'accepted')
            <p class="mb-3 text-sm text-(--color-text-secondary)">Le jour venu, le coursier marque le linge ramassé depuis la <a href="{{ route('round.index') }}" wire:navigate class="font-medium text-(--color-primary) hover:underline">tournée</a> : le dépôt est créé avec les pièces comptées.</p>
        @endif
        <ul class="flex flex-col gap-3">
            @foreach ($requests as $request)
                @php
                    $customer = $request->customer;
                    // Ouverte à la zone : nom complet, téléphone et repère seulement après acceptation.
                    $openToZone = $request->isOpenToZone();
                    $isActing = $actingId === $request->id;
                    $overdue = $request->status->isOpen() && $request->slotEndsAt()->isPast();
                @endphp
                <li wire:key="pickup-{{ $request->id }}" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-4 sm:p-5" data-testid="pickup-request">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            @if ($openToZone)
                                <div class="font-semibold">{{ $customer->first_name }}</div>
                                <div class="text-xs text-(--color-text-muted)">Nom et téléphone visibles une fois la demande acceptée</div>
                            @else
                                <div class="font-semibold">{{ $customer->fullName() }}</div>
                                <a href="tel:{{ $customer->phone }}" class="text-sm text-(--color-primary) hover:underline tabular-nums">{{ \App\Support\PhoneNumber::format($customer->phone) }}</a>
                            @endif
                        </div>
                        <span class="flex-none inline-flex items-center h-6 px-2 rounded-full text-xs font-semibold {{ $statusTone[$request->status->value] }}">{{ $request->status->staffLabel() }}</span>
                    </div>

                    @if ($openToZone)
                        <p class="mt-3 rounded-lg bg-(--color-primary-tint) px-3 py-2 text-sm text-(--color-primary)" data-testid="zone-request">
                            <span class="font-semibold">Ouverte aux pressings de {{ $request->commune }}</span> : le premier qui accepte la prend.
                        </p>
                    @endif

                    <div class="mt-3 grid gap-2 sm:grid-cols-2 text-sm">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Quand</div>
                            <div class="font-medium {{ $overdue ? 'text-(--color-error)' : '' }}">{{ ucfirst($request->scheduleLabel()) }}</div>
                            @if ($request->rescheduled)
                                <div class="text-xs text-(--color-text-muted)">Créneau changé par le pressing</div>
                            @endif
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-(--color-text-muted)">Où · {{ $request->address_label }}</div>
                            <div class="font-medium">{{ $request->place() }}</div>
                            @unless ($openToZone)
                                <div class="text-(--color-text-secondary)">{{ $request->landmark }}</div>
                            @endunless
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-(--color-text-muted)">
                        @if ($request->items_estimate)
                            <span>Environ {{ $request->items_estimate }} pièce{{ $request->items_estimate > 1 ? 's' : '' }}</span>
                        @endif
                        @php $fee = $openToZone ? $pressing->pickup_fee_fcfa : $request->pickup_fee_fcfa; @endphp
                        <span>Frais de ramassage{{ $openToZone ? ' (les vôtres)' : '' }} : {{ $fee > 0 ? number_format($fee, 0, ',', ' ').' F' : 'gratuit' }}</span>
                        <span>Reçue {{ $request->created_at->locale('fr')->diffForHumans() }}</span>
                    </div>

                    @if ($request->note)
                        <p class="mt-3 text-sm text-(--color-text-secondary) break-words">« {{ $request->note }} »</p>
                    @endif

                    @if ($request->photos->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($request->photos as $photo)
                                <a href="{{ $photo->url() }}" target="_blank" rel="noopener" class="block w-16 h-16 rounded-lg overflow-hidden border border-(--color-border)">
                                    <img src="{{ $photo->url(thumb: true) }}" alt="Photo du linge {{ $loop->iteration }}" loading="lazy" class="w-full h-full object-cover">
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($request->status->value === 'refused' && $request->refusal_reason)
                        <p class="mt-3 text-xs text-(--color-text-secondary)">Motif : {{ $request->refusal_reason }}</p>
                    @endif
                    @if ($request->order)
                        <p class="mt-3 text-sm">Dépôt créé au ramassage : <a href="{{ route('orders.show', $request->order) }}" wire:navigate class="font-medium text-(--color-primary) hover:underline">{{ $request->order->order_number }}</a></p>
                    @endif
                    @if (! $request->status->isOpen() && $request->closed_at)
                        <p class="mt-1 text-xs text-(--color-text-muted)">{{ $request->status->label() }} le {{ $request->closed_at->locale('fr')->isoFormat('D MMMM [à] HH:mm') }}{{ $request->respondedBy && $request->status->value === 'refused' ? ' par '.$request->respondedBy->name : '' }}</p>
                    @endif

                    @if ($canHandle && $request->status->isOpen())
                        @if ($isActing && $mode === 'accept')
                            <div class="mt-4 rounded-lg bg-(--color-bg) p-3 sm:p-4 animate-step">
                                <div class="text-sm font-medium mb-3">{{ $request->status->value === 'pending' ? 'Accepter ce ramassage' : 'Reprogrammer ce ramassage' }}</div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="pickup-date" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Jour</label>
                                        <select id="pickup-date" wire:model="date" class="{{ $select }}">
                                            @foreach ($this->dateOptions() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('date') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="pickup-slot" class="block text-xs font-medium text-(--color-text-secondary) mb-1.5">Créneau</label>
                                        <select id="pickup-slot" wire:model="slot" class="{{ $select }}">
                                            @foreach ($this->slotOptions($request) as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('slot') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-(--color-text-muted)">Si vous changez le jour ou le créneau, le client est prévenu et peut annuler si cela ne lui convient pas.</p>
                                <div class="mt-3 grid grid-cols-2 sm:flex gap-2">
                                    <button type="button" wire:click="stopActing" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium text-(--color-text-secondary) hover:bg-(--color-surface)">Annuler</button>
                                    <button type="button" wire:click="confirmAccept" wire:loading.attr="disabled" class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600) disabled:opacity-60" data-testid="confirm-accept">Confirmer</button>
                                </div>
                            </div>
                        @elseif ($isActing && $mode === 'refuse')
                            <div class="mt-4 rounded-lg bg-(--color-bg) p-3 sm:p-4 animate-step">
                                <div class="text-sm font-medium mb-3">Motif du refus (envoyé au client)</div>
                                <div class="flex flex-col gap-2">
                                    @foreach ($refusalReasons as $choice)
                                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                                            <input type="radio" wire:model.live="reason" value="{{ $choice }}" class="w-4 h-4 flex-none">
                                            {{ $choice }}
                                        </label>
                                    @endforeach
                                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                                        <input type="radio" wire:model.live="reason" value="other" class="w-4 h-4 flex-none">
                                        Autre motif
                                    </label>
                                    @if ($reason === 'other')
                                        <input type="text" wire:model="otherReason" maxlength="200" placeholder="ex. Livreur malade aujourd'hui" class="{{ $select }}" aria-label="Autre motif">
                                    @endif
                                    @error('reason') <p class="text-xs text-(--color-error)">{{ $message }}</p> @enderror
                                    @error('otherReason') <p class="text-xs text-(--color-error)">{{ $message }}</p> @enderror
                                </div>
                                <div class="mt-3 grid grid-cols-2 sm:flex gap-2">
                                    <button type="button" wire:click="stopActing" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium text-(--color-text-secondary) hover:bg-(--color-surface)">Annuler</button>
                                    <button type="button" wire:click="confirmRefuse" wire:loading.attr="disabled" class="h-10 px-4 rounded-lg bg-(--color-error) text-white text-sm font-semibold disabled:opacity-60" data-testid="confirm-refuse">Refuser la demande</button>
                                </div>
                            </div>
                        @elseif ($request->status->value === 'pending')
                            <div class="mt-4 grid grid-cols-2 sm:flex gap-2">
                                @if ($openToZone)
                                    <button type="button" wire:click="dismiss({{ $request->id }})" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium text-(--color-text-secondary) hover:bg-(--color-bg)" data-testid="dismiss">Pas pour nous</button>
                                @else
                                    <button type="button" wire:click="startRefuse({{ $request->id }})" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium text-(--color-text-secondary) hover:bg-(--color-bg)">Refuser</button>
                                @endif
                                <button type="button" wire:click="startAccept({{ $request->id }})" class="h-10 px-4 rounded-lg bg-(--color-primary) text-white text-sm font-semibold hover:bg-(--color-primary-600)">Accepter</button>
                            </div>
                        @else
                            <div class="mt-4">
                                <button type="button" wire:click="startAccept({{ $request->id }})" class="h-10 px-4 rounded-lg border border-(--color-border) text-sm font-medium text-(--color-text-secondary) hover:bg-(--color-bg)">Reprogrammer</button>
                            </div>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-4">
            {{ $requests->links() }}
        </div>
    @endif
</div>
