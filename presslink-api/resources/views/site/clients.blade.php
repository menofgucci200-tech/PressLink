<x-site.page
    title="Application de suivi de pressing gratuite — PressLink"
    description="Suivez vos commandes de pressing en temps réel, recevez une notification quand vos vêtements sont prêts et retrouvez tout votre historique. Gratuit."
    path="/pour-les-clients">
    <section class="bg-(--color-bg) overflow-hidden">
        <div class="max-w-6xl mx-auto px-5 pt-12 md:pt-16 grid md:grid-cols-2 gap-10 items-center">
            <div class="md:pb-16">
                <p class="text-xs font-semibold uppercase tracking-widest text-(--color-primary) mb-4">Pour les clients</p>
                <h1 class="font-display text-4xl md:text-5xl font-extrabold leading-[1.08] tracking-tight mb-5">Suivre sa commande de pressing, simplement</h1>
                <p class="text-lg text-(--color-text-secondary) leading-relaxed mb-8">Plus besoin d'appeler ou de passer au pressing « pour voir si c'est prêt ». Vous êtes informé en temps réel, gratuitement.</p>
                <a href="{{ $appUrl }}" class="inline-flex items-center justify-center h-12 px-6 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">Ouvrir l'application</a>
            </div>
            <div class="phone-mock phone-mock--hands max-w-md mx-auto w-full self-end">
                <div class="phone-mock__screen"><x-site.app-screen /></div>
                <img src="{{ asset('images/hands-phone.webp') }}" width="736" height="648" alt="Suivi d'une commande de pressing dans l'application PressLink" fetchpriority="high">
            </div>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-5 py-16 md:py-20">
        <h2 class="font-display text-3xl font-bold tracking-tight mb-8">En trois étapes</h2>
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

        <h2 class="font-display text-3xl font-bold tracking-tight mt-16 mb-8">Tout ce que vous pouvez faire</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
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

        <div class="mt-12 flex flex-wrap gap-4">
            <a href="{{ route('site.how') }}" class="font-semibold text-(--color-primary) hover:underline">Voir le parcours en vidéo →</a>
            <a href="{{ route('site.install') }}" class="font-semibold text-(--color-primary) hover:underline">Installer l'application →</a>
        </div>
    </section>
</x-site.page>
