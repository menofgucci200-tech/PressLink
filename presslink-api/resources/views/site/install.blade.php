<x-site.page
    title="Installer l'application PressLink sur Android et iPhone"
    description="Installez PressLink sur votre téléphone en un geste, sans passer par un store : la marche à suivre en images pour Android (Chrome) et iPhone (Safari)."
    path="/installer">
    <section class="bg-(--color-bg)">
        <div class="max-w-6xl mx-auto px-5 py-14 md:py-20">
            <div class="max-w-2xl mb-12">
                <h1 class="font-display text-4xl md:text-5xl font-extrabold tracking-tight mb-4">Installer PressLink sur votre téléphone</h1>
                <p class="text-(--color-text-secondary) text-lg">Pas de store, pas de téléchargement lourd : ouvrez <a href="{{ $appUrl }}" class="font-semibold text-(--color-primary) hover:underline">presslink.org/app</a> et ajoutez l'application à votre écran d'accueil.</p>
            </div>

            <div class="grid lg:grid-cols-2 gap-8" data-motion="install">
                <section class="bg-(--color-surface) border border-(--color-border) rounded-3xl p-6 md:p-8" aria-labelledby="android">
                    <h2 id="android" class="font-display text-2xl font-bold mb-6">Sur Android <span class="text-(--color-text-muted) font-semibold text-base">· Chrome</span></h2>
                    <x-site.install-demo os="android" />
                </section>
                <section class="bg-(--color-surface) border border-(--color-border) rounded-3xl p-6 md:p-8" aria-labelledby="iphone">
                    <h2 id="iphone" class="font-display text-2xl font-bold mb-6">Sur iPhone <span class="text-(--color-text-muted) font-semibold text-base">· Safari</span></h2>
                    <x-site.install-demo os="ios" />
                </section>
            </div>

            <div class="mt-12 grid md:grid-cols-[auto_1fr] gap-8 items-center bg-(--color-surface) border border-(--color-border) rounded-3xl p-6 md:p-8">
                <img src="{{ asset('qr-presslink-app.png') }}" width="180" height="180" alt="Code QR vers presslink.org/app" class="rounded-xl mx-auto" loading="lazy">
                <div>
                    <h2 class="font-display text-xl font-bold mb-2">Vous êtes sur ordinateur ?</h2>
                    <p class="text-(--color-text-secondary) leading-relaxed">Scannez ce code avec l'appareil photo de votre téléphone pour ouvrir l'application. Le bouton « Installer » n'apparaît pas ? Sur Android, ouvrez le menu ⋮ de Chrome puis « Installer l'application ».</p>
                </div>
            </div>
        </div>
    </section>
</x-site.page>
