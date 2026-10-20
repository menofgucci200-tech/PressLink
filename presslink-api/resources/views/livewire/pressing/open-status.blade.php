@php
    $isSuspended = $pressing && $pressing->status === \App\Enums\PressingStatus::Suspended;
    $isOpen = $pressing && ! $isSuspended && $pressing->isOpenNow();
    $isExceptional = $isOpen && $pressing->isExceptionallyOpen();
    $statusLabel = $isSuspended ? 'Suspendu' : ($isExceptional ? 'Ouvert (exceptionnel)' : ($isOpen ? 'Ouvert' : 'Fermé'));
    $statusBg = $isSuspended ? 'bg-(--color-error-tint)' : ($isExceptional ? 'bg-(--color-warning-tint)' : ($isOpen ? 'bg-(--color-success-tint)' : 'bg-(--color-bg)'));
    $statusDot = $isSuspended ? 'bg-(--color-error)' : ($isExceptional ? 'bg-(--color-warning)' : ($isOpen ? 'bg-(--color-success) animate-pulse-dot' : 'bg-(--color-text-muted)'));
    $statusText = $isSuspended ? 'text-(--color-error)' : ($isExceptional ? 'text-(--color-warning-text)' : ($isOpen ? 'text-(--color-success-text)' : 'text-(--color-text-muted)'));
    $canToggle = $isAdmin && ! $isSuspended && ($isExceptional || ! $isOpen);
@endphp
<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @if ($canToggle) @click="open = !open" :aria-expanded="open" @else disabled @endif
            class="flex items-center gap-1.5 rounded-full pl-2.5 pr-3 py-1.5 {{ $statusBg }} {{ $canToggle ? 'cursor-pointer hover:ring-2 hover:ring-(--color-border)' : 'cursor-default' }}"
            title="{{ $isExceptional ? 'Ouverture exceptionnelle jusqu\'à '.$pressing->exceptional_open_until->format('H:i') : 'D\'après les horaires définis dans Paramètres' }}">
        <span class="w-1.5 h-1.5 rounded-full {{ $statusDot }}"></span>
        <span class="text-xs font-semibold {{ $statusText }}">{{ $statusLabel }}</span>
        @if ($canToggle)
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" class="{{ $statusText }}"><path d="m6 9 6 6 6-6"/></svg>
        @endif
    </button>

    @if ($canToggle)
        <div x-show="open" x-cloak x-transition.opacity class="absolute right-0 top-full mt-2 z-40 w-64 rounded-xl border border-(--color-border) bg-(--color-surface) shadow-lg p-2">
            @if ($isExceptional)
                <p class="px-2 py-1.5 text-xs text-(--color-text-secondary)">Ouvert exceptionnellement jusqu'à {{ $pressing->exceptional_open_until->format('H:i') }}.</p>
                <button type="button" wire:click="closeNow" class="w-full text-left px-2 py-2 rounded-lg text-sm font-medium hover:bg-(--color-bg)">Refermer maintenant</button>
            @else
                <p class="px-2 py-1.5 text-xs text-(--color-text-secondary)">Accueillir un client en dehors des horaires. L'ouverture est inscrite au journal.</p>
                <button type="button" wire:click="openExceptionally('hour')" class="w-full text-left px-2 py-2 rounded-lg text-sm font-medium hover:bg-(--color-bg)">Ouvrir pour 1 heure</button>
                <button type="button" wire:click="openExceptionally('day')" class="w-full text-left px-2 py-2 rounded-lg text-sm font-medium hover:bg-(--color-bg)">Ouvrir jusqu'à la fin de la journée</button>
            @endif
        </div>
    @endif
</div>
