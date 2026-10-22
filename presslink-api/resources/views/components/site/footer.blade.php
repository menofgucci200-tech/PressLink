<footer class="border-t border-(--color-border)">
    <div class="max-w-6xl mx-auto px-5 py-10 grid gap-8 md:grid-cols-[1.4fr_1fr_1fr] text-sm">
        <div>
            <p class="font-display text-lg font-bold">Press<span class="text-(--color-primary)">Link</span></p>
            <p class="mt-2 text-(--color-text-muted)">Le lien entre votre pressing et vos clients.</p>
            <x-site.contact-buttons class="mt-4" size="sm" />
            <a href="mailto:{{ config('presslink.support.email') }}" class="mt-3 inline-block font-semibold text-(--color-text-secondary) hover:text-(--color-primary)">{{ config('presslink.support.email') }}</a>
        </div>
        <nav class="flex flex-col gap-2 text-(--color-text-secondary)" aria-label="Pied de page">
            <a href="{{ route('site.clients') }}" class="hover:text-(--color-primary)">Pour les clients</a>
            <a href="{{ route('site.pressings') }}" class="hover:text-(--color-primary)">Pour les pressings</a>
            <a href="{{ route('site.pricing') }}" class="hover:text-(--color-primary)">Nos offres</a>
            <a href="{{ route('site.how') }}" class="hover:text-(--color-primary)">Comment ça marche</a>
        </nav>
        <nav class="flex flex-col gap-2 text-(--color-text-secondary)" aria-label="Liens utiles">
            <a href="{{ route('site.install') }}" class="hover:text-(--color-primary)">Installer l'application</a>
            <a href="{{ route('site.faq') }}" class="hover:text-(--color-primary)">Questions fréquentes</a>
            <a href="{{ route('site.contact') }}" class="hover:text-(--color-primary)">Contact & support</a>
            <a href="{{ route('login') }}" class="hover:text-(--color-primary)">Espace pressing</a>
        </nav>
    </div>
    <div class="border-t border-(--color-border) pt-5 pb-24 sm:pb-5 px-5 flex flex-wrap justify-center gap-x-5 gap-y-2 text-xs text-(--color-text-muted)">
        <span>© {{ date('Y') }} PressLink · Côte d'Ivoire</span>
        <a href="{{ route('site.legal') }}" class="hover:text-(--color-primary)">Mentions légales</a>
        <a href="{{ route('site.terms') }}" class="hover:text-(--color-primary)">CGU</a>
        <a href="{{ route('site.privacy') }}" class="hover:text-(--color-primary)">Confidentialité</a>
    </div>
</footer>
