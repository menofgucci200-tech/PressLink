{{-- En-tête commun des reçus : logo et coordonnées du pressing, titre et date du reçu. --}}
@php
    $logo = null;
    if ($pressing->logo_path) {
        // dompdf lit les images sur le disque ; le navigateur, par l'URL.
        $logo = $isPdf
            ? (\Illuminate\Support\Facades\Storage::disk('public')->exists($pressing->logo_path) ? \Illuminate\Support\Facades\Storage::disk('public')->path($pressing->logo_path) : null)
            : $pressing->logo_url;
    }
@endphp
        <table class="head">
            <tr>
                @if ($logo)
                    <td class="head-logo"><img src="{{ $logo }}" alt="" class="logo"></td>
                @endif
                <td>
                    <p class="pressing-name">{{ $pressing->name }}</p>
                    @if ($pressing->address || $pressing->city)
                        <div class="muted small">{{ collect([$pressing->address, $pressing->city])->filter()->implode(', ') }}</div>
                    @endif
                    @if ($pressing->phone)
                        <div class="muted small">Tél. {{ \App\Support\PhoneNumber::format($pressing->phone) }}</div>
                    @endif
                </td>
                <td class="r muted small">{{ $title }}<br>{{ $date }}</td>
            </tr>
        </table>
