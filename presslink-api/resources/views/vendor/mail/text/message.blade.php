@props(['unsubscribe' => null])
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            PressLink
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            PressLink · Besoin d'aide ? {{ config('presslink.support.phone_display') }} (appel ou WhatsApp) · {{ config('presslink.support.email') }}
            @if ($unsubscribe)
            Ne plus recevoir ces emails : {{ $unsubscribe }}
            @endif
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
