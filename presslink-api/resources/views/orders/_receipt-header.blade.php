{{-- En-tête commun des reçus : logo, pressing, titre et numéro. --}}
@php
    $logo = null;
    if ($pressing->logo_path) {
        // dompdf lit les images sur le disque ; le navigateur, par l'URL.
        $logo = $isPdf
            ? (\Illuminate\Support\Facades\Storage::disk('public')->exists($pressing->logo_path) ? \Illuminate\Support\Facades\Storage::disk('public')->path($pressing->logo_path) : null)
            : $pressing->logo_url;
    }
@endphp
        <div class="center">
            @if ($logo)
                <img src="{{ $logo }}" alt="" class="logo">
            @endif
            <p class="pressing-name">{{ $pressing->name }}</p>
            @if ($pressing->address || $pressing->city)
                <div class="muted small">{{ collect([$pressing->address, $pressing->city])->filter()->implode(', ') }}</div>
            @endif
            @if ($pressing->phone)
                <div class="muted small">Tél. {{ \App\Support\PhoneNumber::format($pressing->phone) }}</div>
            @endif
            <p class="title">{{ $title }}</p>
            <p class="number">{{ $number }}</p>
        </div>
