@php
    $title = 'PressLink — Suivi de commandes de pressing en Côte d\'Ivoire';
    $description = 'Suivez vos vêtements au pressing et recevez une notification dès qu\'ils sont prêts. Application gratuite et logiciel de gestion pour pressings en Côte d\'Ivoire.';
    $keywords = 'pressing Abidjan, application pressing, suivi commande pressing, logiciel gestion pressing, logiciel pressing Côte d\'Ivoire, gestion de pressing, pressing en ligne, teinturerie Abidjan, blanchisserie Abidjan, notification vêtements prêts, PressLink';

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $baseUrl.'/#organization',
                'name' => 'PressLink',
                'url' => $baseUrl.'/',
                'logo' => $baseUrl.'/apple-touch-icon.png',
                'slogan' => 'Le lien entre votre pressing et vos clients',
                'areaServed' => ['@type' => 'Country', 'name' => 'Côte d\'Ivoire'],
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'telephone' => $supportPhoneE164,
                    'email' => config('presslink.support.email'),
                    'contactType' => 'customer support',
                    'availableLanguage' => 'fr',
                    'areaServed' => 'CI',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $baseUrl.'/#website',
                'url' => $baseUrl.'/',
                'name' => 'PressLink',
                'inLanguage' => 'fr-CI',
                'publisher' => ['@id' => $baseUrl.'/#organization'],
            ],
            [
                '@type' => 'SoftwareApplication',
                '@id' => $baseUrl.'/#application',
                'name' => 'PressLink',
                'applicationCategory' => 'LifestyleApplication',
                'operatingSystem' => 'Android, iOS (application web installable)',
                'url' => $appUrl,
                'description' => $description,
                'inLanguage' => 'fr',
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'XOF'],
                'publisher' => ['@id' => $baseUrl.'/#organization'],
            ],
        ],
    ];
@endphp

<x-site.page :title="$title" :description="$description" :keywords="$keywords" :json-ld="$jsonLd">
    {{-- ===== Hero ===== --}}
    <section class="bg-(--color-bg) overflow-hidden">
        <div class="max-w-6xl mx-auto px-5 pt-12 md:pt-16 grid md:grid-cols-[1.1fr_1fr] gap-10 items-center">
            <div class="pb-4 md:pb-16">
                <p class="text-xs font-semibold uppercase tracking-widest text-(--color-primary) mb-4">Application de suivi de pressing</p>
                <h1 class="font-display text-4xl md:text-5xl font-extrabold leading-[1.08] tracking-tight mb-5">
                    Suivez vos vêtements au pressing et soyez prévenu dès qu'ils sont prêts
                </h1>
                <p class="text-lg text-(--color-text-secondary) leading-relaxed mb-8 max-w-xl">
                    PressLink est l'application de suivi de commandes de pressing en Côte d'Ivoire : <strong class="text-(--color-text-primary)">gratuite pour les clients</strong>, et un logiciel de gestion complet pour les pressings.
                </p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ $appUrl }}" class="inline-flex items-center justify-center h-12 px-6 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">
                        Ouvrir l'application
                    </a>
                    <a href="{{ route('site.how') }}" class="inline-flex items-center justify-center gap-2 h-12 px-6 rounded-xl border border-(--color-border) bg-white font-semibold hover:border-(--color-primary)">
                        <svg class="w-4 h-4 text-(--color-primary)" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        Voir comment ça marche
                    </a>
                </div>
                <p class="mt-5 text-sm text-(--color-text-muted)">Aucun téléchargement depuis un store : l'application s'installe en un geste depuis votre navigateur (<a href="{{ $appUrl }}" class="text-(--color-primary) font-semibold">presslink.org/app</a>).</p>
            </div>
            <div class="phone-mock phone-mock--hands max-w-md mx-auto w-full self-end">
                <div class="phone-mock__screen"><x-site.app-screen /></div>
                <img src="{{ asset('images/hands-phone.webp') }}" width="736" height="648" alt="Une cliente consulte sa commande prête dans l'application PressLink" fetchpriority="high">
            </div>
        </div>
    </section>

    {{-- ===== Vidéo du parcours ===== --}}
    <section class="max-w-6xl mx-auto px-5 py-16 md:py-20">
        <div class="max-w-2xl mb-8">
            <h2 class="font-display text-3xl font-bold tracking-tight mb-3">Du dépôt à la récupération, en vidéo</h2>
            <p class="text-(--color-text-secondary) text-lg">Inscription, code du pressing, suivi, notification : le vrai parcours dans l'application, commenté pas à pas (1 min 30).</p>
        </div>
        <video class="w-full rounded-3xl border border-(--color-border) shadow-sm bg-(--color-bg)" controls playsinline preload="none"
               poster="{{ asset('video/presslink-parcours.jpg') }}?v=2" width="1280" height="720">
            <source src="{{ asset('video/presslink-parcours.mp4') }}?v=2" type="video/mp4">
        </video>
        <a href="{{ route('site.how') }}" class="mt-4 inline-block font-semibold text-(--color-primary) hover:underline">Le parcours étape par étape →</a>
    </section>

    {{-- ===== Installation ===== --}}
    <section class="bg-(--color-bg)">
        <div class="max-w-6xl mx-auto px-5 py-16 md:py-20">
            <div class="max-w-2xl mb-10">
                <h2 class="font-display text-3xl font-bold tracking-tight mb-3">Installez PressLink en 3 étapes</h2>
                <p class="text-(--color-text-secondary) text-lg">Pas de store : ouvrez <a href="{{ $appUrl }}" class="font-semibold text-(--color-primary) hover:underline">presslink.org/app</a> et ajoutez l'application à votre écran d'accueil.</p>
            </div>
            <div class="grid lg:grid-cols-2 gap-8">
                <div class="bg-white border border-(--color-border) rounded-3xl p-6 md:p-8">
                    <h3 class="font-display text-2xl font-bold mb-6">Sur Android <span class="text-(--color-text-muted) font-semibold text-base">· Chrome</span></h3>
                    <x-site.install-demo os="android" />
                </div>
                <div class="bg-white border border-(--color-border) rounded-3xl p-6 md:p-8">
                    <h3 class="font-display text-2xl font-bold mb-6">Sur iPhone <span class="text-(--color-text-muted) font-semibold text-base">· Safari</span></h3>
                    <x-site.install-demo os="ios" />
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Accès aux pages ===== --}}
    <section>
        <div class="max-w-6xl mx-auto px-5 py-16 md:py-20 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['site.clients', 'Pour les clients', 'Suivi en temps réel, notifications, historique et tarifs de votre pressing.'],
                ['site.pressings', 'Pour les pressings', 'Un logiciel de gestion simple : commandes, clients, équipe, exports.'],
                ['site.install', 'Installer l\'application', 'Android ou iPhone : l\'installation pas à pas, en images.'],
            ] as [$route, $heading, $text])
                <a href="{{ route($route) }}" class="group bg-white border border-(--color-border) rounded-2xl p-6 hover:border-(--color-primary) hover:-translate-y-0.5">
                    <h2 class="font-display text-xl font-bold mb-2">{{ $heading }}</h2>
                    <p class="text-(--color-text-secondary) leading-relaxed mb-4">{{ $text }}</p>
                    <span class="font-semibold text-(--color-primary)">Découvrir <span class="inline-block group-hover:translate-x-1 transition-transform">→</span></span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ===== QR + contact ===== --}}
    <section class="max-w-6xl mx-auto px-5 py-16 md:py-20 grid gap-10 md:grid-cols-2 items-center">
        <div class="bg-white border border-(--color-border) rounded-3xl shadow-sm p-6 text-center max-w-sm mx-auto w-full">
            <img src="{{ asset('qr-presslink-app.png') }}" width="280" height="280" alt="Code QR pour ouvrir l'application PressLink : presslink.org/app" class="mx-auto rounded-xl" loading="lazy">
            <p class="mt-4 font-display text-lg font-bold">Scannez pour installer l'application</p>
            <a href="{{ $appUrl }}" class="mt-1 inline-block text-sm font-semibold text-(--color-primary) hover:underline">presslink.org/app</a>
        </div>
        <div>
            <h2 class="font-display text-3xl font-bold tracking-tight mb-3">Une question ? Parlons-en.</h2>
            <p class="text-(--color-text-secondary) text-lg mb-2">L'équipe PressLink vous répond par appel ou sur WhatsApp.</p>
            <p class="font-display text-2xl font-extrabold text-(--color-primary-600) mb-6">{{ $supportPhoneDisplay }}</p>
            <x-site.contact-buttons />
        </div>
    </section>
</x-site.page>
