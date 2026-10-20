@props(['os'])
@php
    $android = $os === 'android';
    $steps = $android
        ? ['Ouvrez presslink.org/app dans Chrome et appuyez sur « Installer ».', 'Confirmez avec « Installer » dans la fenêtre de Chrome.', 'PressLink apparaît sur votre écran d\'accueil, comme une application.']
        : ['Ouvrez presslink.org/app dans Safari et touchez Partager.', 'Choisissez « Sur l\'écran d\'accueil », puis « Ajouter ».', 'Ouvrez PressLink depuis son icône et connectez-vous.'];
    $icon = asset('apple-touch-icon.png');
    $ecran = asset('images/ecrans/connexion.webp');
@endphp
{{-- Démonstration animée de l'installation de la PWA (3 écrans en boucle),
     synchronisée avec la liste d'étapes. Tailles en em : cf. .mock. --}}
<div class="grid sm:grid-cols-[minmax(0,14rem)_1fr] gap-8 items-center">
    <div class="phone-mock phone-mock--appareil max-w-56 w-full mx-auto">
        <div class="phone-mock__screen install-demo">
            {{-- Écran 1 : l'application ouverte dans le navigateur (vraie capture) --}}
            <div class="scene app-shot">
                <div class="app-shot__etat"><span>9:41</span></div>
                <div class="barre-url">🔒 presslink.org/app</div>
                <img src="{{ $ecran }}" alt="L'écran de connexion de PressLink ouvert dans le navigateur" loading="lazy" decoding="async">
                @if ($android)
                    <div class="mock" style="position:absolute;left:0;right:0;bottom:0;height:auto;padding:.8em;background:transparent">
                        <div class="mock-card" style="display:flex;align-items:center;gap:.6em;box-shadow:0 .5em 1.4em rgb(15 23 42 / .18)">
                            <img src="{{ $icon }}" alt="" style="width:2em;height:2em;border-radius:.45em">
                            <span style="flex:1;font-size:.66em;line-height:1.3"><b>Installer PressLink</b><br><span style="color:var(--color-text-muted)">presslink.org</span></span>
                            <span style="background:var(--color-primary);color:#fff;border-radius:.5em;padding:.4em .7em;font-size:.66em;font-weight:700">Installer</span>
                            <span class="tap-dot" style="right:1.6em;left:auto;top:50%;animation-delay:0s"></span>
                        </div>
                    </div>
                @else
                    <div class="mock" style="position:absolute;left:0;right:0;bottom:0;height:3em;padding:0;background:#f2f2f7;border-top:1px solid #ddd;display:flex;justify-content:space-around;align-items:center;color:#0a84ff">
                        <span>‹</span><span>›</span>
                        <svg style="width:1.1em;height:1.1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M8 7l4-4 4 4M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
                        <span>⧉</span>
                        <span class="tap-dot" style="left:50%;top:50%"></span>
                    </div>
                @endif
            </div>

            {{-- Écran 2 : confirmation --}}
            <div class="scene mock" style="padding:0;background:#0f172a">
                <img src="{{ $ecran }}" alt="" loading="lazy" decoding="async" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:top;opacity:.55">
                @if ($android)
                    <div style="position:absolute;left:.8em;right:.8em;top:38%;background:#fff;border-radius:1em;padding:1em">
                        <div style="font-weight:700;font-size:.85em;margin-bottom:.7em">Installer l'application ?</div>
                        <div style="display:flex;align-items:center;gap:.6em;margin-bottom:1em">
                            <img src="{{ $icon }}" alt="" style="width:2.2em;height:2.2em;border-radius:.5em">
                            <span style="font-size:.7em;line-height:1.3"><b>PressLink</b><br><span style="color:var(--color-text-muted)">presslink.org</span></span>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:1.2em;font-size:.72em;font-weight:700;color:var(--color-primary)"><span>Annuler</span><span>Installer</span></div>
                        <span class="tap-dot" style="right:1.4em;left:auto;bottom:.7em;top:auto"></span>
                    </div>
                @else
                    <div style="position:absolute;left:0;right:0;bottom:0;background:#f2f2f7;border-radius:1em 1em 0 0;padding:1em .8em">
                        <div style="display:flex;align-items:center;gap:.6em;margin-bottom:.9em">
                            <img src="{{ $icon }}" alt="" style="width:2.2em;height:2.2em;border-radius:.5em">
                            <span style="font-size:.7em;line-height:1.3"><b>PressLink</b><br><span style="color:var(--color-text-muted)">presslink.org</span></span>
                        </div>
                        @foreach (['Copier', 'Sur l\'écran d\'accueil', 'Ajouter aux favoris'] as $row)
                            <div style="position:relative;background:#fff;padding:.6em .8em;font-size:.68em;{{ $loop->first ? 'border-radius:.8em .8em 0 0' : '' }}{{ $loop->last ? 'border-radius:0 0 .8em .8em' : 'border-bottom:1px solid #eee' }};{{ $loop->index === 1 ? 'font-weight:700;color:var(--color-primary)' : '' }}">
                                {{ $row }}
                                @if ($loop->index === 1)
                                    <span class="tap-dot" style="left:80%;top:50%"></span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Écran 3 : écran d'accueil --}}
            <div class="scene" style="background:linear-gradient(160deg,#1e3a8a,#0ea5e9);padding:4em 1em 1em;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1.2em .6em;align-content:start;font-size:6.4cqw">
                @for ($i = 0; $i < 11; $i++)
                    <span style="aspect-ratio:1;border-radius:.6em;background:rgb(255 255 255 / .22)"></span>
                @endfor
                <span style="text-align:center">
                    <img src="{{ $icon }}" alt="" style="width:100%;aspect-ratio:1;border-radius:.6em;box-shadow:0 0 0 .2em #fff,0 .5em 1.2em rgb(0 0 0 / .35)">
                    <span style="display:block;color:#fff;font-size:.5em;margin-top:.4em;font-weight:600">PressLink</span>
                </span>
            </div>
        </div>
    </div>

    <ol class="install-steps space-y-3">
        @foreach ($steps as $step)
            <li class="flex gap-4 items-start border-2 border-(--color-border) rounded-2xl p-4 bg-(--color-surface)">
                <span class="w-8 h-8 flex-none rounded-full bg-(--color-primary) text-white font-bold flex items-center justify-center text-sm">{{ $loop->iteration }}</span>
                <span class="text-(--color-text-secondary) leading-relaxed pt-1">{{ $step }}</span>
            </li>
        @endforeach
    </ol>
</div>
