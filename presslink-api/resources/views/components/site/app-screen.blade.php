{{-- Écran simplifié de l'application client (commande prête + notification),
     affiché dans les maquettes de téléphone. Tailles en em : cf. .mock. --}}
<div class="mock">
    <div style="font-weight:800;font-size:1.05em;margin-bottom:.15em">Mes commandes</div>
    <div style="font-size:.7em;color:var(--color-text-muted);margin-bottom:.8em">Pressing Excellence · PE-4821</div>

    <div class="mock-card" style="margin-bottom:.6em;border-color:#a7f3d0;box-shadow:0 .4em 1em rgb(16 185 129 / .15)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.45em">
            <span style="font-weight:700;font-size:.8em">PL-204518</span>
            <span class="mock-pill" style="background:var(--color-success-tint);color:var(--color-success-text)">● Prête</span>
        </div>
        <div style="font-size:.68em;color:var(--color-text-secondary);line-height:1.5">3 chemises · 1 costume<br>Total 7 500 FCFA</div>
        <div style="display:flex;gap:.25em;margin-top:.6em">
            @foreach ([1, 1, 1, 0] as $done)
                <span style="flex:1;height:.28em;border-radius:1em;background:{{ $done ? 'var(--color-success)' : 'var(--color-border)' }}"></span>
            @endforeach
        </div>
    </div>

    <div class="mock-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.45em">
            <span style="font-weight:700;font-size:.8em">PL-204377</span>
            <span class="mock-pill" style="background:var(--color-warning-tint);color:var(--color-warning-text)">● En traitement</span>
        </div>
        <div style="font-size:.68em;color:var(--color-text-secondary)">2 robes · 1 drap</div>
    </div>

    <div class="float-y" style="position:absolute;left:.6em;right:.6em;bottom:1.2em;background:rgb(15 23 42 / .92);color:#fff;border-radius:.9em;padding:.6em .7em;display:flex;gap:.55em;align-items:center;box-shadow:0 .6em 1.4em rgb(0 0 0 / .25)">
        <span style="width:1.9em;height:1.9em;border-radius:.5em;background:var(--color-primary);display:grid;place-items:center;font-weight:800;font-size:.8em">P</span>
        <span style="font-size:.66em;line-height:1.35"><b>PressLink</b><br>Votre commande PL-204518 est prête ✨</span>
    </div>
</div>
