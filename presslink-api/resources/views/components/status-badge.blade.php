@props(['status'])
@php
    $colors = match ($status) {
        \App\Enums\OrderStatus::Recue => ['bg' => 'var(--color-info-tint)', 'fg' => 'var(--color-info)'],
        \App\Enums\OrderStatus::Traitement => ['bg' => 'color-mix(in srgb, var(--color-secondary) 16%, transparent)', 'fg' => 'var(--color-secondary)'],
        \App\Enums\OrderStatus::Prete => ['bg' => 'var(--color-success-tint)', 'fg' => 'var(--color-success-text)'],
        \App\Enums\OrderStatus::Recuperee => ['bg' => 'var(--color-border)', 'fg' => 'var(--color-text-secondary)'],
        \App\Enums\OrderStatus::Attente => ['bg' => 'var(--color-warning-tint)', 'fg' => 'var(--color-warning-text)'],
        \App\Enums\OrderStatus::Annulee => ['bg' => 'var(--color-error-tint)', 'fg' => 'var(--color-error)'],
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide']) }}
      style="background:{{ $colors['bg'] }};color:{{ $colors['fg'] }}">
    <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $colors['fg'] }}"></span>
    {{ $status->label() }}
</span>
