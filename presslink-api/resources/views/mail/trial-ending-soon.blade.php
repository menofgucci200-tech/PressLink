<x-mail::message>
# Bonjour {{ $owner->name }},

L'essai gratuit de **{{ $subscription->pressing->name }}** se termine le **{{ $subscription->trial_ends_at->format('d/m/Y') }}**.

Pour continuer à enregistrer vos commandes et à prévenir vos clients, choisissez votre formule{{ \App\Support\GeniusPay::isConfigured() ? ' et payez en ligne (Wave, Orange Money, MTN, Moov ou carte)' : '' }}. Si vous payez avant la fin de l'essai, votre période payée commence seulement à sa fin : vous ne perdez aucun jour gratuit.

<x-mail::button :url="route('subscription.plans')">
Choisir ma formule
</x-mail::button>

Une question ? Répondez à cet e-mail ou appelez-nous au {{ config('presslink.support.phone_display') }}.

L'équipe PressLink
</x-mail::message>
