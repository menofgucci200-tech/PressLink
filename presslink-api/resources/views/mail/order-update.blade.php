<x-mail::message :unsubscribe="$unsubscribe">
# {{ $title }}

Bonjour {{ $customer->first_name }},

{!! $intro !!}

<x-mail::table>
| Article | Qté | Montant |
|:--------|:---:|--------:|
@foreach ($order->items as $item)
| {{ $item->name }} | {{ $item->quantity }} | {{ number_format($item->subtotal_fcfa, 0, ',', ' ') }} F |
@endforeach
| **Total** | | **{{ number_format($order->total_fcfa, 0, ',', ' ') }} FCFA** |
</x-mail::table>

@if ($pressing->address || $pressing->phone)
<x-mail::panel>
**{{ $pressing->name }}**<br>
@if ($pressing->address){{ $pressing->address }}@if ($pressing->city), {{ $pressing->city }}@endif<br>@endif
@if ($pressing->phone)Tél. {{ $pressing->phone }}@endif
</x-mail::panel>
@endif

<x-mail::button :url="url('/app/')">
Suivre ma commande
</x-mail::button>

À bientôt,<br>
L'équipe PressLink
</x-mail::message>
