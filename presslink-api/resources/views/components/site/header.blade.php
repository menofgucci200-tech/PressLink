@php
    $links = [
        'site.clients' => 'Pour les clients',
        'site.pressings' => 'Pour les pressings',
        'site.pricing' => 'Tarifs',
        'site.how' => 'Comment ça marche',
        'site.install' => 'Installer',
        'site.faq' => 'Questions fréquentes',
        'site.contact' => 'Contact',
    ];
@endphp
<header class="sticky top-0 z-20 bg-(--color-surface)/90 backdrop-blur border-b border-(--color-border)">
    <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="font-display text-xl font-bold tracking-tight" aria-label="PressLink, accueil">
            Press<span class="text-(--color-primary)">Link</span>
        </a>
        <nav class="hidden lg:flex items-center gap-6 text-sm font-medium text-(--color-text-secondary)" aria-label="Navigation principale">
            @foreach ($links as $name => $label)
                <a href="{{ route($name) }}" @class(['hover:text-(--color-primary)', 'text-(--color-primary) font-semibold' => request()->routeIs($name)]) @if (request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="flex items-center gap-2">
            <x-theme-toggle class="w-10! h-10!" />
            <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center h-10 px-4 rounded-lg border border-(--color-border) text-sm font-semibold hover:border-(--color-primary) hover:text-(--color-primary)">
                Espace pressing
            </a>
            {{-- Menu mobile sans JavaScript --}}
            <details class="lg:hidden relative group">
                <summary class="list-none cursor-pointer w-10 h-10 rounded-lg border border-(--color-border) flex items-center justify-center" aria-label="Ouvrir le menu">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </summary>
                <nav class="absolute right-0 mt-2 w-64 bg-(--color-surface) border border-(--color-border) rounded-2xl shadow-lg p-2 flex flex-col text-sm font-medium" aria-label="Navigation mobile">
                    @foreach ($links as $name => $label)
                        <a href="{{ route($name) }}" @class(['px-3 py-2.5 rounded-lg hover:bg-(--color-bg)', 'text-(--color-primary) bg-(--color-primary-tint)' => request()->routeIs($name)])>{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('login') }}" class="mt-1 px-3 py-2.5 rounded-lg border-t border-(--color-border) hover:bg-(--color-bg)">Espace pressing</a>
                </nav>
            </details>
        </div>
    </div>
</header>
