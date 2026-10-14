{{-- En-tête de colonne triable : un clic trie, un second inverse le sens. --}}
@props(['column', 'label', 'sort' => '', 'direction' => 'desc', 'align' => 'left'])
@php $active = $sort === $column; @endphp
<th {{ $attributes->merge(['class' => 'px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide '.($align === 'right' ? 'text-right' : 'text-left')]) }}
    aria-sort="{{ $active ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <button type="button" wire:click="sortBy('{{ $column }}')"
            title="Trier par {{ mb_strtolower($label) }}"
            class="group/sort inline-flex items-center gap-1 uppercase tracking-wide {{ $align === 'right' ? 'flex-row-reverse' : '' }} {{ $active ? 'text-(--color-text-primary)' : 'text-(--color-text-muted) hover:text-(--color-text-secondary)' }}">
        {{ $label }}
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"
             class="transition-all duration-150 {{ $active ? 'opacity-100' : 'opacity-0 group-hover/sort:opacity-60' }} {{ $active && $direction === 'asc' ? 'rotate-180' : '' }}" aria-hidden="true">
            <path d="m6 9 6 6 6-6"></path>
        </svg>
    </button>
</th>
