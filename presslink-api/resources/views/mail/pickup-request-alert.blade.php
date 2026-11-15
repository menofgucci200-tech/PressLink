<x-mail::message>
@if ($cancelled)
# Ramassage annulé

**{{ $customer->fullName() }}** ({{ $customer->phone }}) a annulé sa demande de ramassage chez **{{ $pressing->name }}**.
@elseif ($openToZone)
# Ramassage à prendre à {{ $pickupRequest->commune }}

**{{ $customer->first_name }}** cherche un pressing pour passer chercher son linge. La demande est ouverte à tous les pressings qui passent à {{ $pickupRequest->commune }} : **le premier qui l'accepte la prend.**
@else
# Nouvelle demande de ramassage

**{{ $customer->fullName() }}** ({{ $customer->phone }}) demande à **{{ $pressing->name }}** de passer chercher son linge.
@endif

<x-mail::panel>
**{{ ucfirst($pickupRequest->scheduleLabel()) }}**

@if ($openToZone)
{{ $pickupRequest->place() }}
@else
{{ $pickupRequest->place() }} — {{ $pickupRequest->landmark }}
@endif
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
@elseif ($openToZone)
Voir la demande
@else
Accepter ou refuser
@endif
</x-mail::button>

L'équipe PressLink
</x-mail::message>
