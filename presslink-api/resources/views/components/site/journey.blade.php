@props(['current', 'done' => []])
{{-- Pages Tester et Inscription : le court briefing à gauche (comme l'accueil),
     le formulaire à droite, sous le fil des 3 étapes. --}}
<div class="acc site-polices min-h-screen">
    <x-site.fonts />
    <header class="relative max-w-6xl mx-auto px-5 h-16 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="-ml-1.5" aria-label="PressLink, accueil"><x-logo class="h-10" alt="" /></a>
        <div class="flex items-center gap-2">
            <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center whitespace-nowrap h-10 px-4 rounded-lg border border-(--color-border) bg-(--color-surface) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">Espace pressing</a>
            <x-theme-toggle class="w-10! h-10!" />
        </div>
    </header>
    <div class="acc-enveloppe acc-enveloppe--parcours">
        <div class="acc-briefing acc-briefing--court">{{ $briefing }}</div>
        <div class="pc-carte">
            <x-site.rail :current="$current" :done="$done" />
            <div class="pc-zone">{{ $slot }}</div>
        </div>
    </div>
</div>
