@php $legal = config('presslink.legal'); @endphp
<x-site.legal
    title="Mentions légales — PressLink"
    heading="Mentions légales"
    description="Mentions légales du site presslink.org et de l'application PressLink : éditeur, hébergeur, propriété intellectuelle et contact."
    path="/mentions-legales">

    <h2>Éditeur</h2>
    <p>
        Le site <strong>presslink.org</strong> et l'application PressLink sont édités par
        <strong>{{ $legal['company'] }}</strong>@if ($legal['legal_form']), {{ $legal['legal_form'] }}@endif.
    </p>
    <ul>
        <li>Adresse : {{ $legal['address'] }}</li>
        @if ($legal['rccm'])<li>RCCM : {{ $legal['rccm'] }}</li>@endif
        @if ($legal['tax_id'])<li>Compte contribuable : {{ $legal['tax_id'] }}</li>@endif
        <li>Téléphone et WhatsApp : {{ config('presslink.support.phone_display') }}</li>
        @if ($legal['email'])<li>Email : <a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a></li>@endif
        @if ($legal['publisher'])<li>Directeur de la publication : {{ $legal['publisher'] }}</li>@endif
    </ul>

    <h2>Hébergement</h2>
    <p>Le site et l'application sont hébergés par <strong>LWS (Ligne Web Services)</strong>, SAS, 10 rue Penthièvre, 75008 Paris, France — <a href="https://www.lws.fr" rel="noopener" target="_blank">www.lws.fr</a>.</p>

    <h2>Propriété intellectuelle</h2>
    <p>La marque PressLink, son logo, les textes, visuels, vidéos et le logiciel du site et de l'application sont la propriété de l'éditeur. Toute reproduction ou réutilisation, totale ou partielle, sans autorisation écrite préalable est interdite.</p>

    <h2>Données personnelles</h2>
    <p>Le traitement de vos données est décrit dans la <a href="{{ route('site.privacy') }}">politique de confidentialité</a>, conformément à la loi ivoirienne n° 2013-450 du 19 juin 2013 relative à la protection des données à caractère personnel.</p>

    <h2>Contact</h2>
    <p>Pour toute question, rendez-vous sur la page <a href="{{ route('site.contact') }}">Contact</a> ou appelez le {{ config('presslink.support.phone_display') }}.</p>
</x-site.legal>
