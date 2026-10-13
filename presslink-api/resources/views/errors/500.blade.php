@extends('errors.layout')
@section('code', '500')
@section('title', 'Une erreur est survenue')
@section('accent', '#dc2626')
@section('icon')<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9Z"/></svg>@endsection
@section('message')Un problème technique nous empêche d'afficher cette page. Réessayez dans quelques instants ; si le problème persiste, contactez le support.@endsection
@section('actions')<button class="btn primary" type="button" onclick="location.reload()">Réessayer</button><a class="btn" href="/">Accueil</a>@endsection
