@extends('errors.layout')
@section('code', '503')
@section('title', 'Maintenance en cours')
@section('accent', '#0891b2')
@section('icon')<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/></svg>@endsection
@section('message')PressLink est en cours de mise à jour pour vous offrir un meilleur service. Revenez dans quelques minutes.@endsection
@section('actions')<button class="btn primary" type="button" onclick="location.reload()">Actualiser</button>@endsection
