@php
    $request = $subscriptionRequest;
    $subscription = $request->pressing->subscription;
@endphp
<x-mail::message>
# Nouvelle demande d'abonnement

<x-mail::table>
| | |
|:--|:--|
| **Pressing** | {{ $request->pressing->name }} ({{ $request->pressing->code }}) |
| **Demande** | {{ $request->label() }} |
| **Montant** | {{ $request->amount_fcfa !== null ? number_format($request->amount_fcfa, 0, ',', ' ').' F' : 'à fixer' }} |
| **Formule actuelle** | {{ $subscription?->plan->label() ?? '—' }} |
| **Demandé par** | {{ $request->requester?->name ?? '—' }} {{ $request->requester?->phone ? '· '.$request->requester->phone : '' }} |
| **Téléphone du pressing** | {{ $request->pressing->phone ?: '—' }} |
</x-mail::table>

Créez le lien de paiement dans Wave Business, collez-le sur la fiche du pressing et envoyez-le ; activez la demande une fois le paiement reçu.

<x-mail::button :url="route('admin.pressings.show', $request->pressing)">
Ouvrir la fiche du pressing
</x-mail::button>
</x-mail::message>
