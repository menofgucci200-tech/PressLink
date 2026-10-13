{{-- Portail « Console super admin » : sombre, violet, volontairement différent
     de l'espace pressing pour qu'on sache toujours où l'on se connecte. --}}
<div class="relative min-h-screen overflow-hidden bg-slate-950 text-white flex items-center justify-center px-5 py-12">
    {{-- Décor : grille + halos --}}
    <div class="absolute inset-0 opacity-[.07] [background-image:linear-gradient(#fff_1px,transparent_1px),linear-gradient(90deg,#fff_1px,transparent_1px)] [background-size:44px_44px] [mask-image:radial-gradient(ellipse_at_center,#000_30%,transparent_75%)]" aria-hidden="true"></div>
    <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[46rem] h-[46rem] rounded-full bg-violet-600/25 blur-3xl" aria-hidden="true"></div>
    <div class="absolute -bottom-48 -right-32 w-[30rem] h-[30rem] rounded-full bg-indigo-500/20 blur-3xl" aria-hidden="true"></div>

    <div class="relative w-full max-w-md animate-step">
        <div class="flex items-center justify-between mb-8">
            <span class="font-display text-xl font-bold tracking-tight">Press<span class="text-violet-400">Link</span></span>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-violet-400/30 bg-violet-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-widest text-violet-200">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-400 animate-pulse"></span>
                Super admin
            </span>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/[.04] backdrop-blur-xl shadow-2xl shadow-black/40 p-8 sm:p-10">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center mb-6 shadow-lg shadow-violet-900/50">
                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
            </div>
            <h1 class="font-display text-3xl font-bold tracking-tight mb-1.5">Console d'administration</h1>
            <p class="text-slate-400 mb-8">Pilotage de la plateforme : pressings, abonnements, commandes et administrateurs.</p>

            @include('livewire.auth._form', ['dark' => true])
        </div>

        <p class="mt-6 flex items-center justify-center gap-2 text-xs text-slate-500">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            Accès réservé à l'équipe PressLink.
        </p>
        <p class="mt-2 text-center text-xs text-slate-600">
            Vous êtes un pressing ? <a href="{{ route('login') }}" class="text-slate-400 hover:text-white underline-offset-2 hover:underline">Espace pressing</a>
        </p>
    </div>
</div>
