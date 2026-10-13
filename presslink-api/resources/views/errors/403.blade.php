@extends('errors.layout')
@section('code', '403')
@section('title', 'Accès refusé')
@section('accent', '#d97706')
@section('icon')<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>@endsection
@section('message')Votre compte n'a pas l'autorisation d'afficher cette page. Si vous pensez que c'est une erreur, demandez à l'administrateur de votre pressing.@endsection

