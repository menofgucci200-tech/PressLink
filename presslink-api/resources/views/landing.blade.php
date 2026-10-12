@php
    $title = 'PressLink — Suivi de commandes de pressing en Côte d\'Ivoire';
    $description = 'Suivez vos vêtements au pressing et recevez une notification dès qu\'ils sont prêts. Application gratuite et logiciel de gestion pour pressings en Côte d\'Ivoire.';
    $keywords = 'pressing Abidjan, application pressing, suivi commande pressing, logiciel gestion pressing, logiciel pressing Côte d\'Ivoire, gestion de pressing, pressing en ligne, teinturerie Abidjan, blanchisserie Abidjan, notification vêtements prêts, PressLink';

    $whatsappPressing = 'https://wa.me/'.ltrim($supportPhoneE164, '+').'?text='.rawurlencode('Bonjour, je souhaite en savoir plus sur PressLink pour mon pressing.');
    $whatsappSupport = 'https://wa.me/'.ltrim($supportPhoneE164, '+').'?text='.rawurlencode('Bonjour, j\'ai besoin d\'aide avec PressLink.');

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
            [
                '@type' => 'FAQPage',
                'mainEntity' => collect($faqs)->map(fn ($faq) => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                ])->all(),
            ],
        ],
    ];
@endphp

<x-layouts.public
    :title="$title"
    :description="$description"
    :keywords="$keywords"
    :canonical="$baseUrl.'/'"
    :og-image="$baseUrl.'/og-image.png'"
    :json-ld="$jsonLd">

    {{-- ===== En-tête ===== --}}
    <header class="sticky top-0 z-20 bg-white/90 backdrop-blur border-b border-(--color-border)">
        <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="font-display text-xl font-bold tracking-tight" aria-label="PressLink, accueil">
                Press<span class="text-(--color-primary)">Link</span>
            </a>
            <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-(--color-text-secondary)" aria-label="Navigation principale">
                <a href="#clients" class="hover:text-(--color-primary)">Pour les clients</a>
                <a href="#pressings" class="hover:text-(--color-primary)">Pour les pressings</a>
                <a href="#installer" class="hover:text-(--color-primary)">Installer</a>
                <a href="#faq" class="hover:text-(--color-primary)">Questions fréquentes</a>
            </nav>
            <a href="{{ route('login') }}" class="inline-flex items-center h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
                Espace pressing
            </a>
        </div>
    </header>

    <main>
        {{-- ===== Hero ===== --}}
        <section class="bg-(--color-bg)">
            <div class="max-w-6xl mx-auto px-5 py-14 md:py-20 grid md:grid-cols-[1.2fr_1fr] gap-12 items-center">
                <div>
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
                        <a href="#pressings" class="inline-flex items-center justify-center h-12 px-6 rounded-xl border border-(--color-border) bg-white font-semibold hover:border-(--color-primary)">
                            Je suis un pressing
                        </a>
                    </div>
                    <p class="mt-5 text-sm text-(--color-text-muted)">Aucun téléchargement depuis un store : l'application s'installe en un geste depuis votre navigateur.</p>
                </div>

                {{-- Code QR : scanner = ouvrir l'application web --}}
                <div class="mx-auto w-full max-w-sm">
                    <div class="bg-white border border-(--color-border) rounded-3xl shadow-sm p-6 text-center">
                        <img src="{{ asset('qr-presslink-app.png') }}" width="280" height="280" alt="Code QR pour ouvrir l'application PressLink : presslink.org/app" class="mx-auto rounded-xl" loading="eager">
                        <p class="mt-4 font-display text-lg font-bold">Scannez pour installer l'application</p>
                        <a href="{{ $appUrl }}" class="mt-1 inline-block text-sm font-semibold text-(--color-primary) hover:underline">presslink.org/app</a>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== Clients : comment ça marche ===== --}}
        <section id="clients" class="max-w-6xl mx-auto px-5 py-16 md:py-20">
            <div class="max-w-2xl mb-10">
                <h2 class="font-display text-3xl font-bold tracking-tight mb-3">Suivre sa commande de pressing, simplement</h2>
                <p class="text-(--color-text-secondary) text-lg">Plus besoin d'appeler ou de passer au pressing « pour voir si c'est prêt ». Vous êtes informé en temps réel.</p>
            </div>
            <ol class="grid md:grid-cols-3 gap-5">
                @foreach ([
                    ['1', 'Rejoignez votre pressing', 'Saisissez le code de votre pressing (par exemple PE-4821) dans l\'application. Vous le faites une seule fois.'],
                    ['2', 'Déposez vos vêtements', 'Le pressing enregistre votre commande : vous la retrouvez aussitôt dans l\'application, avec le détail des articles et le total.'],
                    ['3', 'Soyez notifié', 'Dès que votre commande est prête, une notification vous prévient. Vous passez récupérer vos vêtements au bon moment.'],
                ] as [$n, $heading, $text])
                    <li class="bg-(--color-bg) border border-(--color-border) rounded-2xl p-6">
                        <div class="w-10 h-10 rounded-full bg-(--color-primary) text-white font-bold flex items-center justify-center mb-4">{{ $n }}</div>
                        <h3 class="font-display text-lg font-bold mb-2">{{ $heading }}</h3>
                        <p class="text-(--color-text-secondary) leading-relaxed">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>

            <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ([
                    ['Suivi en temps réel', 'Reçue, en traitement, prête, récupérée : l\'état de chaque commande est toujours à jour.'],
                    ['Notifications', 'Un message sur votre téléphone quand votre commande est prête à être récupérée.'],
                    ['Historique complet', 'Retrouvez toutes vos commandes passées, leurs articles et leurs montants en FCFA.'],
                    ['Infos pratiques du pressing', 'Adresse, horaires d\'ouverture, numéro de téléphone et code du pressing, dans l\'application.'],
                    ['Tarifs consultables', 'Quand votre pressing le souhaite, sa grille de tarifs est visible directement dans l\'application.'],
                    ['Signalement d\'un problème', 'Un article manquant ou abîmé ? Signalez-le en quelques secondes depuis la commande concernée.'],
                ] as [$heading, $text])
                    <article class="border border-(--color-border) rounded-2xl p-6">
                        <h3 class="font-semibold mb-1.5">{{ $heading }}</h3>
                        <p class="text-sm text-(--color-text-secondary) leading-relaxed">{{ $text }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- ===== Pressings : logiciel de gestion ===== --}}
        <section id="pressings" class="bg-(--color-text-primary) text-white">
            <div class="max-w-6xl mx-auto px-5 py-16 md:py-20 grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-blue-300 mb-3">Pour les pressings</p>
                    <h2 class="font-display text-3xl font-bold tracking-tight mb-4">Un logiciel de gestion de pressing simple et complet</h2>
                    <p class="text-slate-300 text-lg leading-relaxed mb-8">
                        Gérez vos commandes, vos clients et votre équipe depuis un seul tableau de bord, et tenez vos clients informés automatiquement.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ $whatsappPressing }}" class="inline-flex items-center justify-center h-12 px-6 rounded-xl bg-white text-(--color-text-primary) font-semibold hover:bg-blue-50">
                            Nous écrire sur WhatsApp
                        </a>
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center h-12 px-6 rounded-xl border border-white/30 font-semibold hover:bg-white/10">
                            Accéder à l'espace pressing
                        </a>
                    </div>
                </div>
                <ul class="grid sm:grid-cols-2 gap-3">
                    @foreach ([
                        'Création et suivi des commandes',
                        'Fiches clients et historique',
                        'Services et tarifs, avec variantes',
                        'Équipe : employés et administrateurs',
                        'Tableau de bord et indicateurs du jour',
                        'Exports CSV, Excel et PDF',
                        'Notifications automatiques aux clients',
                        'Plusieurs pressings depuis un même compte',
                    ] as $feature)
                        <li class="flex items-start gap-3 bg-white/5 border border-white/10 rounded-xl p-4 text-sm leading-snug">
                            <svg class="w-5 h-5 flex-none text-blue-300 mt-px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- ===== Installation ===== --}}
        <section id="installer" class="max-w-6xl mx-auto px-5 py-16 md:py-20">
            <div class="max-w-2xl mb-10">
                <h2 class="font-display text-3xl font-bold tracking-tight mb-3">Installer PressLink sur votre téléphone</h2>
                <p class="text-(--color-text-secondary) text-lg">Ouvrez <a href="{{ $appUrl }}" class="font-semibold text-(--color-primary) hover:underline">presslink.org/app</a> ou scannez le code QR ci-dessus, puis ajoutez l'application à votre écran d'accueil.</p>
            </div>
            <div class="grid md:grid-cols-2 gap-5">
                <div class="border border-(--color-border) rounded-2xl p-6">
                    <h3 class="font-display text-lg font-bold mb-3">Sur Android (Chrome)</h3>
                    <ol class="list-decimal pl-5 space-y-2 text-(--color-text-secondary)">
                        <li>Ouvrez <strong>presslink.org/app</strong> dans Chrome.</li>
                        <li>Appuyez sur le bouton <strong>« Installer »</strong> qui s'affiche dans l'application.</li>
                        <li>L'icône PressLink apparaît sur votre écran d'accueil.</li>
                    </ol>
                </div>
                <div class="border border-(--color-border) rounded-2xl p-6">
                    <h3 class="font-display text-lg font-bold mb-3">Sur iPhone (Safari)</h3>
                    <ol class="list-decimal pl-5 space-y-2 text-(--color-text-secondary)">
                        <li>Ouvrez <strong>presslink.org/app</strong> dans Safari.</li>
                        <li>Touchez le bouton <strong>Partager</strong>, puis <strong>« Sur l'écran d'accueil »</strong>.</li>
                        <li>Ouvrez PressLink depuis son icône et connectez-vous.</li>
                    </ol>
                </div>
            </div>
        </section>

        {{-- ===== FAQ ===== --}}
        <section id="faq" class="bg-(--color-bg)">
            <div class="max-w-3xl mx-auto px-5 py-16 md:py-20">
                <h2 class="font-display text-3xl font-bold tracking-tight mb-8">Questions fréquentes</h2>
                <div class="space-y-3">
                    @foreach ($faqs as $faq)
                        <details class="group bg-white border border-(--color-border) rounded-xl">
                            <summary class="flex items-center justify-between gap-4 cursor-pointer list-none px-5 py-4 font-semibold">
                                {{ $faq['question'] }}
                                <svg class="w-5 h-5 flex-none text-(--color-text-muted) transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </summary>
                            <p class="px-5 pb-5 text-(--color-text-secondary) leading-relaxed">{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ===== Support ===== --}}
        <section id="support" class="max-w-6xl mx-auto px-5 py-16 md:py-20">
            <div class="bg-(--color-primary-tint) border border-blue-100 rounded-3xl px-6 py-10 md:px-12 text-center">
                <h2 class="font-display text-3xl font-bold tracking-tight mb-3">Une question ? Un problème ?</h2>
                <p class="text-(--color-text-secondary) text-lg mb-6">L'équipe PressLink vous répond par appel ou sur WhatsApp.</p>
                <p class="font-display text-3xl font-extrabold text-(--color-primary-600) mb-6">{{ $supportPhoneDisplay }}</p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="tel:{{ $supportPhoneE164 }}" class="inline-flex items-center justify-center h-12 px-6 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">Appeler le support</a>
                    <a href="{{ $whatsappSupport }}" class="inline-flex items-center justify-center h-12 px-6 rounded-xl bg-[#25D366] text-white font-semibold hover:brightness-95">Écrire sur WhatsApp</a>
                </div>
            </div>
        </section>
    </main>

    {{-- ===== Pied de page ===== --}}
    <footer class="border-t border-(--color-border)">
        <div class="max-w-6xl mx-auto px-5 py-8 flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-(--color-text-muted)">
            <p><span class="font-display font-bold text-(--color-text-primary)">Press<span class="text-(--color-primary)">Link</span></span> — Le lien entre votre pressing et vos clients.</p>
            <nav class="flex items-center gap-5" aria-label="Pied de page">
                <a href="{{ $appUrl }}" class="hover:text-(--color-primary)">Application</a>
                <a href="{{ route('login') }}" class="hover:text-(--color-primary)">Espace pressing</a>
                <a href="#support" class="hover:text-(--color-primary)">Support</a>
            </nav>
            <p>© {{ date('Y') }} PressLink · Côte d'Ivoire</p>
        </div>
    </footer>
</x-layouts.public>
