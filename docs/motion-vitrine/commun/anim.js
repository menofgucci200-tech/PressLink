// Outils d'animation communs aux maquettes : chaque video.html définit draw(t)
// et ces fonctions s'occupent du reste (chargement, sous-titres, bouches).
const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
const lerp = (a, b, x) => a + (b - a) * x;
/** Progression 0 → 1 entre les instants a et b. */
const p = (t, a, b) => clamp((t - a) / (b - a));
const E = {
    out: (x) => 1 - Math.pow(1 - x, 3),
    in: (x) => x * x * x,
    inOut: (x) => (x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2),
    back: (x) => { const c = 1.9; return 1 + (c + 1) * Math.pow(x - 1, 3) + c * Math.pow(x - 1, 2); },
    elastic: (x) => (x === 0 || x === 1 ? x : Math.pow(2, -10 * x) * Math.sin((x * 10 - 0.75) * (2 * Math.PI) / 3) + 1),
    bounce: (x) => {
        const n = 7.5625, d = 2.75;
        if (x < 1 / d) return n * x * x;
        if (x < 2 / d) return n * (x -= 1.5 / d) * x + 0.75;
        if (x < 2.5 / d) return n * (x -= 2.25 / d) * x + 0.9375;
        return n * (x -= 2.625 / d) * x + 0.984375;
    },
};
const $ = (id) => document.getElementById(id);
/** Applique transform / opacité / visibilité en une fois. */
function put(el, { x = 0, y = 0, s = 1, sx = null, sy = null, r = 0, o = 1, show = true, extra = '' } = {}) {
    if (typeof el === 'string') el = $(el);
    el.style.display = show ? '' : 'none';
    if (!show) return;
    el.style.opacity = o;
    el.style.transform = `translate(${x}px, ${y}px) rotate(${r}deg) scale(${sx ?? s}, ${sy ?? s}) ${extra}`;
}
/** Pseudo-hasard stable (même image = même rendu). */
const noise = (seed) => { const x = Math.sin(seed * 12.9898) * 43758.5453; return x - Math.floor(x); };

let TL = null, VOIX = {};
/** Niveau de voix (0..1) d'un personnage à l'instant t : fait bouger sa bouche. */
function niveau(qui, t) {
    let v = 0;
    for (const r of TL.voix) {
        const rep = VOIX[r.cle];
        if (!rep || (qui && rep.qui !== qui)) continue;
        const i = Math.floor((t - r.debut) * 30);
        if (i >= 0 && i < rep.env.length) v = Math.max(v, rep.env[i]);
    }
    return v;
}
function debut(cle) { return TL.voix.find((v) => v.cle === cle).debut; }
function fin(cle) { return debut(cle) + VOIX[cle].duree; }

window.ready = (async () => {
    await document.fonts.ready;
    await Promise.all([...document.images].map((img) => (img.complete ? null : new Promise((r) => { img.onload = img.onerror = r; }))));
})();
window.setup = (timeline, repliques) => {
    TL = timeline;
    for (const r of repliques) VOIX[r.cle] = r;
};
window.renderAt = (t) => {
    draw(t);
    const cap = $('caption');
    if (cap) {
        const cur = TL.voix.find((v) => t >= v.debut - 0.05 && t <= v.debut + VOIX[v.cle].duree + 0.25);
        if (cur) {
            const rep = VOIX[cur.cle];
            cap.innerHTML = `<span>${rep.qui ? `<b>${rep.qui} :</b> ` : ''}${rep.texte}</span>`;
            cap.style.opacity = clamp(Math.min((t - cur.debut + 0.05) / 0.12, (cur.debut + rep.duree + 0.25 - t) / 0.12));
        } else cap.style.opacity = 0;
    }
};
