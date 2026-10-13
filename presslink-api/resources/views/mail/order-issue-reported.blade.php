<x-mail::message>
# Un client a signalé un problème

**{{ $customer->first_name }} {{ $customer->last_name }}** ({{ $customer->phone }}) a signalé un problème sur la commande **{{ $order->order_number }}** chez **{{ $pressing->name }}**.

<x-mail::panel>
**{{ $issue->category->label() }}**
@if ($issue->description)

« {{ $issue->description }} »
@endif
</x-mail::panel>

<x-mail::button :url="route('orders.show', $order)">
Voir la commande
</x-mail::button>

Retrouvez tous les signalements dans le menu **Signalements** de votre tableau de bord.

L'équipe PressLink
</x-mail::message>
