@php
    $pressingQuestions = \App\Http\Controllers\LandingController::pressingFaqs();
@endphp
<x-site.page
    title="Logiciel de gestion de pressing à Abidjan — PressLink"
    description="Logiciel de gestion de pressing pour la Côte d'Ivoire : commandes, clients, Wave et Orange Money, caisse, équipe, mode hors connexion. Essai gratuit."
    keywords="logiciel pressing Abidjan, logiciel de gestion de pressing, logiciel pressing Côte d'Ivoire, gestion pressing Wave, caisse pressing, application pressing"
    path="/pour-les-pressings"
    :json-ld="\App\Support\SiteContent::softwareJsonLd(route('site.pressings'))">
    <section class="bg-slate-900 text-white dark:border-b dark:border-(--color-border)">
        <div class="max-w-6xl mx-auto px-5 py-16 md:py-24 grid md:grid-cols-2 gap-12 items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-blue-300 mb-3">Pour les pressings</p>
                <h1 class="font-display text-4xl md:text-5xl font-extrabold leading-[1.08] tracking-tight mb-5">Le logiciel de gestion de pressing pensé pour la Côte d'Ivoire</h1>
                <p class="text-slate-300 text-lg leading-relaxed mb-8">
                    À Abidjan comme partout dans le pays : vos commandes, vos clients, votre caisse et votre équipe dans un seul outil. Vos clients sont prévenus automatiquement quand leurs vêtements sont prêts.
                </p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <x-site.trial-button />
                    <a href="{{ route('site.pricing') }}" class="inline-flex items-center justify-center min-h-12 px-6 rounded-xl border border-white/30 font-semibold hover:bg-white/10">
                        Consulter nos offres
                    </a>
                </div>
                <p class="mt-4 text-sm text-slate-300">Pas encore décidé ? <a href="{{ route('sandbox.start') }}" class="font-semibold text-white underline underline-offset-2 hover:no-underline">Tester avec un pressing fictif</a>, sans inscription.</p>
            </div>
            <ul class="grid sm:grid-cols-2 gap-3" data-motion="cascade">
                @foreach (\App\Support\PlanCatalog::ESSENTIALS as $feature)
                    <li class="flex items-start gap-3 bg-white/5 border border-white/10 rounded-xl p-4 text-sm leading-snug">
                        <svg class="w-5 h-5 flex-none text-blue-300 mt-px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        {{ $feature }}
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-5 py-16 md:py-20">
        <div class="grid lg:grid-cols-[1.3fr_1fr] gap-10 items-center">
            <figure>
                <video class="w-full rounded-3xl border border-(--color-border) shadow-sm bg-(--color-bg)" controls playsinline preload="none"
                       poster="{{ asset('video/presslink-presentation.jpg') }}" width="1280" height="720">
                    <source src="{{ asset('video/presslink-presentation.mp4') }}" type="video/mp4">
                </video>
                <figcaption class="mt-3 font-semibold">Découvrir PressLink : l'application et le logiciel de gestion <span class="font-normal text-(--color-text-muted)">· 1 min 22</span></figcaption>
            </figure>
            <div>
                <h2 class="font-display text-3xl font-bold tracking-tight mb-4">Voyez-le avant de vous lancer</h2>
                <p class="text-(--color-text-secondary) leading-relaxed mb-4">
                    Filmé dans le tableau de bord du pressing et dans l'application de vos clients : la création d'une commande au comptoir, l'encaissement, puis la notification « prête » reçue par le client.
                </p>
                <p class="text-(--color-text-secondary) leading-relaxed">
                    Vos clients n'ont rien à télécharger depuis un store : ils ouvrent <span class="font-semibold">presslink.org/app</span> et suivent leurs commandes gratuitement.
                </p>
            </div>
        </div>
    </section>

    <section class="bg-(--color-bg) border-y border-(--color-border)">
        <div class="max-w-6xl mx-auto px-5 py-16 md:py-20">
            <h2 class="font-display text-3xl font-bold tracking-tight mb-8">Comment ça se passe au comptoir</h2>
            <ol class="grid md:grid-cols-4 gap-5" data-motion="cascade">
                @foreach ([
                    ['Le client dépose', 'Vous créez la commande en quelques secondes : client, service (repassage, nettoyage…), pièces, total. Il peut payer tout de suite, verser un acompte ou payer au retrait.'],
                    ['Vous traitez', 'Passez la commande « en traitement » : le client suit l\'avancement depuis son téléphone.'],
                    ['C\'est prêt', 'Marquez-la « prête » : le client reçoit automatiquement une notification, avec le reste à payer.'],
                    ['Il récupère', 'Encaissez le solde (espèces, Wave, Orange Money…) et remettez la commande : votre caisse et vos chiffres sont à jour.'],
                ] as $i => [$heading, $text])
                    <li class="border border-(--color-border) bg-(--color-surface) rounded-2xl p-6">
                        <div class="text-sm font-bold text-(--color-primary) mb-2">Étape {{ $i + 1 }}</div>
                        <h3 class="font-display text-lg font-bold mb-2">{{ $heading }}</h3>
                        <p class="text-sm text-(--color-text-secondary) leading-relaxed">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-5 py-16 md:py-20">
        <div class="max-w-2xl mb-8">
            <h2 class="font-display text-3xl font-bold tracking-tight mb-3">Pour aller plus loin, selon votre offre</h2>
            <p class="text-(--color-text-secondary)">L'essentiel est dans toutes les offres. Ces fonctions s'ajoutent avec Business ou Premium, ou une à une en option. Pendant l'essai gratuit, tout est ouvert.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" data-motion="cascade">
            @foreach (\App\Enums\Feature::cases() as $feature)
                <div class="rounded-2xl border border-(--color-border) bg-(--color-surface) p-5">
                    <div class="flex items-baseline justify-between gap-3 mb-1">
                        <h3 class="font-semibold">{{ $feature->label() }}</h3>
                        @if ($from = $feature->includedFrom())
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-(--color-primary) whitespace-nowrap">dès {{ $from->label() }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-(--color-text-secondary) leading-relaxed">{{ $feature->description() }}</p>
                </div>
            @endforeach
        </div>
        <a href="{{ route('site.pricing') }}" class="mt-6 inline-block font-semibold text-(--color-primary) hover:underline">Comparer les offres et les prix →</a>
    </section>

    <section class="bg-slate-900 text-white dark:border-y dark:border-(--color-border)">
        <div class="max-w-6xl mx-auto px-5 py-16">
            <h2 class="font-display text-3xl font-bold tracking-tight mb-8">Se lancer sans risque</h2>
            <x-site.reassurance dark />
        </div>
    </section>

    <x-site.testimonials />

    <section class="bg-(--color-bg) border-y border-(--color-border)">
        <div class="max-w-3xl mx-auto px-5 py-16">
            <h2 class="font-display text-2xl font-bold tracking-tight mb-6">Les questions des gérants</h2>
            <div class="space-y-3" data-motion="cascade">
                @foreach ($pressingQuestions as $faq)
                    <details class="group bg-(--color-surface) border border-(--color-border) rounded-xl" @if ($loop->first) open @endif>
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

    <section class="max-w-6xl mx-auto px-5 py-16">
        <div class="bg-(--color-primary-tint) border border-(--color-primary)/15 rounded-3xl px-6 py-10 md:px-12 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h2 class="font-display text-2xl font-bold mb-1">Équipez votre pressing</h2>
                <p class="text-(--color-text-secondary)">{{ \App\Support\LaunchOffer::isActive() ? 'Gratuit jusqu\'au '.\App\Support\LaunchOffer::dateLabel().' pendant le lancement officiel' : 'Essai gratuit de '.\App\Support\PlanCatalog::trialDays().' jours' }}, toutes les fonctions ouvertes. <a href="{{ route('site.pricing') }}" class="font-semibold text-(--color-primary) hover:underline">Trouvez l'offre faite pour vous →</a></p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <x-site.trial-button />
                <x-site.contact-buttons message="Bonjour, je souhaite une démonstration de PressLink pour mon pressing." />
            </div>
        </div>
    </section>
</x-site.page>
