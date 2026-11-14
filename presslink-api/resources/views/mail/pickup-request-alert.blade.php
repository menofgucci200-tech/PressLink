<x-mail::message>
@if ($cancelled)
# Ramassage annulé

**{{ $customer->fullName() }}** ({{ $customer->phone }}) a annulé sa demande de ramassage chez **{{ $pressing->name }}**.
@else
# Nouvelle demande de ramassage

**{{ $customer->fullName() }}** ({{ $customer->phone }}) demande à **{{ $pressing->name }}** de passer chercher son linge.
@endif

<x-mail::panel>
**{{ ucfirst($pickupRequest->scheduleLabel()) }}**

{{ $pickupRequest->place() }} — {{ $pickupRequest->landmark }}
@if ($pickupRequest->items_estimate)

Environ {{ $pickupRequest->items_estimate }} pièce{{ $pickupRequest->items_estimate > 1 ? 's' : '' }}
@endif
@if ($pickupRequest->note)

« {{ $pickupRequest->note }} »
@endif
</x-mail::panel>

<x-mail::button :url="route('pickups.index')">
@if ($cancelled)
Voir les ramassages
@else
Accepter ou refuser
@endif
</x-mail::button>

L'équipe PressLink
</x-mail::message>
