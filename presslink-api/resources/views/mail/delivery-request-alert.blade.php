<x-mail::message>
@if ($cancelled)
# Livraison annulée

**{{ $customer->fullName() }}** ({{ $customer->phone }}) a annulé la livraison de la commande **{{ $order->order_number }}** chez **{{ $pressing->name }}**. Elle passera la chercher au pressing.
@else
# Livraison demandée

**{{ $customer->fullName() }}** ({{ $customer->phone }}) demande à **{{ $pressing->name }}** de lui livrer la commande **{{ $order->order_number }}**. Elle est ajoutée à la tournée de ce jour-là.
@endif

<x-mail::panel>
**{{ ucfirst($delivery->scheduleLabel()) }}**

{{ $delivery->place() }} — {{ $delivery->landmark }}
@if ($delivery->note)

« {{ $delivery->note }} »
@endif
</x-mail::panel>

<x-mail::button :url="route('round.index')">
Voir la tournée
</x-mail::button>

L'équipe PressLink
</x-mail::message>
