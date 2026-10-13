@extends('errors.layout')
@section('code', '404')
@section('title', 'Page introuvable')
@section('accent', '#2563eb')
@section('icon')<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M8.5 11h5"/></svg>@endsection
@section('message')Cette page n'existe pas ou a été déplacée. Vérifiez l'adresse, ou repartez de l'accueil.@endsection

