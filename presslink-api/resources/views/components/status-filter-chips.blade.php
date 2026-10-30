{{--
    Filtres de statut aux couleurs des badges, avec le nombre de commandes
    de chaque statut. Re-cliquer sur le filtre actif revient à « Toutes ».
--}}
@props(['current' => '', 'counts' => [], 'statuses' => \App\Enums\OrderStatus::cases(), 'model' => 'status'])
@php
    $statusColors = [
        '' => 'var(--color-primary)',
        'recue' => 'var(--color-info)',
        'traitement' => 'var(--color-secondary)',
        'prete' => 'var(--color-success-text)',
        'recuperee' => 'var(--color-text-secondary)',
        'attente' => 'var(--color-warning-text)',
        'annulee' => 'var(--color-error)',
    ];
    $chips = [['value' => '', 'label' => 'Toutes', 'count' => collect($counts)->sum()]];
    foreach ($statuses as $s) {
        $chips[] = ['value' => $s->value, 'label' => $s->label(), 'count' => (int) ($counts[$s->value] ?? 0)];
    }
@endphp
{{-- Téléphone : une seule ligne qui défile au doigt, pour laisser la place à la liste --}}
<div {{ $attributes->merge(['class' => 'flex gap-2 sm:flex-wrap max-sm:overflow-x-auto max-sm:-mx-4 max-sm:px-4 max-sm:pb-1 max-sm:[scrollbar-width:none]']) }} role="group" aria-label="Filtrer par statut">
    @foreach ($chips as $chip)
        @php $isActive = $current === $chip['value']; @endphp
        <button type="button" wire:click="$set('{{ $model }}', '{{ $isActive ? '' : $chip['value'] }}')" aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                style="--chip: {{ $statusColors[$chip['value']] ?? 'var(--color-primary)' }}"
                class="flex-none inline-flex items-center gap-1.5 h-9 pl-3.5 pr-1.5 rounded-full text-[13px] font-semibold border transition-all duration-150
                       {{ $isActive
                           ? 'bg-(--chip) border-(--chip) text-white shadow-[0_6px_14px_-8px_var(--chip)]'
                           : 'border-[color-mix(in_srgb,var(--chip)_30%,transparent)] bg-[color-mix(in_srgb,var(--chip)_8%,transparent)] text-(--chip) hover:bg-[color-mix(in_srgb,var(--chip)_14%,transparent)]' }}">
            {{ $chip['label'] }}
            <span class="min-w-6 h-6 px-1.5 rounded-full inline-flex items-center justify-center text-xs font-bold tabular-nums
                         {{ $isActive ? 'bg-white/25 text-white' : 'bg-[color-mix(in_srgb,var(--chip)_16%,transparent)] '.($chip['count'] === 0 ? 'opacity-60' : '') }}">
                {{ number_format($chip['count'], 0, ',', ' ') }}
            </span>
        </button>
    @endforeach
</div>
