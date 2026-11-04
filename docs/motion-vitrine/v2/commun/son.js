// Son des maquettes du site : musique en boucle, voix off par section,
// bruitages. Tout passe par Web Audio (boucles sans couture, musique qui
// s'efface sous la voix). Le son ne démarre qu'après un geste du visiteur.
window.Son = (() => {
    const BASE = new URL('.', document.currentScript.src).href; // …/commun/
    let ctx = null, actif = false, musique = null, musiqueNom = null;
    let busMusique, busVoix, busSfx;
    const tampons = {};
    const textes = {};
    let voixEnCours = null, legende = null;
    const listeners = [];

    function memoire(v) { try { if (v === undefined) return localStorage.getItem('pl-son') === '1'; localStorage.setItem('pl-son', v ? '1' : '0'); } catch { return false; } }

    async function charger(url) {
        if (!tampons[url]) {
            tampons[url] = fetch(url).then((r) => r.arrayBuffer()).then((b) => ctx.decodeAudioData(b)).catch(() => null);
        }
        return tampons[url];
    }
    function init() {
        if (ctx) return;
        ctx = new (window.AudioContext || window.webkitAudioContext)();
        const master = ctx.createDynamicsCompressor();
        master.connect(ctx.destination);
        busMusique = ctx.createGain(); busMusique.gain.value = 0.55; busMusique.connect(master);
        busVoix = ctx.createGain(); busVoix.gain.value = 1.0; busVoix.connect(master);
        busSfx = ctx.createGain(); busSfx.gain.value = 0.7; busSfx.connect(master);
    }
    function niveauMusique(v, duree = 0.35) {
        if (!ctx) return;
        busMusique.gain.cancelScheduledValues(ctx.currentTime);
        busMusique.gain.setTargetAtTime(v, ctx.currentTime, duree / 3);
    }

    /** Lance (ou change) la boucle musicale avec un fondu enchaîné. */
    async function jouerMusique(url) {
        musiqueNom = url;
        if (!actif) return;
        const buf = await charger(url);
        if (!buf || musiqueNom !== url) return;
        const src = ctx.createBufferSource();
        src.buffer = buf; src.loop = true;
        const g = ctx.createGain(); g.gain.value = 0;
        src.connect(g).connect(busMusique);
        src.start();
        g.gain.setTargetAtTime(1, ctx.currentTime, 0.4);
        if (musique) { const old = musique; old.g.gain.setTargetAtTime(0, ctx.currentTime, 0.3); setTimeout(() => old.src.stop(), 1500); }
        musique = { src, g };
    }

    async function sfx(nom, gain = 1) {
        if (!actif) return;
        const buf = await charger(BASE + 'sons/' + nom + '.mp3');
        if (!buf) return;
        const src = ctx.createBufferSource(); src.buffer = buf;
        const g = ctx.createGain(); g.gain.value = gain;
        src.connect(g).connect(busSfx); src.start();
    }

    /** Joue une réplique (une seule à la fois) et affiche sa légende. */
    async function voix(url, texte, qui = '') {
        if (!actif) return;
        const buf = await charger(url);
        if (!buf) return;
        if (voixEnCours) { try { voixEnCours.stop(); } catch {} }
        const src = ctx.createBufferSource(); src.buffer = buf;
        src.connect(busVoix);
        niveauMusique(0.16);
        afficherLegende(texte, qui);
        src.onended = () => { if (voixEnCours === src) { voixEnCours = null; niveauMusique(0.55, 0.8); cacherLegende(); } };
        voixEnCours = src;
        src.start();
    }

    function afficherLegende(texte, qui) {
        if (!legende || !texte) return;
        legende.innerHTML = (qui ? `<b>${qui} :</b> ` : '') + texte;
        legende.classList.add('on');
    }
    function cacherLegende() { legende?.classList.remove('on'); }

    async function basculer(force) {
        actif = force ?? !actif;
        memoire(actif);
        if (actif) {
            init();
            await ctx.resume();
            if (musiqueNom) { const n = musiqueNom; musiqueNom = null; jouerMusique(n); }
        } else if (ctx) {
            if (voixEnCours) { try { voixEnCours.stop(); } catch {} }
            if (musique) { musique.src.stop(); musique = null; }
            cacherLegende();
        }
        listeners.forEach((f) => f(actif));
    }

    /** Branche le bouton son (data-son) et la zone de légende (data-legende). */
    function brancher({ musique: url } = {}) {
        musiqueNom = url;
        legende = document.querySelector('[data-legende]');
        document.querySelectorAll('[data-son]').forEach((b) => {
            b.addEventListener('click', () => basculer());
            listeners.push((on) => { b.setAttribute('aria-pressed', on ? 'true' : 'false'); b.classList.toggle('on', on); });
        });
    }

    /* Ambiances superposées (boucles dont on dose le volume, ex. l'heure qui passe). */
    const ambiances = {};
    let ambUrls = {}, ambNiveaux = {};
    async function demarrerAmbiances() {
        for (const [nom, url] of Object.entries(ambUrls)) {
            if (ambiances[nom]) continue;
            const buf = await charger(url);
            if (!buf || !actif) return;
            const src = ctx.createBufferSource(); src.buffer = buf; src.loop = true;
            const g = ctx.createGain(); g.gain.value = ambNiveaux[nom] ?? 0;
            src.connect(g).connect(busMusique); src.start();
            ambiances[nom] = { src, g };
        }
    }
    function preparerAmbiances(urls) { ambUrls = urls; listeners.push((on) => { if (on) demarrerAmbiances(); else { for (const k in ambiances) { ambiances[k].src.stop(); delete ambiances[k]; } } }); }
    function doserAmbiances(niveaux) {
        ambNiveaux = niveaux;
        if (!ctx) return;
        for (const [nom, v] of Object.entries(niveaux)) ambiances[nom]?.g.gain.setTargetAtTime(v, ctx.currentTime, 0.15);
    }
    /* Note jouée à une hauteur donnée (kora, etc.). */
    async function note(nom, gain = 1, rate = 1) {
        if (!actif) return;
        const buf = await charger(BASE + 'sons/' + nom + '.mp3');
        if (!buf) return;
        const src = ctx.createBufferSource(); src.buffer = buf; src.playbackRate.value = rate;
        const g = ctx.createGain(); g.gain.value = gain;
        src.connect(g).connect(busSfx); src.start();
    }

    return { brancher, basculer, voix, sfx, note, jouerMusique, preparerAmbiances, doserAmbiances, get actif() { return actif; }, onChange: (f) => listeners.push(f) };
})();
