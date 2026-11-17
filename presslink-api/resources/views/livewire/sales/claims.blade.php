@php
    $input = 'w-full h-10 px-3 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)';
    $label = 'block text-xs font-medium text-(--color-text-secondary) mb-1.5';
    $tones = [
        \App\Models\SalesAgentClaim::PENDING => 'bg-(--color-info-tint) text-(--color-info)',
        \App\Models\SalesAgentClaim::APPROVED => 'bg-(--color-success-tint) text-(--color-success-text)',
        \App\Models\SalesAgentClaim::DECLINED => 'bg-(--color-error-tint) text-(--color-error)',
    ];
@endphp
<div class="max-w-3xl">
    <h1 class="font-display text-2xl font-bold mb-1">Signaler un pressing</h1>
    <p class="text-sm text-(--color-text-muted) mb-6">Un pressing que vous avez présenté s'est inscrit seul, sans votre code ? Signalez-le : l'équipe PressLink vérifie et vous le rattache.</p>

    <form novalidate wire:submit="submit" class="bg-(--color-surface) border border-(--color-border) rounded-xl p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 items-start mb-6">
        <div>
            <label class="{{ $label }}" for="pressingName">Nom du pressing</label>
            <input id="pressingName" type="text" wire:model="pressingName" class="{{ $input }}">
            @error('pressingName') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="{{ $label }}" for="phone">Numéro du pressing ou du gérant</label>
            <input id="phone" type="tel" inputmode="tel" wire:model="phone" placeholder="07 00 00 00 00" class="{{ $input }} tabular-nums">
            @error('phone') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <label class="{{ $label }}" for="note">Précision <span class="font-normal text-(--color-text-muted)">(facultatif)</span></label>
            <textarea id="note" rows="2" wire:model="note" placeholder="Présenté le 12 novembre, gérante Mme Koné…" class="w-full px-3 py-2 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm focus:outline-none focus:border-(--color-primary)"></textarea>
            @error('note') <p class="text-xs text-(--color-error) mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2 flex justify-end">
            <button type="submit" class="h-10 px-5 rounded-lg bg-(--color-primary) text-white text-sm font-semibold">Envoyer le signalement</button>
        </div>
    </form>

    <div class="bg-(--color-surface) border border-(--color-border) rounded-xl overflow-hidden">
        <h2 class="font-display text-base font-bold px-5 py-4">Mes signalements</h2>
        @forelse ($claims as $claim)
            <div class="flex items-center gap-3 px-5 py-3 border-t border-(--color-border) text-sm">
                <div class="flex-1 min-w-0">
                    <div class="font-medium truncate">{{ $claim->pressing_name }}</div>
                    <div class="text-xs text-(--color-text-muted)"><span class="tabular-nums">{{ \App\Support\PhoneNumber::national($claim->phone) }}</span> · le {{ $claim->created_at->format('d/m/Y') }}</div>
                </div>
                <span class="flex-none inline-flex items-center h-7 px-2.5 rounded-full text-xs font-semibold {{ $tones[$claim->status] ?? '' }}">{{ $claim->statusLabel() }}</span>
            </div>
        @empty
            <div class="px-5 py-8 border-t border-(--color-border) text-center text-sm text-(--color-text-muted)">Aucun signalement.</div>
        @endforelse
    </div>
</div>
