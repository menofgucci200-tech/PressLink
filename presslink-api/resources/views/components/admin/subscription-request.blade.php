@props(['request', 'showPressing' => false])
{{-- Demande d'abonnement en attente : lien de paiement Wave, activation une fois payé, refus avec motif. --}}
@php
    $waPhone = ltrim((string) ($request->requester?->phone ?: $request->pressing?->phone), '+');
    $waUrl = $waPhone !== '' ? 'https://wa.me/'.$waPhone.'?text='.rawurlencode($request->paymentMessage()) : null;
@endphp
<li class="rounded-lg bg-(--color-surface) border border-(--color-border) p-3 text-sm" wire:key="request-{{ $request->id }}" x-data="{ refusing: false }">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5">
        <span>
            @if ($showPressing && $request->pressing)
                <a href="{{ route('admin.pressings.show', $request->pressing) }}" wire:navigate class="font-semibold text-(--color-primary) hover:underline">{{ $request->pressing->name }}</a> ·
            @endif
            <span class="font-medium">{{ $request->label() }}</span>
            <span class="text-(--color-text-muted)">· {{ $request->requester?->name ?? '—' }}, le {{ $request->created_at->format('d/m/Y à H:i') }}</span>
        </span>
        <span class="flex gap-2">
            <button type="button" wire:click="applyRequest({{ $request->id }})" wire:confirm="Le paiement est reçu ? « {{ $request->label() }} » sera activé tout de suite."
                    class="h-8 px-3 rounded-lg bg-(--color-primary) text-white text-xs font-semibold hover:bg-(--color-primary-600)">Payé, activer</button>
            <button type="button" @click="refusing = ! refusing" :aria-expanded="refusing"
                    class="h-8 px-3 rounded-lg border border-(--color-border) text-xs font-semibold hover:border-(--color-error) hover:text-(--color-error)">Refuser</button>
        </span>
    </div>
    <form x-show="refusing" x-cloak wire:submit="declineRequest({{ $request->id }})" class="mb-2.5 grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_auto] gap-2 items-start">
        <div>
            <input type="text" wire:model="declineReasons.{{ $request->id }}" maxlength="500" aria-label="Motif du refus"
                   placeholder="Motif montré au pressing (facultatif) : ex. paiement non reçu"
                   class="w-full h-9 px-3 rounded-lg border border-(--color-border) text-sm focus:outline-none focus:border-(--color-error)">
            @error("declineReasons.{$request->id}") <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="h-9 px-3 rounded-lg bg-(--color-error) text-white text-xs font-semibold hover:brightness-95">Confirmer le refus</button>
    </form>
    <form wire:submit="savePaymentLink({{ $request->id }})" class="grid grid-cols-1 sm:grid-cols-[9rem_minmax(0,1fr)_auto] gap-2 items-start">
        <div>
            <input type="number" min="0" wire:model="requestAmounts.{{ $request->id }}" placeholder="Montant" aria-label="Montant à payer (F)"
                   class="w-full h-9 px-3 rounded-lg border border-(--color-border) text-sm tabular-nums">
            @error("requestAmounts.{$request->id}") <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <input type="url" wire:model="requestLinks.{{ $request->id }}" placeholder="Lien de paiement Wave Business (https://…)" aria-label="Lien de paiement"
                   class="w-full h-9 px-3 rounded-lg border border-(--color-border) text-sm">
            @error("requestLinks.{$request->id}") <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex gap-2">
            <button type="submit" class="h-9 px-3 rounded-lg border border-(--color-primary) text-(--color-primary) text-xs font-semibold">Enregistrer</button>
            @if ($waUrl && $request->payment_link)
                <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="inline-flex items-center h-9 px-3 rounded-lg bg-(--color-whatsapp) text-white text-xs font-semibold">Envoyer sur WhatsApp</a>
            @endif
        </div>
    </form>
</li>
