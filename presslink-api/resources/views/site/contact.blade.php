<x-site.page
    title="Contact et support — PressLink"
    description="Une question ou un problème avec PressLink ? Appelez-nous ou écrivez-nous sur WhatsApp : l'équipe vous répond rapidement."
    path="/contact">
    <section class="max-w-4xl mx-auto px-5 py-16 md:py-24">
        <div class="bg-(--color-primary-tint) border border-blue-100 rounded-3xl px-6 py-12 md:px-12 text-center">
            <h1 class="font-display text-4xl font-extrabold tracking-tight mb-3">Une question ? Un problème ?</h1>
            <p class="text-(--color-text-secondary) text-lg mb-6">L'équipe PressLink vous répond par appel ou sur WhatsApp.</p>
            <p class="font-display text-3xl md:text-4xl font-extrabold text-(--color-primary-600) mb-8">{{ $supportPhoneDisplay }}</p>
            <x-site.contact-buttons class="justify-center" />
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-2">
            <div class="border border-(--color-border) rounded-2xl p-6">
                <h2 class="font-display text-lg font-bold mb-2">Vous êtes client d'un pressing</h2>
                <p class="text-(--color-text-secondary) leading-relaxed">Indiquez votre numéro de commande (ex. PL-123456) et le pressing concerné : nous vous répondrons plus vite. Pensez aussi à « Aide & support » dans le menu de l'application.</p>
            </div>
            <div class="border border-(--color-border) rounded-2xl p-6">
                <h2 class="font-display text-lg font-bold mb-2">Vous êtes un pressing</h2>
                <p class="text-(--color-text-secondary) leading-relaxed">Démonstration, mise en place, abonnement ou question sur le tableau de bord : écrivez-nous, nous organisons un échange.</p>
            </div>
        </div>
    </section>
</x-site.page>
