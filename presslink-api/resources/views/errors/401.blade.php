@extends('errors.layout')
@section('code', '401')
@section('title', 'Connexion requise')
@section('accent', '#d97706')
@section('icon')<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>@endsection
@section('message')Vous devez être connecté pour accéder à cette page.@endsection
@section('actions')<a class="btn primary" href="/login">Se connecter</a><a class="btn" href="/">Accueil</a>@endsection
