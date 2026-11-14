// Audience du site vitrine (menu Audience de la console super admin) : une
// page vue à l'ouverture, puis chaque clic sur un lien ou un bouton. Actif
// seulement sur les pages qui portent <meta name="presslink-audience">.
// Sans cookie : le serveur compte les visiteurs avec une empreinte du jour.
// Un appareil peut être exclu (bouton de la page Audience) ; les navigateurs
// pilotés par un programme ne sont jamais comptés.

const IGNORER = 'presslink-audience-ignore';

function exclu() {
    try {
        return localStorage.getItem(IGNORER) === '1';
    } catch {
        return false;
    }
}

function envoyer(url, donnees) {
    const corps = JSON.stringify({ path: location.pathname, ...donnees });
    const blob = new Blob([corps], { type: 'application/json' });
    if (navigator.sendBeacon?.(url, blob)) return;
    fetch(url, { method: 'POST', body: corps, keepalive: true, headers: { 'Content-Type': 'application/json' } }).catch(() => {});
}

function libelle(el) {
    const texte = el.dataset.audience || el.getAttribute('aria-label') || el.innerText || el.getAttribute('title') || '';
    return texte.replace(/\s+/g, ' ').trim().slice(0, 120);
}

function destination(el) {
    const href = el.getAttribute?.('href');
    if (!href) return null;
    let lien;
    try {
        lien = new URL(href, location.href);
    } catch {
        return null;
    }
    if (lien.protocol === 'tel:' || lien.protocol === 'mailto:') return lien.protocol + lien.pathname;
    // Jamais les paramètres d'un lien (message WhatsApp pré-rempli, jetons…).
    if (lien.origin === location.origin) return lien.pathname + lien.hash;
    return lien.host + lien.pathname;
}

export function suivreAudience() {
    const url = document.querySelector('meta[name="presslink-audience"]')?.content;
    if (!url || navigator.webdriver || exclu()) return;

    const campagne = new URLSearchParams(location.search);
    envoyer(url, {
        type: 'page',
        referrer: document.referrer || null,
        campaign: campagne.get('utm_source') || campagne.get('ref') || null,
    });

    document.addEventListener('click', (e) => {
        const el = e.target.closest?.('a[href], button, [data-audience]');
        if (!el || exclu()) return;
        envoyer(url, { type: 'click', label: libelle(el) || null, target: destination(el) });
    }, { capture: true });
}
