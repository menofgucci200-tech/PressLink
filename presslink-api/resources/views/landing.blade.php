@php
    $offer = \App\Support\LaunchOffer::isActive();
    $until = \App\Support\LaunchOffer::dateLabel();
    $daysLeft = \App\Support\LaunchOffer::daysLeft();

    $title = $offer
        ? 'PressLink — Logiciel pressing gratuit jusqu\'au '.$until
        : 'PressLink — Logiciel de gestion de pressing en Côte d\'Ivoire';
    $description = 'Commandes, paiements, reçus et caisse au même endroit, clients prévenus dès que c\'est prêt. Le logiciel de pressing ivoirien'
        .($offer ? ', gratuit jusqu\'au '.$until.'.' : '.');
    $join = 'https://wa.me/'.ltrim($supportPhoneE164, '+').'?text='.rawurlencode($offer
        ? 'Bonjour, je veux inscrire mon pressing sur PressLink (offre de lancement, gratuit jusqu\'au '.$until.').'
        : 'Bonjour, je veux inscrire mon pressing sur PressLink.');
    $keywords = 'pressing Abidjan, application pressing, suivi commande pressing, logiciel gestion pressing, logiciel pressing Côte d\'Ivoire, gestion de pressing, pressing en ligne, teinturerie Abidjan, blanchisserie Abidjan, notification vêtements prêts, PressLink';

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $baseUrl.'/#organization',
                'name' => 'PressLink',
                'url' => $baseUrl.'/',
                // Logo carré dédié (Google : au moins 112 px, de préférence 512 px).
                'logo' => [
                    '@type' => 'ImageObject',
                    '@id' => $baseUrl.'/#logo',
                    'url' => $baseUrl.'/images/logo-presslink.png',
                    'contentUrl' => $baseUrl.'/images/logo-presslink.png',
                    'width' => 512,
                    'height' => 512,
                    'caption' => 'PressLink',
                ],
                'image' => ['@id' => $baseUrl.'/#logo'],
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
    {{-- ===== Lancement officiel ===== --}}
    <section class="relative overflow-hidden bg-[#0B1530] text-white">
        <div class="absolute inset-0 bg-[radial-gradient(120%_80%_at_85%_0%,#2563EB_0%,transparent_55%),radial-gradient(90%_70%_at_0%_100%,#12245A_0%,transparent_60%)]" aria-hidden="true"></div>
        <div class="relative max-w-6xl mx-auto px-5 pt-12 md:pt-16 pb-14 md:pb-20 grid lg:grid-cols-[1.05fr_1fr] gap-12 items-center">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-6">
                    <span class="inline-flex items-center gap-2 h-8 px-3.5 rounded-full bg-white/10 border border-white/20 text-sm font-semibold">
                        <span class="w-2 h-2 rounded-full bg-[#22C55E] shadow-[0_0_0_4px_rgba(34,197,94,.25)]" aria-hidden="true"></span>
                        {{ $offer ? 'Lancement officiel' : 'Disponible en Côte d\'Ivoire' }}
                    </span>
                    @if ($offer)
                        <span class="inline-flex items-center h-8 px-3.5 rounded-full bg-[#FBBF24] text-[#1E293B] text-sm font-bold">Gratuit jusqu'au {{ $until }}</span>
                    @endif
                </div>
                <h1 class="font-display text-4xl md:text-5xl font-extrabold leading-[1.06] tracking-tight mb-5">
                    Votre pressing, enfin simple à gérer.
                    <span class="text-[#93C5FD]">Vos clients, toujours informés.</span>
                </h1>
                <p class="text-lg text-[#CBD5E1] leading-relaxed mb-8 max-w-xl">
                    PressLink est le logiciel de gestion de pressing pensé pour la Côte d'Ivoire : commandes, paiements, reçus et caisse au même endroit. Et dès qu'une commande est prête, votre client est prévenu sur son téléphone.
                </p>

                @if ($offer)
                    <div class="mb-8 max-w-xl flex items-center gap-4 rounded-2xl bg-white/[0.07] border border-white/15 px-5 py-4">
                        <div class="flex-none text-center">
                            <div class="font-display text-4xl font-extrabold leading-none text-[#FBBF24] tabular-nums">{{ $daysLeft }}</div>
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-[#CBD5E1] mt-1">{{ $daysLeft > 1 ? 'jours' : 'jour' }}</div>
                        </div>
                        <p class="text-[15px] leading-snug">
                            <strong>100 % gratuit jusqu'au {{ $until }}.</strong>
                            <span class="text-[#CBD5E1]">Toutes les fonctions, sans rien payer : inscrivez votre pressing pendant le lancement.</span>
                        </p>
                    </div>
                @endif

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('signup') }}" class="inline-flex items-center justify-center gap-2.5 min-h-13 py-2 px-6 rounded-xl bg-[#22C55E] text-white font-bold hover:brightness-95 shadow-lg shadow-black/20 sm:whitespace-nowrap">
                        {{ $offer ? 'Inscrire mon pressing gratuitement' : 'Inscrire mon pressing' }}
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a href="{{ $appUrl }}" class="inline-flex items-center justify-center h-13 px-6 rounded-xl border border-white/30 font-semibold hover:bg-white/10 whitespace-nowrap">
                        Je suis client →
                    </a>
                </div>
                <p class="mt-4 text-sm text-[#CBD5E1]">Pas encore décidé ? <a href="{{ route('sandbox.start') }}" class="font-semibold text-white underline underline-offset-2 hover:no-underline">Tester avec un pressing fictif</a>, sans inscription.</p>
                <p class="mt-3 text-sm text-[#94A3B8]">Mise en place avec l'équipe PressLink · Sur ordinateur, tablette ou téléphone · Appel ou WhatsApp : {{ $supportPhoneDisplay }}</p>
            </div>

            <div class="relative max-w-xl mx-auto w-full pb-10 lg:pb-0" aria-hidden="true">
                <div class="rounded-2xl bg-[#0E1424] p-2.5 pb-3 shadow-[0_40px_80px_-20px_rgba(0,0,0,.6)] ring-1 ring-white/10">
                    <img src="{{ asset('images/launch-dashboard.webp') }}" width="1600" height="1000" alt="" class="rounded-lg w-full h-auto" fetchpriority="high">
                </div>
                <div class="mx-auto h-3 w-[108%] -ml-[4%] rounded-b-3xl bg-gradient-to-b from-[#C9D2E3] to-[#8C97AD]"></div>
                <div class="absolute -bottom-2 right-2 sm:-right-4 w-[34%] max-w-[190px] rounded-[1.6rem] bg-[#0B0F19] p-1.5 shadow-[0_30px_60px_-15px_rgba(0,0,0,.7)] ring-1 ring-white/10 rotate-3">
                    <img src="{{ asset('images/launch-app.webp') }}" width="600" height="1290" alt="" class="rounded-[1.25rem] w-full h-auto" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Pourquoi maintenant ===== --}}
    <section class="max-w-6xl mx-auto px-5 py-16 md:py-20">
        <div class="max-w-2xl mb-10">
            <p class="text-xs font-semibold uppercase tracking-widest text-(--color-primary) mb-3">Pourquoi rejoindre PressLink maintenant</p>
            <h2 class="font-display text-3xl md:text-4xl font-bold tracking-tight">Le bon moment pour moderniser votre pressing</h2>
        </div>
        <div class="grid md:grid-cols-3 gap-5" data-motion="cascade">
            @foreach (array_values(array_filter([
                $offer ? ['🎁', 'Gratuit jusqu\'au '.$until, 'Toutes les fonctions pendant le lancement, sans rien payer. Vous voyez par vous-même ce que PressLink change au quotidien.'] : null,
                ['🤝', 'Accompagné pas à pas', 'Inscription en 2 minutes, puis notre équipe vous appelle pour vous aider à démarrer. Une question ? Un appel ou un message WhatsApp suffit.'],
                ['🔔', 'Moins d\'appels, moins d\'attente', 'Vos clients sont prévenus automatiquement quand leur commande est prête : fini les « c\'est prêt ? » et les passages pour rien.'],
                $offer ? null : ['📈', 'Votre activité en un coup d\'œil', 'Encaissé du jour, commandes en cours, retards : le tableau de bord vous dit l\'essentiel dès l\'ouverture.'],
            ])) as [$icon, $heading, $text])
                <article class="bg-(--color-surface) border border-(--color-border) rounded-2xl p-6">
                    <div class="w-12 h-12 rounded-xl bg-(--color-primary-tint) flex items-center justify-center text-2xl mb-4" aria-hidden="true">{{ $icon }}</div>
                    <h3 class="font-display text-xl font-bold mb-2">{{ $heading }}</h3>
                    <p class="text-(--color-text-secondary) leading-relaxed">{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ===== L'outil du pressing ===== --}}
    <section class="bg-(--color-bg)">
        <div class="max-w-6xl mx-auto px-5 py-16 md:py-20 grid lg:grid-cols-[1fr_1.1fr] gap-12 items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-(--color-primary) mb-3">Pour le pressing</p>
                <h2 class="font-display text-3xl md:text-4xl font-bold tracking-tight mb-4">Tout votre pressing au même endroit</h2>
                <p class="text-(--color-text-secondary) text-lg mb-8">Un vrai outil de gestion, utile dès le premier jour, même si vos clients n'ont pas encore l'application.</p>
                <ul class="grid sm:grid-cols-2 gap-x-6 gap-y-5">
                    @foreach ([
                        ['Commandes en 4 étapes', 'Client, articles, paiement, confirmation : un dépôt enregistré en quelques secondes.'],
                        ['Paiements et acomptes', 'Espèces, Wave, Orange Money, MTN MoMo, Moov Money : payé, acompte ou au retrait.'],
                        ['Reçus', 'Ticket 80 mm, feuille A5 ou PDF, à imprimer ou à envoyer.'],
                        ['Caisse du jour', 'Encaissements par moyen de paiement et clôture de caisse, avec les écarts.'],
                        ['Clients et équipe', 'Fichier clients, historique, rôles pour chaque membre de l\'équipe.'],
                        ['Plusieurs pressings', 'Une vue d\'ensemble, des alertes et un rapport mensuel pour tout le groupe.'],
                    ] as [$heading, $text])
                        <li class="flex gap-3">
                            <span class="flex-none mt-0.5 w-6 h-6 rounded-full bg-(--color-success-tint) text-(--color-success-text) flex items-center justify-center" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                            </span>
                            <span><strong class="block font-semibold">{{ $heading }}</strong><span class="text-sm text-(--color-text-secondary) leading-relaxed">{{ $text }}</span></span>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('site.pressings') }}" class="mt-8 inline-block font-semibold text-(--color-primary) hover:underline">Découvrir le logiciel en détail →</a>
            </div>
            <figure class="rounded-2xl border border-(--color-border) bg-(--color-surface) p-2 shadow-sm">
                <img src="{{ asset('images/launch-caisse.webp') }}" width="1600" height="1000" alt="Caisse du jour dans PressLink : encaissements par moyen de paiement, paiements et clôture sans écart" class="rounded-xl w-full h-auto" loading="lazy">
                <figcaption class="px-2 pt-2.5 pb-1 text-sm text-(--color-text-muted)">La caisse du jour : encaissements par moyen de paiement et clôture, ici sans écart.</figcaption>
            </figure>
        </div>
    </section>

    {{-- ===== Et les clients ===== --}}
    <section class="max-w-6xl mx-auto px-5 py-16 md:py-20 grid lg:grid-cols-[1.1fr_1fr] gap-12 items-center">
        <div class="order-2 lg:order-1 grid sm:grid-cols-[auto_1fr] gap-8 items-center">
            <div class="mx-auto w-52 rounded-[2rem] bg-[#0B0F19] p-2 shadow-xl">
                <img src="{{ asset('images/launch-app.webp') }}" width="600" height="1290" alt="Détail d'une commande prête dans l'application PressLink" class="rounded-[1.6rem] w-full h-auto" loading="lazy">
            </div>
            <div class="bg-(--color-surface) border border-(--color-border) rounded-3xl p-6 text-center">
                <img src="{{ asset('qr-presslink-app.png') }}" width="200" height="200" alt="Code QR pour ouvrir l'application PressLink : presslink.org/app" class="mx-auto rounded-xl w-40 h-40" loading="lazy">
                <p class="mt-3 font-display font-bold">Scannez pour installer</p>
                <a href="{{ $appUrl }}" class="text-sm font-semibold text-(--color-primary) hover:underline">presslink.org/app</a>
                <p class="mt-2 text-xs text-(--color-text-muted)">Gratuit pour les clients · Sans passer par un store</p>
            </div>
        </div>
        <div class="order-1 lg:order-2">
            <p class="text-xs font-semibold uppercase tracking-widest text-(--color-primary) mb-3">Pour vos clients</p>
            <h2 class="font-display text-3xl md:text-4xl font-bold tracking-tight mb-4">Leur numéro suffit</h2>
            <p class="text-(--color-text-secondary) text-lg mb-8">Pas de code à retenir : le numéro de téléphone relie chaque client à ses commandes, dans tous les pressings PressLink.</p>
            <ol class="space-y-5">
                @foreach ([
                    ['Vous enregistrez la commande', 'avec le nom et le numéro du client, comme d\'habitude.'],
                    ['Le client installe PressLink', 'et entre son numéro : ses commandes sont déjà là.'],
                    ['Il est prévenu quand c\'est prêt', 'et sait ce qu\'il reste à payer avant de se déplacer.'],
                ] as $i => [$heading, $text])
                    <li class="flex gap-4">
                        <span class="flex-none w-9 h-9 rounded-full bg-(--color-primary) text-white font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                        <p class="pt-1.5 leading-relaxed"><strong>{{ $heading }}</strong> <span class="text-(--color-text-secondary)">{{ $text }}</span></p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ===== En vidéo ===== --}}
    <section class="bg-(--color-bg)">
        <div class="max-w-6xl mx-auto px-5 py-16 md:py-20 grid lg:grid-cols-[1.3fr_1fr] gap-10 items-center">
            <div data-motion="videos"><figure>
                <video class="w-full rounded-3xl border border-(--color-border) shadow-sm bg-(--color-bg)" controls playsinline preload="none"
                       poster="{{ asset('video/presslink-presentation.jpg') }}" width="1280" height="720">
                    <source src="{{ asset('video/presslink-presentation.mp4') }}" type="video/mp4">
                </video>
                <figcaption class="mt-3 font-semibold">Découvrir PressLink : l'application et le logiciel de gestion <span class="font-normal text-(--color-text-muted)">· 1 min 22</span></figcaption>
            </figure></div>
            <div>
                <h2 class="font-display text-3xl font-bold tracking-tight mb-3">PressLink, en vidéo</h2>
                <p class="text-(--color-text-secondary) text-lg mb-6">Filmé dans le tableau de bord du pressing et dans l'application des clients : un dépôt au comptoir, l'encaissement, puis la notification « prête ».</p>
                <a href="{{ route('site.how') }}" class="font-semibold text-(--color-primary) hover:underline">Le parcours étape par étape, en vidéo →</a>
            </div>
        </div>
    </section>

    {{-- ===== Nos offres : l'accroche vers la page Offres ===== --}}
    <section class="bg-slate-900 text-white dark:border-y dark:border-(--color-border)">
        <div class="max-w-6xl mx-auto px-5 py-16 md:py-20">
            <div class="flex flex-wrap items-end justify-between gap-6 mb-8">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-widest text-blue-300 mb-3">Nos offres</p>
                    <h2 class="font-display text-3xl md:text-4xl font-bold tracking-tight mb-3">{{ $offer ? 'Et après le '.$until.' ?' : 'Vous cherchez la meilleure offre pour votre pressing ?' }}</h2>
                    <p class="text-slate-300 leading-relaxed">{{ $offer ? 'Ensuite, vous choisissez l\'offre qui vous ressemble, seulement si PressLink vous convient. Rien n\'est prélevé automatiquement.' : 'Petit pressing, pressing qui grandit ou plusieurs établissements : choisissez l\'offre qui vous ressemble, et payez au mois ou à l\'année.' }}</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <x-site.trial-button tone="light" />
                    <a href="{{ route('site.pricing') }}" class="inline-flex items-center justify-center min-h-12 px-6 rounded-xl border border-white/30 font-semibold hover:bg-white/10 whitespace-nowrap">
                        Consulter nos offres
                    </a>
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-3" data-motion="cascade">
                @foreach ($plans as $item)
                    <a href="{{ route('site.pricing') }}" class="group rounded-2xl border p-6 transition-colors {{ $item['recommended'] ? 'border-blue-400/60 bg-white/10' : 'border-white/10 bg-white/5 hover:bg-white/10' }}">
                        <div class="flex items-baseline justify-between gap-3">
                            <h3 class="font-display text-xl font-bold">{{ $item['label'] }}</h3>
                            @if ($item['recommended'])<span class="text-[11px] font-semibold uppercase tracking-wide text-blue-300">Recommandée</span>@endif
                        </div>
                        <p class="text-sm text-blue-300 font-semibold mt-0.5">{{ $item['tagline'] }}</p>
                        <p class="mt-4">
                            @if ($item['monthly'] !== null)
                                <span class="font-display text-3xl font-extrabold tabular-nums">{{ \App\Support\PlanCatalog::fcfa($item['monthly']) }}</span>
                                <span class="text-sm text-slate-300">F / mois</span>
                            @else
                                <span class="font-display text-2xl font-extrabold">Sur devis</span>
                            @endif
                        </p>
                        <p class="text-sm text-slate-300 mt-1">{{ $item['ordersLimit'] === null ? 'Commandes illimitées' : \App\Support\PlanCatalog::fcfa($item['ordersLimit']).' commandes par mois' }}</p>
                        <span class="mt-4 inline-block text-sm font-semibold text-blue-300">Voir ce qu'elle comprend <span class="inline-block group-hover:translate-x-1 transition-transform">→</span></span>
                    </a>
                @endforeach
            </div>
            @if ($launchOffer && ! $offer)
                <p class="mt-6 inline-flex items-center gap-2 rounded-full bg-white/10 border border-white/20 px-4 py-2 text-sm font-semibold">
                    <span class="w-2 h-2 rounded-full bg-[#1DC8FF]" aria-hidden="true"></span>
                    Offre de lancement : {{ $launchOffer }}
                </p>
            @endif
        </div>
    </section>

    {{-- ===== Rejoindre en 3 étapes ===== --}}
    <section id="rejoindre" class="bg-(--color-bg) scroll-mt-20">
        <div class="max-w-6xl mx-auto px-5 py-16 md:py-20">
            <div class="max-w-2xl mb-10">
                <p class="text-xs font-semibold uppercase tracking-widest text-(--color-primary) mb-3">Inscrire votre pressing</p>
                <h2 class="font-display text-3xl md:text-4xl font-bold tracking-tight">Rejoindre PressLink en 3 étapes</h2>
            </div>
            <ol class="grid md:grid-cols-3 gap-5" data-motion="cascade">
                @foreach ([
                    ['Inscrivez votre pressing', 'En 2 minutes sur ce site : le nom de votre pressing, votre commune et votre numéro. Ou sur WhatsApp au '.$supportPhoneDisplay.'.'],
                    ['On vous aide à démarrer', 'Notre équipe vous appelle : vos tarifs, vos horaires et votre équipe. On vous montre tout.'],
                    [$offer ? 'Vous travaillez, gratuitement' : 'Vous démarrez', $offer ? 'Vous enregistrez vos premières commandes, sans rien payer jusqu\'au '.$until.'.' : 'Vous enregistrez vos premières commandes et vos clients sont prévenus.'],
                ] as $i => [$heading, $text])
                    <li class="relative bg-(--color-surface) border border-(--color-border) rounded-2xl p-6">
                        <div class="font-display text-5xl font-extrabold text-(--color-primary)/15 absolute top-4 right-5 select-none" aria-hidden="true">{{ $i + 1 }}</div>
                        <h3 class="font-display text-xl font-bold mb-2 pr-10">{{ $heading }}</h3>
                        <p class="text-(--color-text-secondary) leading-relaxed">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ===== Appel final ===== --}}
    <section class="max-w-6xl mx-auto px-5 py-16 md:py-20">
        <div class="relative overflow-hidden rounded-3xl bg-(--color-primary) text-white px-6 py-12 md:px-14 md:py-14 grid md:grid-cols-[1.3fr_1fr] gap-8 items-center">
            <div class="absolute -right-24 -top-24 w-80 h-80 rounded-full bg-white/10" aria-hidden="true"></div>
            <div class="relative">
                <h2 class="font-display text-3xl md:text-4xl font-extrabold tracking-tight mb-3">
                    {{ $offer ? 'Le lancement, c\'est maintenant.' : 'Prêt à simplifier votre pressing ?' }}
                </h2>
                <p class="text-lg text-[#DBEAFE]">
                    @if ($offer)
                        PressLink est gratuit jusqu'au <strong class="text-white">{{ $until }}</strong> : plus que {{ $daysLeft }} {{ $daysLeft > 1 ? 'jours' : 'jour' }} pour en profiter.
                    @else
                        Inscrivez-vous en 2 minutes : notre équipe vous appelle pour vous aider à démarrer.
                    @endif
                </p>
                <p class="mt-4 text-sm text-[#DBEAFE]">{{ $supportPhoneDisplay }} · {{ config('presslink.support.email') }}</p>
            </div>
            <div class="relative flex flex-col gap-3">
                <a href="{{ route('signup') }}" class="inline-flex items-center justify-center gap-2.5 min-h-13 py-2 px-6 rounded-xl bg-white text-(--color-primary-600) font-bold hover:bg-[#EFF6FF]">
                    {{ $offer ? 'Inscrire mon pressing gratuitement' : 'Inscrire mon pressing' }}
                </a>
                <a href="{{ route('sandbox.start') }}" class="inline-flex items-center justify-center h-13 px-6 rounded-xl border border-white/40 font-semibold hover:bg-white/10">Tester avec un pressing fictif</a>
                <a href="{{ $join }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2.5 h-13 px-6 rounded-xl border border-white/40 font-semibold hover:bg-white/10">
                    <img src="{{ asset('images/whatsapp.png') }}" alt="" width="22" height="22" class="w-5.5 h-5.5 rounded-full">
                    Être accompagné sur WhatsApp
                </a>
                <a href="tel:{{ $supportPhoneE164 }}" class="inline-flex items-center justify-center h-13 px-6 rounded-xl border border-white/40 font-semibold hover:bg-white/10">Appeler le {{ $supportPhoneDisplay }}</a>
            </div>
        </div>
    </section>
</x-site.page>
