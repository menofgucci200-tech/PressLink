@props(['title', 'description', 'canonical', 'ogImage', 'keywords' => null, 'jsonLd' => null, 'breadcrumbJsonLd' => null])
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">
        @if ($keywords)
            <meta name="keywords" content="{{ $keywords }}">
        @endif
        <meta name="robots" content="index, follow, max-image-preview:large">
        <meta name="author" content="PressLink">
        <meta name="theme-color" content="#2563EB">
        @if (config('services.google.site_verification'))
            <meta name="google-site-verification" content="{{ config('services.google.site_verification') }}">
        @endif
        <link rel="canonical" href="{{ $canonical }}">

        {{-- Google exige un favicon d'au moins 48 px (multiple de 48) pour l'afficher dans ses résultats --}}
        <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
        <link rel="icon" type="image/png" href="{{ asset('favicon-48.png') }}?v=2" sizes="48x48">
        <link rel="icon" type="image/png" href="{{ asset('favicon-96.png') }}?v=2" sizes="96x96">
        <link rel="icon" type="image/png" href="{{ asset('favicon-192.png') }}?v=2" sizes="192x192">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">

        {{-- Open Graph / Twitter : aperçu lors d'un partage (WhatsApp, Facebook…) --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="PressLink">
        <meta property="og:locale" content="fr_CI">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="PressLink — suivi de commandes et gestion de pressing">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ $ogImage }}">

        @if ($jsonLd)
            <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif
        @if ($breadcrumbJsonLd)
            <script type="application/ld+json">{!! json_encode($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif

        <x-theme-script />
        <script>document.documentElement.classList.add('js-motion');</script>
        @vite(['resources/css/app.css', 'resources/js/site.js'])
    </head>
    <body class="bg-(--color-surface) text-(--color-text-primary) antialiased">
        {{ $slot }}
    </body>
</html>
