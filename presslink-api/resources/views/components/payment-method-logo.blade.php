{{-- Logo d'un moyen de paiement : celui de l'opérateur, ou un billet / une carte dessinés. --}}
@props(['method', 'size' => 24])
@php
    $method = $method instanceof \App\Enums\PaymentMethod ? $method : \App\Enums\PaymentMethod::from($method);
    $logo = $method->logo();
@endphp
@if ($logo)
    <img src="{{ asset($logo) }}" alt="" width="{{ $size }}" height="{{ $size }}" loading="lazy" aria-hidden="true"
         {{ $attributes->merge(['class' => 'flex-none rounded-md object-contain bg-white ring-1 ring-black/5']) }} style="width:{{ $size }}px;height:{{ $size }}px">
@elseif ($method === \App\Enums\PaymentMethod::Cash)
    <span aria-hidden="true" {{ $attributes->merge(['class' => 'flex-none inline-flex items-center justify-center rounded-md bg-[#16a34a] text-white']) }} style="width:{{ $size }}px;height:{{ $size }}px">
        <svg width="{{ round($size * 0.7) }}" height="{{ round($size * 0.7) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
    </span>
@else
    <span aria-hidden="true" {{ $attributes->merge(['class' => 'flex-none inline-flex items-center justify-center rounded-md bg-[#1e3a8a] text-white']) }} style="width:{{ $size }}px;height:{{ $size }}px">
        <svg width="{{ round($size * 0.7) }}" height="{{ round($size * 0.7) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg>
    </span>
@endif
