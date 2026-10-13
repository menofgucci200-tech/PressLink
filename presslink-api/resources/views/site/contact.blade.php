@php
    $field = 'w-full h-12 px-4 rounded-xl border border-(--color-border) bg-white text-sm outline-none focus:border-(--color-primary) focus:ring-4 focus:ring-blue-500/10';
@endphp
<x-site.page
    title="Contact et support — PressLink"
    description="Une question ou un problème avec PressLink ? Appelez-nous, écrivez-nous sur WhatsApp ou par email : l'équipe vous répond rapidement."
    path="/contact">
    <section class="max-w-6xl mx-auto px-5 py-14 md:py-20">
        <div class="max-w-2xl mb-10">
            <h1 class="font-display text-4xl md:text-5xl font-extrabold tracking-tight mb-3">Une question ? Un problème ?</h1>
            <p class="text-(--color-text-secondary) text-lg">L'équipe PressLink vous répond par appel, sur WhatsApp ou par email.</p>
        </div>

        <div class="grid lg:grid-cols-[1fr_1.2fr] gap-8 items-start">
            {{-- Moyens de contact --}}
            <div class="space-y-4">
                <div class="bg-(--color-primary-tint) border border-blue-100 rounded-3xl p-6">
                    <p class="text-sm font-semibold text-(--color-text-secondary) mb-1">Téléphone et WhatsApp</p>
                    <p class="font-display text-3xl font-extrabold text-(--color-primary-600) mb-5">{{ $supportPhoneDisplay }}</p>
                    <x-site.contact-buttons />
                </div>
                <a href="mailto:{{ $supportEmail }}" class="flex items-center gap-4 bg-white border border-(--color-border) rounded-3xl p-6 hover:border-(--color-primary)">
                    <span class="w-12 h-12 flex-none rounded-2xl bg-(--color-primary) text-white flex items-center justify-center">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-(--color-text-secondary)">Email</span>
                        <span class="block font-display text-xl font-bold">{{ $supportEmail }}</span>
                    </span>
                </a>
                <div class="border border-(--color-border) rounded-3xl p-6 text-sm text-(--color-text-secondary) leading-relaxed">
                    <p class="mb-2"><strong class="text-(--color-text-primary)">Client d'un pressing :</strong> indiquez votre numéro de commande (ex. PL-123456) et le pressing concerné.</p>
                    <p><strong class="text-(--color-text-primary)">Pressing :</strong> démonstration, mise en place, abonnement ou question sur le tableau de bord, nous organisons un échange.</p>
                </div>
            </div>

            {{-- Formulaire --}}
            <div class="bg-white border border-(--color-border) rounded-3xl p-6 md:p-8 shadow-sm">
                <h2 class="font-display text-2xl font-bold mb-1">Envoyez-nous un message</h2>
                <p class="text-sm text-(--color-text-secondary) mb-6">Nous vous répondons par email ou par téléphone.</p>

                @if (session('contact_sent'))
                    <div class="rounded-2xl bg-(--color-success-tint) border border-emerald-200 p-5 text-(--color-success-text)" role="status">
                        <p class="font-semibold mb-1">Message envoyé, merci !</p>
                        <p class="text-sm">Nous revenons vers vous rapidement.</p>
                    </div>
                @elseif ($canSendMail)
                    <form novalidate method="POST" action="{{ route('site.contact.send') }}" class="space-y-4">
                        @csrf
                        {{-- Champ piège anti-robots, invisible pour les humains --}}
                        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label for="name" class="block text-sm font-medium mb-1.5">Nom complet</label>
                                <input id="name" name="name" value="{{ old('name') }}" required class="{{ $field }}">
                                @error('name')<p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="profile" class="block text-sm font-medium mb-1.5">Vous êtes</label>
                                <select id="profile" name="profile" class="{{ $field }}">
                                    @foreach (['Client' => 'Client d\'un pressing', 'Pressing' => 'Un pressing', 'Autre' => 'Autre'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('profile') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium mb-1.5">Email</label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" class="{{ $field }}">
                                @error('email')<p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium mb-1.5">Téléphone</label>
                                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="07 00 00 00 00" class="{{ $field }}">
                                @error('phone')<p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium mb-1.5">Message</label>
                            <textarea id="message" name="message" rows="6" required class="{{ $field }} h-auto py-3">{{ old('message') }}</textarea>
                            @error('message')<p class="mt-1.5 text-sm text-(--color-error)">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="w-full h-12 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">Envoyer le message</button>
                        <p class="text-xs text-(--color-text-muted)">Vos informations servent uniquement à vous répondre. <a href="{{ route('site.privacy') }}" class="underline">Confidentialité</a></p>
                    </form>
                @else
                    {{-- Envoi serveur indisponible : on ouvre la messagerie du visiteur. --}}
                    <a href="mailto:{{ $supportEmail }}?subject={{ rawurlencode('Contact PressLink') }}"
                       class="flex items-center justify-center gap-2 w-full h-12 rounded-xl bg-(--color-primary) text-white font-semibold hover:bg-(--color-primary-600)">
                        Écrire à {{ $supportEmail }}
                    </a>
                @endif
            </div>
        </div>
    </section>
</x-site.page>
