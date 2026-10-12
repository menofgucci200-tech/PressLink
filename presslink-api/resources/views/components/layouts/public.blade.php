@props(['title', 'description', 'canonical', 'ogImage', 'keywords' => null, 'jsonLd' => null])
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
        <link rel="canonical" href="{{ $canonical }}">

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

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

        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-(--color-surface) text-(--color-text-primary) antialiased">
        {{ $slot }}
    </body>
</html>
