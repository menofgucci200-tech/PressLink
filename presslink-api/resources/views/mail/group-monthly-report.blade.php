Bonjour {{ $user->name }},

Voici le bilan de vos {{ $rows->count() }} pressings pour {{ $month->translatedFormat('F Y') }} :

- Encaissé : {{ number_format($totals['revenue'], 0, ',', ' ') }} F
- Commandes déposées : {{ $totals['orders'] }}
- Panier moyen : {{ number_format($totals['average_basket'], 0, ',', ' ') }} F
- Reste à encaisser : {{ number_format($totals['outstanding'], 0, ',', ' ') }} F

@foreach ($rows->sortByDesc('revenue') as $row)
{{ $row['pressing']->name }} : {{ number_format($row['revenue'], 0, ',', ' ') }} F encaissés, {{ $row['orders'] }} commande(s)
@endforeach

Le rapport complet est en pièce jointe (PDF).
Tableau de bord du groupe : {{ route('pressings.overview') }}

Pour ne plus recevoir ce rapport : Rapports du groupe > Envoi automatique.

L'équipe PressLink
