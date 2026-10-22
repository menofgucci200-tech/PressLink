<div class="max-w-3xl">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold mb-1">Support</h1>
        <p class="text-sm text-(--color-text-muted)">Une question, un problème ? L'équipe PressLink vous répond.</p>
    </div>

    <div class="bg-(--color-primary-tint) border border-(--color-primary)/15 rounded-2xl p-6 sm:p-8 text-center mb-6">
        <div class="w-14 h-14 mx-auto rounded-full bg-(--color-primary) text-white flex items-center justify-center mb-4">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"></path></svg>
        </div>
        <p class="text-(--color-text-secondary) mb-2">Appelez ou écrivez-nous sur WhatsApp, au même numéro :</p>
        <p class="font-display text-3xl sm:text-4xl font-extrabold text-(--color-primary-600) dark:text-(--color-primary) tabular-nums mb-6" data-testid="support-phone">{{ $phoneDisplay }}</p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="tel:{{ $phoneE164 }}" class="inline-flex items-center justify-center gap-2 h-12 px-6 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">
                <img src="{{ asset('images/phone.png') }}" alt="" width="26" height="26" class="rounded-full ring-2 ring-white">
                Appeler le support
            </a>
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 h-12 px-6 rounded-xl bg-(--color-whatsapp) text-white font-semibold hover:brightness-95">
                <img src="{{ asset('images/whatsapp.png') }}" alt="" width="26" height="26" class="rounded-full ring-2 ring-white">
                Écrire sur WhatsApp
            </a>
            <a href="mailto:{{ config('presslink.support.email') }}" class="inline-flex items-center justify-center gap-2 h-12 px-6 rounded-xl border border-(--color-border) bg-(--color-surface) font-semibold hover:border-(--color-primary)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                {{ config('presslink.support.email') }}
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
