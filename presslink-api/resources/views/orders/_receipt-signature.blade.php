{{-- Signature PressLink en pied de reçu : logo noir sur le ticket thermique, en couleur sur la feuille A5. --}}
@php
    $signature = 'images/logo/'.($size === 'ticket' ? 'presslink-recu-noir.png' : 'presslink-recu.png');
@endphp
        <div class="signature">
            Suivi par <img src="{{ $isPdf ? public_path($signature) : asset($signature) }}" alt="PressLink">
        </div>
