@php
    $steps = [
        ['Ouvrez l\'application', 'Scannez le code QR ou ouvrez presslink.org/app, puis installez-la sur votre écran d\'accueil.'],
        ['Inscrivez-vous', 'Votre numéro de téléphone, votre prénom, votre nom et un mot de passe : c\'est tout.'],
        ['Rejoignez votre pressing', 'Saisissez le code de votre pressing, par exemple PE-4821. Une seule fois.'],
        ['Déposez vos vêtements', 'Le pressing enregistre la commande : elle apparaît aussitôt dans l\'application, avec les articles et le total.'],
        ['Suivez l\'avancement', 'Reçue, en traitement, prête : l\'état de la commande se met à jour en temps réel.'],
        ['Soyez prévenu', 'Une notification vous indique que votre commande est prête.'],
        ['Récupérez', 'Passez au pressing au bon moment. La commande est marquée récupérée et reste dans votre historique.'],
    ];
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'Suivre sa commande de pressing avec PressLink',
        'video' => [
            '@type' => 'VideoObject',
            'name' => 'PressLink : du dépôt à la récupération',
            'description' => 'Le parcours client PressLink en vidéo : inscription, code du pressing, suivi, notification et récupération.',
            'thumbnailUrl' => asset('video/presslink-parcours.jpg'),
            'contentUrl' => asset('video/presslink-parcours.mp4'),
            'uploadDate' => '2026-10-13',
        ],
        'step' => collect($steps)->map(fn ($step, $i) => [
            '@type' => 'HowToStep',
            'position' => $i + 1,
            'name' => $step[0],
            'text' => $step[1],
        ])->all(),
    ];
@endphp
<x-site.page
    title="Comment ça marche — le parcours client PressLink"
    description="De l'inscription à la récupération de vos vêtements : découvrez en vidéo comment suivre votre commande de pressing avec PressLink."
    path="/comment-ca-marche"
    :json-ld="$jsonLd">
    <section class="bg-(--color-bg)">
        <div class="max-w-5xl mx-auto px-5 py-14 md:py-20">
            <h1 class="font-display text-4xl md:text-5xl font-extrabold tracking-tight mb-4 max-w-2xl">Comment ça marche</h1>
            <p class="text-(--color-text-secondary) text-lg mb-10 max-w-2xl">De l'inscription à la récupération de vos vêtements, voici tout le parcours PressLink.</p>
            <video class="w-full rounded-3xl border border-(--color-border) shadow-lg bg-white" controls playsinline preload="metadata"
                   poster="{{ asset('video/presslink-parcours.jpg') }}" width="1280" height="720">
                <source src="{{ asset('video/presslink-parcours.mp4') }}" type="video/mp4">
                Votre navigateur ne lit pas cette vidéo.
            </video>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-5 py-16 md:py-20 grid lg:grid-cols-[1fr_22rem] gap-12 items-start">
        <ol class="relative border-l-2 border-(--color-border) ml-4 space-y-8">
            @foreach ($steps as [$heading, $text])
                <li class="pl-8 relative">
                    <span class="absolute -left-[1.15rem] top-0 w-9 h-9 rounded-full bg-(--color-primary) text-white font-bold flex items-center justify-center ring-4 ring-white">{{ $loop->iteration }}</span>
                    <h2 class="font-display text-xl font-bold mb-1">{{ $heading }}</h2>
                    <p class="text-(--color-text-secondary) leading-relaxed">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
        <div class="lg:sticky lg:top-24">
            <div class="phone-mock phone-mock--hands">
                <div class="phone-mock__screen"><x-site.app-screen /></div>
                <img src="{{ asset('images/hands-phone.webp') }}" width="736" height="648" alt="Notification de commande prête dans PressLink" loading="lazy">
            </div>
            <a href="{{ $appUrl }}" class="mt-6 flex items-center justify-center h-12 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">Commencer maintenant</a>
            <a href="{{ route('site.install') }}" class="mt-3 block text-center font-semibold text-(--color-primary) hover:underline">Comment l'installer ?</a>
        </div>
    </section>
</x-site.page>
