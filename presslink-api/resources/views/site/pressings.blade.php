<x-site.page
    title="Logiciel de gestion de pressing en Côte d'Ivoire — PressLink"
    description="Commandes, clients, services et tarifs, équipe, exports et notifications automatiques : le logiciel de gestion de pressing simple et complet."
    path="/pour-les-pressings">
    <section class="bg-slate-900 text-white dark:border-b dark:border-(--color-border)">
        <div class="max-w-6xl mx-auto px-5 py-16 md:py-24 grid md:grid-cols-2 gap-12 items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-blue-300 mb-3">Pour les pressings</p>
                <h1 class="font-display text-4xl md:text-5xl font-extrabold leading-[1.08] tracking-tight mb-5">Un logiciel de gestion de pressing simple et complet</h1>
                <p class="text-slate-300 text-lg leading-relaxed mb-8">
                    Gérez vos commandes, vos clients et votre équipe depuis un seul tableau de bord, et tenez vos clients informés automatiquement.
                </p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="https://wa.me/{{ ltrim(config('presslink.support.phone_e164'), '+') }}?text={{ rawurlencode('Bonjour, je souhaite en savoir plus sur PressLink pour mon pressing.') }}" target="_blank" rel="noopener"
                       class="inline-flex items-center justify-center gap-3 h-12 pl-2 pr-6 rounded-xl bg-[#25D366] text-white font-semibold hover:brightness-95">
                        <img src="{{ asset('images/whatsapp.png') }}" alt="" class="w-8 h-8 rounded-full ring-2 ring-white" width="32" height="32">
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
                    'Services (repassage, nettoyage…) et prix de chaque pièce, avec variantes',
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

    <section class="max-w-6xl mx-auto px-5 py-16 md:py-20">
        <h2 class="font-display text-3xl font-bold tracking-tight mb-8">Comment ça se passe au comptoir</h2>
        <ol class="grid md:grid-cols-4 gap-5">
            @foreach ([
                ['Le client dépose', 'Vous créez la commande en quelques secondes : client, service (repassage, nettoyage…), pièces, total.'],
                ['Vous traitez', 'Passez la commande « en traitement » : le client suit l\'avancement depuis son téléphone.'],
                ['C\'est prêt', 'Marquez-la « prête » : le client reçoit automatiquement une notification.'],
                ['Il récupère', 'Marquez-la « récupérée » : votre historique et vos chiffres sont à jour.'],
            ] as $i => [$heading, $text])
                <li class="border border-(--color-border) rounded-2xl p-6">
                    <div class="text-sm font-bold text-(--color-primary) mb-2">Étape {{ $i + 1 }}</div>
                    <h3 class="font-display text-lg font-bold mb-2">{{ $heading }}</h3>
                    <p class="text-sm text-(--color-text-secondary) leading-relaxed">{{ $text }}</p>
                </li>
            @endforeach
        </ol>

        <div class="mt-14 bg-(--color-primary-tint) border border-(--color-primary)/15 rounded-3xl px-6 py-10 md:px-12 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h2 class="font-display text-2xl font-bold mb-1">Équipez votre pressing</h2>
                <p class="text-(--color-text-secondary)">Démonstration et mise en place avec l'équipe PressLink.</p>
            </div>
            <x-site.contact-buttons message="Bonjour, je souhaite une démonstration de PressLink pour mon pressing." />
        </div>
    </section>
</x-site.page>
