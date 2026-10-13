<x-mail::message>
# Nouveau message de contact

Reçu depuis la page Contact de presslink.org.

<x-mail::table>
| | |
|:--|:--|
| **Nom** | {{ $data['name'] }} |
| **Profil** | {{ $data['profile'] }} |
| **Téléphone** | {{ $data['phone'] ?: '—' }} |
| **Email** | {{ $data['email'] ?: '—' }} |
</x-mail::table>

<x-mail::panel>
{{ $data['message'] }}
</x-mail::panel>

@if ($data['email'])
Répondez directement à cet email pour écrire à {{ $data['name'] }}.
@endif
</x-mail::message>
