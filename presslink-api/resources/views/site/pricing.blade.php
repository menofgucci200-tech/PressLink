@php
    $whatsapp = 'https://wa.me/'.ltrim((string) config('presslink.support.phone_e164'), '+').'?text=';
    $fcfa = fn (?int $amount) => \App\Support\PlanCatalog::fcfa($amount);
    $questions = [
        ['Comment payer ?', \App\Support\GeniusPay::isConfigured()
            ? 'Choisissez votre formule depuis votre espace et payez en ligne, au mois ou à l\'année : Wave, Orange Money, MTN, Moov ou carte bancaire. Les frais de l\'opérateur s\'ajoutent au prix et vous sont indiqués avant de payer. Votre formule est activée dès le paiement confirmé.'
            : 'Choisissez votre formule : nous vous envoyons un lien de paiement Wave du montant de la formule, au mois ou à l\'année. Votre espace est activé dès le paiement reçu.'],
        ['Puis-je essayer avant de payer ?', "Oui : l'essai gratuit dure {$trialDays} jours, avec toutes les fonctions ouvertes. Ensuite, vous gardez les fonctions de la formule choisie."],
        ['Puis-je changer de formule ?', 'Oui, à tout moment, depuis la page Abonnement de votre espace pressing : vous y voyez ce que vous gagnez à passer à la formule supérieure.'],
        ['Une seule fonction me manque, dois-je changer de formule ?', 'Non : chaque fonction peut aussi être ajoutée seule, en option, à votre formule.'],
        ['Et si j\'ai plus de commandes que prévu ce mois-ci ?', 'Vous pouvez ajouter des commandes pour le mois en cours depuis votre espace, sans changer de formule.'],
        ['L\'application est-elle payante pour mes clients ?', 'Non, l\'application PressLink est gratuite pour les clients des pressings.'],
    ];
    // Matériel, coupures d'Internet, engagement : les mêmes réponses que sur la page Pour les pressings.
    foreach (array_slice(\App\Http\Controllers\LandingController::pressingFaqs(), 1, 3) as $faq) {
        $questions[] = [$faq['question'], $faq['answer']];
    }
@endphp
<x-site.page
    title="Nos offres — PressLink, logiciel de gestion de pressing"
    description="Starter, Business, Premium : les offres PressLink pour gérer votre pressing, leurs prix au mois ou à l'année et ce que chacune comprend. Essai gratuit."
    path="/offres"
    :json-ld="\App\Support\SiteContent::softwareJsonLd(route('site.pricing'))">
    <section class="bg-slate-900 text-white dark:border-b dark:border-(--color-border)">
        <div class="max-w-6xl mx-auto px-5 pt-16 pb-28 md:pt-20 md:pb-32 text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-blue-300 mb-3">Nos offres</p>
            <h1 class="font-display text-4xl md:text-5xl font-extrabold leading-[1.08] tracking-tight mb-5">Une offre pour chaque pressing</h1>
            <p class="text-slate-300 text-lg leading-relaxed max-w-2xl mx-auto">
                Essai gratuit de {{ $trialDays }} jours, toutes les fonctions ouvertes. Ensuite, payez au mois ou à l'année par Wave, sans engagement.
            </p>
            <x-site.trial-button tone="light" class="mt-7" />
            @if ($launchOffer)
                <p class="mt-6 inline-flex items-center gap-2 rounded-full bg-white/10 border border-white/20 px-4 py-2 text-sm font-semibold">
                    <span class="w-2 h-2 rounded-full bg-[#1DC8FF]" aria-hidden="true"></span>
                    Offre de lancement : {{ $launchOffer }}
                </p>
            @endif
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-5 -mt-20 md:-mt-24 pb-16">
        <h2 class="sr-only">Nos trois offres</h2>
        <x-plans.cards :plans="$plans" mode="site" data-motion="cascade" />
    </section>

    @if ($options->isNotEmpty())
        <section class="max-w-6xl mx-auto px-5 pb-16">
            <h2 class="font-display text-2xl font-bold tracking-tight mb-2">Une fonction vous manque ?</h2>
            <p class="text-(--color-text-secondary) mb-6">Ajoutez-la seule à votre formule, sans en changer.</p>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" data-motion="cascade">
                @foreach ($options as $feature)
                    <div class="rounded-2xl border border-(--color-border) bg-(--color-surface) p-5">
                        <div class="flex items-baseline justify-between gap-3">
                            <h3 class="font-semibold">{{ $feature->label() }}</h3>
                            <span class="text-sm font-semibold tabular-nums whitespace-nowrap">{{ $fcfa($feature->priceFcfa()) }} F / mois</span>
                        </div>
                        <p class="text-sm text-(--color-text-secondary) mt-1">{{ $feature->description() }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="max-w-6xl mx-auto px-5 pb-16">
        <x-plans.compare :plans="$plans" />
    </section>

    <section class="max-w-6xl mx-auto px-5 pb-16">
        <h2 class="font-display text-2xl font-bold tracking-tight mb-6">Se lancer sans risque</h2>
        <x-site.reassurance />
    </section>

    <x-site.testimonials class="pt-0" />

    <section class="bg-(--color-bg) border-y border-(--color-border)">
        <div class="max-w-3xl mx-auto px-5 py-16">
            <h2 class="font-display text-2xl font-bold tracking-tight mb-6">Questions sur nos offres</h2>
            <div class="space-y-3" data-motion="cascade">
                @foreach ($questions as [$question, $answer])
                    <details class="group bg-(--color-surface) border border-(--color-border) rounded-xl" @if ($loop->first) open @endif>
                        <summary class="flex items-center justify-between gap-4 cursor-pointer list-none px-5 py-4 font-semibold">
                            {{ $question }}
                            <svg class="w-5 h-5 flex-none text-(--color-text-muted) transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <p class="px-5 pb-5 text-(--color-text-secondary) leading-relaxed">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-5 py-16 text-center">
        <h2 class="font-display text-3xl font-bold tracking-tight mb-3">Une question avant de choisir ?</h2>
        <p class="text-(--color-text-secondary) mb-6">Écrivez-nous : nous vous aidons à choisir l'offre adaptée à votre pressing. Ou commencez par l'essai gratuit.</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <x-site.trial-button />
        <a href="{{ $whatsapp.rawurlencode('Bonjour, je voudrais de l\'aide pour choisir une formule PressLink pour mon pressing.') }}" target="_blank" rel="noopener"
           class="inline-flex items-center justify-center gap-3 h-12 pl-2 pr-6 rounded-xl bg-(--color-whatsapp) text-white font-semibold hover:brightness-95">
            <img src="{{ asset('images/whatsapp.png') }}" alt="" class="w-8 h-8 rounded-full ring-2 ring-white" width="32" height="32">
            Nous écrire sur WhatsApp
        </a>
        </div>
    </section>
</x-site.page>
