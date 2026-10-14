{{-- Bouton « copier » (voir [data-copy] dans resources/js/app.js). --}}
@props(['value', 'label' => 'Copier'])
<button type="button" data-copy="{{ $value }}" data-tooltip="{{ $label }}" aria-label="{{ $label }} : {{ $value }}"
        {{ $attributes->merge(['class' => 'copy-btn inline-flex items-center justify-center w-6 h-6 rounded-md text-(--color-text-muted) hover:text-(--color-primary) hover:bg-(--color-primary-tint) align-middle']) }}>
    <svg class="copy-btn__copy" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
    <svg class="copy-btn__done" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
</button>
