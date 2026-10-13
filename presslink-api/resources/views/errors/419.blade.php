@extends('errors.layout')
@section('code', '419')
@section('title', 'Session expirée')
@section('accent', '#7c3aed')
@section('icon')<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>@endsection
@section('message')Votre session a expiré après un moment d'inactivité. Rechargez la page et recommencez : vos données n'ont pas été enregistrées.@endsection
@section('actions')<button class="btn primary" type="button" onclick="location.reload()">Recharger la page</button><a class="btn" href="/login">Se reconnecter</a>@endsection
