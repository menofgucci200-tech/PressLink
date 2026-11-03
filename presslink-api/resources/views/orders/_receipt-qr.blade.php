{{-- QR code vers l'application client (presslink.org/app), où le client suit sa commande avec son numéro. --}}
<img src="{{ $isPdf ? public_path('images/qr-presslink-app.png') : asset('images/qr-presslink-app.png') }}" alt="QR code de l'application PressLink" class="qr">
