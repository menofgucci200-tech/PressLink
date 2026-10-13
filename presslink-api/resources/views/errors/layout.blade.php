{{-- Gabarit des pages d'erreur : CSS inline, aucune dépendance (Vite, base,
     session) pour rester affichable même quand l'application est en panne. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · @yield('title') — PressLink</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <style>
        :root{--p:#2563eb;--t:#0f172a;--m:#64748b;--b:#e2e8f0;--bg:#f8fafc;--accent:@yield('accent', '#2563eb')}
        *{box-sizing:border-box;margin:0}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--bg);color:var(--t);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;-webkit-font-smoothing:antialiased;overflow:hidden;position:relative}
        .halo{position:absolute;width:640px;height:640px;border-radius:50%;background:radial-gradient(circle,color-mix(in srgb,var(--accent) 18%,transparent),transparent 65%);top:50%;left:50%;transform:translate(-50%,-55%);pointer-events:none}
        main{position:relative;max-width:480px;width:100%;text-align:center;animation:in .6s cubic-bezier(.2,.8,.2,1) both}
        .logo{font-weight:800;font-size:20px;letter-spacing:-.02em;color:var(--t);text-decoration:none}.logo span{color:var(--p)}
        .code{font-size:clamp(88px,22vw,140px);font-weight:800;letter-spacing:-.06em;line-height:1;margin:28px 0 8px;background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 45%,#0f172a));-webkit-background-clip:text;background-clip:text;color:transparent}
        .icon{width:64px;height:64px;margin:0 auto -6px;border-radius:20px;display:grid;place-items:center;background:color-mix(in srgb,var(--accent) 12%,#fff);color:var(--accent);animation:float 4s ease-in-out infinite}
        h1{font-size:24px;font-weight:700;letter-spacing:-.01em;margin-bottom:10px}
        p{color:var(--m);line-height:1.6;margin-bottom:28px}
        .actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:center}
        .btn{display:inline-flex;align-items:center;gap:8px;height:46px;padding:0 20px;border-radius:12px;font-weight:600;font-size:15px;text-decoration:none;border:1px solid var(--b);color:var(--t);background:#fff;cursor:pointer;font-family:inherit}
        .btn.primary{background:var(--p);border-color:var(--p);color:#fff}
        .btn:hover{filter:brightness(.96)}
        .help{margin-top:32px;font-size:13px;color:var(--m)}.help a{color:var(--p);font-weight:600;text-decoration:none}
        @keyframes in{from{opacity:0;transform:translateY(16px)}}
        @keyframes float{50%{transform:translateY(-6px)}}
        @media (prefers-reduced-motion:reduce){main,.icon{animation:none}}
    </style>
</head>
<body>
    <div class="halo" aria-hidden="true"></div>
    <main>
        <a class="logo" href="/">Press<span>Link</span></a>
        <div class="code">@yield('code')</div>
        <div class="icon" aria-hidden="true">@yield('icon')</div>
        <h1 style="margin-top:22px">@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @section('actions')
                <a class="btn primary" href="/">Retour à l'accueil</a>
                <button class="btn" type="button" onclick="history.length > 1 ? history.back() : location.assign('/')">Page précédente</button>
            @show
        </div>
        <p class="help">Besoin d'aide ? <a href="/contact">Contactez le support</a> · 07 18 00 22 93</p>
    </main>
</body>
</html>
