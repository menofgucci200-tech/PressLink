<x-mail::message>
# Nouveau pressing inscrit sur le site

<x-mail::table>
| | |
|:--|:--|
| **Pressing** | {{ $pressing->name }} ({{ $pressing->code }}) |
| **Commune** | {{ $pressing->city ?: '—' }} |
| **Téléphone du pressing** | {{ \App\Support\PhoneNumber::format($pressing->phone) ?: '—' }} |
| **Gérant** | {{ $owner->name }} · {{ \App\Support\PhoneNumber::format($owner->phone) }} |
| **E-mail** | {{ $owner->email ?: '—' }} |
| **Essai gratuit jusqu'au** | {{ $pressing->subscription?->trial_ends_at?->format('d/m/Y') ?? '—' }} |
</x-mail::table>

Appelez le gérant pour l'aider à démarrer : ses prix, ses horaires, son équipe et sa première commande.

<x-mail::button :url="route('admin.pressings.show', $pressing)">
Ouvrir la fiche du pressing
</x-mail::button>
</x-mail::message>
