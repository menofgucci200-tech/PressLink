@props(['unsubscribe' => null])
<x-mail::layout>
{{-- En-tête PressLink --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
PressLink
</x-mail::header>
</x-slot:header>

{{-- Corps --}}
{!! $slot !!}

{{-- Sous-texte --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Pied de page --}}
<x-slot:footer>
<x-mail::footer>
**PressLink** · Le suivi de vos commandes de pressing<br>
Besoin d'aide ? {{ config('presslink.support.phone_display') }} (appel ou WhatsApp) · [{{ config('presslink.support.email') }}](mailto:{{ config('presslink.support.email') }})<br>
[presslink.org]({{ config('app.url') }})
@if ($unsubscribe)
<br><br>[Ne plus recevoir ces emails]({{ $unsubscribe }})
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
