<div class="max-w-3xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Support</h1>
        <p class="text-sm text-(--color-text-muted)">Une question, un problème ? L'équipe PressLink vous répond.</p>
    </div>

    <div class="bg-(--color-primary-tint) border border-blue-100 rounded-2xl p-6 sm:p-8 text-center mb-6">
        <div class="w-14 h-14 mx-auto rounded-full bg-(--color-primary) text-white flex items-center justify-center mb-4">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"></path></svg>
        </div>
        <p class="text-(--color-text-secondary) mb-2">Appelez ou écrivez-nous sur WhatsApp, au même numéro :</p>
        <p class="font-display text-3xl sm:text-4xl font-extrabold text-(--color-primary-600) tabular-nums mb-6" data-testid="support-phone">{{ $phoneDisplay }}</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="tel:{{ $phoneE164 }}" class="inline-flex items-center justify-center gap-2 h-12 px-6 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92Z"></path></svg>
                Appeler le support
            </a>
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 h-12 px-6 rounded-xl bg-[#25D366] text-white font-semibold hover:brightness-95">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"></path></svg>
                Écrire sur WhatsApp
            </a>
        </div>
    </div>

    <div class="flex items-start gap-3 bg-(--color-warning-tint) text-(--color-warning-text) rounded-xl p-4 text-sm mb-8">
        <svg class="flex-none mt-0.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"></path><path d="M10 22h4"></path><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V17h6v-.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2Z"></path></svg>
        <p>Pour aller plus vite, indiquez le nom de votre pressing et, s'il y en a un, le numéro de la commande concernée (ex. PL-123456).</p>
    </div>

    <h2 class="font-display text-lg font-bold mb-3">Questions fréquentes</h2>
    <div class="space-y-3">
        @foreach ($faqs as $faq)
            <details class="group bg-(--color-surface) border border-(--color-border) rounded-xl">
                <summary class="flex items-center justify-between gap-4 cursor-pointer list-none px-5 py-4 text-sm font-semibold">
                    {{ $faq['question'] }}
                    <svg class="w-5 h-5 flex-none text-(--color-text-muted) transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                </summary>
                <p class="px-5 pb-5 text-sm text-(--color-text-secondary) leading-relaxed">{{ $faq['answer'] }}</p>
            </details>
        @endforeach
    </div>
</div>
