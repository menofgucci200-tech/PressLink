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
| **Formule actuelle** | {{ $subscription?->plan->label() ?? '—' }} |
| **Demandé par** | {{ $request->requester?->name ?? '—' }} {{ $request->requester?->phone ? '· '.$request->requester->phone : '' }} |
| **Téléphone du pressing** | {{ $request->pressing->phone ?: '—' }} |
</x-mail::table>

Une fois le paiement reçu, activez-la depuis la fiche du pressing.

<x-mail::button :url="route('admin.pressings.show', $request->pressing)">
Ouvrir la fiche du pressing
</x-mail::button>
</x-mail::message>
