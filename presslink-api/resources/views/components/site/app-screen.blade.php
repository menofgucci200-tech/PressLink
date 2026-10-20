@props(['ecran' => 'accueil', 'notification' => true])
@php
    $ecrans = [
        'accueil' => ['accueil.webp', 'Accueil de l\'application client PressLink : les commandes en cours et leur état'],
        'commande-prete' => ['commande-prete.webp', 'Détail d\'une commande prête dans l\'application PressLink'],
        'commande-contact' => ['commande-contact.webp', 'Paiement de la commande et bouton « Contacter le pressing » dans l\'application PressLink'],
        'connexion' => ['connexion.webp', 'Écran de connexion de l\'application PressLink'],
    ];
    [$fichier, $alt] = $ecrans[$ecran] ?? $ecrans['accueil'];
    $enTeteBleu = $ecran !== 'connexion';
@endphp
{{-- Vraie capture de l'application client, sous une barre d'état de téléphone
     (pour que l'encoche de la photo ne cache pas l'en-tête), et notification
     « commande prête » qui arrive comme sur le téléphone. Tailles en cqw. --}}
<div class="app-shot">
    <div @class(['app-shot__etat', 'is-bleu' => $enTeteBleu])>
        <span>9:41</span>
        <svg viewBox="0 0 64 20" aria-hidden="true"><path d="M2 14h3v4H2zM8 11h3v7H8zM14 8h3v10h-3zM20 5h3v13h-3z" fill="currentColor"/><path d="M33 9a10 10 0 0 1 13 0M35.5 12a6 6 0 0 1 8 0" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round"/><circle cx="39.5" cy="15.5" r="1.8" fill="currentColor"/><rect x="50" y="5" width="12" height="11" rx="2.5" stroke="currentColor" stroke-width="1.6" fill="none"/><rect x="52" y="7" width="7" height="7" rx="1" fill="currentColor"/></svg>
    </div>
    <img src="{{ asset('images/ecrans/'.$fichier) }}" width="780" height="1576" alt="{{ $alt }}" loading="lazy" decoding="async">
    @if ($notification)
        <div class="app-push" data-push>
            <img src="{{ asset('apple-touch-icon.png') }}" alt="" width="40" height="40">
            <span>
                <small><b>PRESSLINK</b><i>maintenant</i></small>
                <b>Votre commande est prête ! 🎉</b>
                Pressing Élégance · PL-949922
            </span>
        </div>
    @endif
</div>
