<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="app-debug" content="{{ config('app.debug') ? '1' : '0' }}">

        <title>{{ $title ?? config('app.name') }}</title>
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
        <link rel="icon" type="image/png" href="{{ asset('favicon-96.png') }}?v=2" sizes="96x96">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    {{-- Chaque portail (pressing / super admin) gère son propre fond et sa mise en page. --}}
    <body class="min-h-screen antialiased">
        {{ $slot }}

        <x-feedback />
        @livewireScripts
    </body>
</html>
