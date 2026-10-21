// Tableau de bord en ligne : envoie ce qui a été fait sur le comptoir hors
// connexion, garde la copie des données à jour et fait préparer la page du
// comptoir par le service worker (public/espace-sw.js) pour la prochaine
// coupure. Actif seulement si le pressing a le mode hors connexion
// (attributs data-hors-ligne-* posés par layouts/dashboard.blade.php).

import { StockHorsLigne } from './hors-ligne';

let stock = null;
let enCours = false;

function bandeau(texte) {
    let el = document.getElementById('bandeau-hors-ligne');
    if (!texte) {
        el?.remove();
        return;
    }
    if (!el) {
        el = document.createElement('a');
        el.id = 'bandeau-hors-ligne';
        el.href = '/comptoir-hors-ligne';
        el.className = 'fixed bottom-4 right-4 z-40 rounded-xl bg-(--color-info) text-white text-sm font-semibold px-4 py-2.5 shadow-lg';
        document.body.appendChild(el);
    }
    el.textContent = texte;
}

async function synchroniser() {
    if (!stock || enCours || !navigator.onLine) return;
    enCours = true;
    try {
        const resultat = await stock.envoyer();
        if (resultat.etat === 'vide' && stock.donneesPerimees()) {
            await stock.rafraichir();
        }
        const restantes = stock.file().length;
        const problemes = stock.problemes().length;
        if (restantes > 0) {
            bandeau(`${restantes} opération(s) du comptoir hors connexion en attente d'envoi`);
        } else if (problemes > 0) {
            bandeau(`${problemes} point(s) à vérifier sur le comptoir hors connexion`);
        } else {
            bandeau(null);
        }
    } finally {
        enCours = false;
    }
}

function preparerComptoir() {
    navigator.serviceWorker?.ready.then((registration) => {
        registration.active?.postMessage({ type: 'preparer-comptoir' });
    }).catch(() => {});
}

function demarrer() {
    const pressing = document.body.dataset.horsLignePressing;
    const user = document.body.dataset.horsLigneUser;
    if (!pressing || !user) {
        return;
    }
    if (!stock || stock.pressingId !== Number(pressing)) {
        stock = new StockHorsLigne(pressing, user);
        preparerComptoir();
    }
    synchroniser();
}

document.addEventListener('DOMContentLoaded', demarrer);
document.addEventListener('livewire:navigated', demarrer);
window.addEventListener('online', synchroniser);
setInterval(synchroniser, 60000);

// Déconnexion avec des opérations pas encore envoyées : on prévient.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!stock || !(form instanceof HTMLFormElement) || !form.action.endsWith('/logout')) return;
    const restantes = stock.file().length;
    if (restantes > 0 && !window.confirm(`${restantes} opération(s) faite(s) hors connexion ne sont pas encore envoyées. Elles restent sur ce PC et partiront à votre prochaine connexion. Se déconnecter quand même ?`)) {
        event.preventDefault();
        return;
    }
    // PC partagé : après déconnexion, le comptoir ne s'ouvre plus avec les
    // données de ce compte (les opérations non envoyées, elles, restent).
    stock.oublierDonnees();
    navigator.serviceWorker?.controller?.postMessage({ type: 'oublier-comptoir' });
}, true);
