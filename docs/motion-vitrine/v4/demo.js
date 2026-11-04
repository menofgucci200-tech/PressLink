/*
 * Démo automatique des maquettes : un curseur parcourt la page et clique à la
 * place du visiteur. Elle se lance seule à l'ouverture et s'arrête dès que le
 * visiteur touche la page. Le bouton « Rejouer la démo » la relance.
 */
window.Demo = (function () {
    const lent = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let curseur = null;
    let rejeter = null;
    let minuteur = null;
    let enCours = false;
    let bouton = null;

    function creerCurseur() {
        curseur = document.createElement('div');
        curseur.className = 'demo-curseur';
        curseur.hidden = true;
        curseur.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26"><path d="M4 2.5l15.5 9.2-6.9 1.5 3.9 7.3-2.9 1.5-3.9-7.3L4 19.8z" fill="#fff" stroke="#0F172A" stroke-width="1.6" stroke-linejoin="round"/></svg>';
        document.body.appendChild(curseur);
    }

    function attendre(ms) {
        return new Promise((ok, ko) => {
            rejeter = ko;
            minuteur = setTimeout(ok, ms);
        });
    }

    function element(cible) {
        return typeof cible === 'string' ? document.querySelector(cible) : cible;
    }

    async function vers(cible) {
        const el = element(cible);
        if (!el) { return null; }
        let r = el.getBoundingClientRect();
        if (r.top < 70 || r.bottom > window.innerHeight - 40) {
            window.scrollTo({ top: window.scrollY + r.top - window.innerHeight / 2 + r.height / 2, behavior: 'smooth' });
            await attendre(750);
            r = el.getBoundingClientRect();
        }
        curseur.style.transform = `translate(${r.left + Math.min(r.width / 2, 120)}px, ${r.top + r.height / 2}px)`;
        await attendre(800);
        return el;
    }

    async function cliquer(cible) {
        const el = await vers(cible);
        if (!el) { return; }
        curseur.classList.remove('clic');
        void curseur.offsetWidth;
        curseur.classList.add('clic');
        await attendre(160);
        el.click();
        await attendre(450);
    }

    async function taper(cible, texte) {
        const el = await vers(cible);
        if (!el) { return; }
        el.focus({ preventScroll: true });
        el.value = '';
        for (const lettre of texte) {
            el.value += lettre;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            await attendre(45 + Math.random() * 40);
        }
        await attendre(250);
    }

    async function defiler(cible, duree = 900) {
        const el = typeof cible === 'number' ? null : element(cible);
        if (typeof cible !== 'number' && !el) { return; }
        const haut = el ? window.scrollY + el.getBoundingClientRect().top - 90 : cible;
        window.scrollTo({ top: haut, behavior: 'smooth' });
        await attendre(duree);
    }

    function arreter() {
        if (!enCours) { return; }
        clearTimeout(minuteur);
        if (rejeter) { rejeter('arrêt'); }
    }

    function majBouton() {
        if (!bouton) { return; }
        bouton.setAttribute('aria-pressed', enCours ? 'true' : 'false');
        bouton.innerHTML = enCours ? '■ Démo en cours (touchez pour reprendre la main)' : '▶ Rejouer la démo';
    }

    function lancer(scenario, avant) {
        if (enCours) { arreter(); return; }
        if (avant) { avant(); }
        enCours = true;
        curseur.hidden = false;
        majBouton();
        scenario({ vers, cliquer, taper, attendre, defiler })
            .catch(() => {})
            .finally(() => {
                enCours = false;
                curseur.hidden = true;
                curseur.style.transform = 'translate(-60px, -60px)';
                if (document.activeElement && document.activeElement.blur) { document.activeElement.blur(); }
                majBouton();
            });
    }

    function installer({ scenario, reinitialiser, auto = true }) {
        creerCurseur();
        bouton = document.querySelector('[data-rejouer]');
        if (bouton) {
            bouton.addEventListener('click', (e) => { e.stopPropagation(); lancer(scenario, reinitialiser); });
        }
        ['pointerdown', 'wheel', 'touchstart', 'keydown'].forEach((type) => {
            window.addEventListener(type, (e) => {
                if (e.isTrusted && !(bouton && bouton.contains(e.target))) { arreter(); }
            }, { passive: true, capture: true });
        });
        majBouton();
        if (auto && !lent) { setTimeout(() => lancer(scenario, reinitialiser), 1400); }
    }

    return { installer };
})();
