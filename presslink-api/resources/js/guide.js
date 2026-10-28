// Accompagnement « Bien démarrer » : depuis le guide du tableau de bord, chaque
// lien ouvre sa page avec `?guide=<parcours>`. Sur la page, l'élément à utiliser
// est mis en lumière (le reste assombri) et une bulle explique quoi faire,
// étape par étape. L'étape avance quand on clique sur l'élément en lumière,
// ou avec « Suivant ». Les éléments ciblés portent `data-guide="<nom>"`.

const PARCOURS = {
    prix: [
        { cible: 'ajouter-piece', titre: 'Ajoutez une pièce', texte: 'Chemise, pantalon, robe, costume… Cliquez sur « Ajouter une pièce ».', auClic: true },
        { cible: 'piece-nom', titre: 'Son nom', texte: 'Tapez le nom de la pièce, par exemple « Chemise ». Choisissez ensuite une icône juste en dessous.' },
        { cible: 'piece-prix', titre: 'Ses prix', texte: 'Indiquez le prix pour chaque service (repassage, nettoyage…). Laissez vide si vous ne le proposez pas.' },
        { cible: 'piece-enregistrer', titre: 'Enregistrez', texte: 'Cliquez sur « Ajouter la pièce ». Recommencez pour chaque pièce que vous traitez.', auClic: true },
    ],
    horaires: [
        { cible: 'horaires', titre: 'Vos horaires', texte: 'Pour chaque jour, indiquez l\'heure d\'ouverture et de fermeture, ou cochez « Fermé ».' },
        { cible: 'parametres-enregistrer', titre: 'Enregistrez', texte: 'Cliquez sur « Enregistrer » : vos clients verront vos horaires dans l\'application.', auClic: true },
    ],
    logo: [
        { cible: 'logo', titre: 'Votre logo', texte: 'Cliquez sur « Choisir un logo » et prenez l\'image de votre enseigne (photo ou fichier).' },
        { cible: 'parametres-enregistrer', titre: 'Enregistrez', texte: 'Cliquez sur « Enregistrer » : le logo apparaît sur vos reçus et dans l\'application.', auClic: true },
    ],
    equipe: [
        { cible: 'ajouter-membre', titre: 'Ajoutez un membre', texte: 'Gérant, caissière, employé : chacun a son propre accès. Cliquez sur « Ajouter un membre ».', auClic: true },
        { cible: 'membre-identite', titre: 'Qui est-ce ?', texte: 'Son nom et son rôle : le rôle décide de ce qu\'il peut voir et faire.' },
        { cible: 'membre-acces', titre: 'Son accès', texte: 'Choisissez un identifiant et un mot de passe : c\'est avec eux qu\'il se connectera.' },
        { cible: 'membre-creer', titre: 'Créez le compte', texte: 'Cliquez sur « Créer le compte », puis donnez-lui son identifiant et son mot de passe.', auClic: true },
    ],
    commande: [
        { cible: 'commande-client', titre: '1. Le client', texte: 'Cherchez-le par son nom ou son numéro. Nouveau client ? Créez-le avec son nom et son téléphone : c\'est ce numéro qui le relie à l\'application.' },
        { cible: 'commande-pieces', titre: '2. Les pièces', texte: 'Choisissez le service, puis touchez chaque pièce déposée. Touchez plusieurs fois pour en ajouter plusieurs.' },
        { cible: 'commande-ticket', titre: '3. Le ticket', texte: 'Vérifiez le total et choisissez le paiement : tout de suite, un acompte ou au retrait.' },
        { cible: 'commande-valider', titre: '4. Enregistrez', texte: 'Le client est prévenu sur son téléphone dès que la commande est prête.', auClic: true },
    ],
};

const CLE = 'presslink-guide';
let actif = null; // { nom, etape, page }
let elements = null;
let boucle = null;

function lire() {
    try {
        return JSON.parse(sessionStorage.getItem(CLE) || 'null');
    } catch {
        return null;
    }
}

function ecrire(valeur) {
    try {
        valeur ? sessionStorage.setItem(CLE, JSON.stringify(valeur)) : sessionStorage.removeItem(CLE);
    } catch {
        // Stockage indisponible : le parcours vit seulement le temps de la page.
    }
}

/** Premier élément visible portant ce nom (une page peut en avoir deux : téléphone et ordinateur). */
function cible(nom) {
    return [...document.querySelectorAll(`[data-guide="${nom}"]`)].find((el) => el.offsetParent !== null || el.getClientRects().length > 0) || null;
}

function creerElements() {
    const spot = document.createElement('div');
    spot.className = 'guide-spot';
    spot.setAttribute('aria-hidden', 'true');

    // Quatre panneaux sombres autour de l'élément (les clics passent au travers).
    const voiles = [0, 1, 2, 3].map(() => {
        const voile = document.createElement('div');
        voile.className = 'guide-voile';
        voile.setAttribute('aria-hidden', 'true');
        return voile;
    });

    const bulle = document.createElement('div');
    bulle.className = 'guide-bulle';
    bulle.setAttribute('role', 'dialog');
    bulle.setAttribute('aria-live', 'polite');
    bulle.innerHTML = `
        <p class="guide-compteur"></p>
        <p class="guide-titre"></p>
        <p class="guide-texte"></p>
        <p class="guide-attente">Cliquez sur l'élément en surbrillance.</p>
        <div class="guide-actions">
            <button type="button" class="guide-passer">Passer</button>
            <button type="button" class="guide-suivant">Suivant</button>
        </div>`;
    bulle.querySelector('.guide-passer').addEventListener('click', () => terminer(false));
    bulle.querySelector('.guide-suivant').addEventListener('click', () => avancer());

    document.body.append(...voiles, spot, bulle);
    return { spot, bulle, voiles };
}

function retirerElements() {
    elements?.voiles.forEach((voile) => voile.remove());
    elements?.spot.remove();
    elements?.bulle.remove();
    elements = null;
}

function afficherEtape() {
    const etapes = PARCOURS[actif.nom];
    const etape = etapes[actif.etape];
    const { bulle } = elements;

    bulle.querySelector('.guide-compteur').textContent = `Étape ${actif.etape + 1} sur ${etapes.length}`;
    bulle.querySelector('.guide-titre').textContent = etape.titre;
    bulle.querySelector('.guide-texte').textContent = etape.texte;
    bulle.querySelector('.guide-attente').hidden = !etape.auClic;
    bulle.querySelector('.guide-suivant').textContent = actif.etape === etapes.length - 1 ? 'Terminer' : 'Suivant';

    // Le défilement vers l'élément se fait dans placer(), une fois la page prête.
    actif.depuis = performance.now();
    actif.defile = false;
    ecrire(actif);
}

/** Suit l'élément ciblé (défilement, contenu qui bouge, formulaire qui s'ouvre). */
function placer() {
    if (!actif || !elements) return;

    const { spot, bulle } = elements;
    const el = cible(PARCOURS[actif.nom][actif.etape].cible);
    const telephone = window.innerWidth < 640;

    if (!el) {
        // Élément pas encore affiché (formulaire en cours d'ouverture) : bulle en bas, page assombrie.
        spot.style.opacity = '0';
        voiler(null);
        Object.assign(bulle.style, { top: 'auto', bottom: '24px', left: telephone ? '12px' : '50%', right: telephone ? '12px' : 'auto', transform: telephone ? 'none' : 'translateX(-50%)' });
        return;
    }

    // Une fois la page posée (Livewire remet le défilement en haut après une
    // navigation) : on amène l'élément au milieu de l'écran s'il n'est pas visible.
    if (!actif.defile && performance.now() - actif.depuis > 350) {
        actif.defile = true;
        const avant = el.getBoundingClientRect();
        if (avant.top < 70 || avant.bottom > window.innerHeight - 20) {
            el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    const r = el.getBoundingClientRect();
    const marge = 6;
    voiler({ top: r.top - marge, left: r.left - marge, right: r.right + marge, bottom: r.bottom + marge });
    Object.assign(spot.style, {
        opacity: '1',
        top: `${r.top - marge}px`,
        left: `${r.left - marge}px`,
        width: `${r.width + marge * 2}px`,
        height: `${r.height + marge * 2}px`,
    });

    if (telephone) {
        // Sur téléphone : la bulle en bas de l'écran, ou en haut si l'élément y est.
        const enBas = r.bottom < window.innerHeight * 0.6;
        Object.assign(bulle.style, { left: '12px', right: '12px', transform: 'none', top: enBas ? 'auto' : '12px', bottom: enBas ? '12px' : 'auto' });
        return;
    }

    const hauteur = bulle.offsetHeight || 160;
    const dessous = r.bottom + 14 + hauteur < window.innerHeight;
    const gauche = Math.min(Math.max(12, r.left), window.innerWidth - bulle.offsetWidth - 12);
    Object.assign(bulle.style, {
        right: 'auto',
        bottom: 'auto',
        transform: 'none',
        left: `${gauche}px`,
        top: dessous ? `${r.bottom + 14}px` : `${Math.max(12, r.top - hauteur - 14)}px`,
    });
}

/** Assombrit tout sauf le rectangle donné (null : tout l'écran). */
function voiler(trou) {
    const [haut, bas, gauche, droite] = elements.voiles;
    const W = window.innerWidth;
    const H = window.innerHeight;
    if (!trou) {
        Object.assign(haut.style, { top: '0px', left: '0px', width: `${W}px`, height: `${H}px` });
        [bas, gauche, droite].forEach((v) => Object.assign(v.style, { width: '0px', height: '0px' }));
        return;
    }
    const t = Math.max(0, trou.top);
    const b = Math.min(H, trou.bottom);
    Object.assign(haut.style, { top: '0px', left: '0px', width: `${W}px`, height: `${t}px` });
    Object.assign(bas.style, { top: `${b}px`, left: '0px', width: `${W}px`, height: `${Math.max(0, H - b)}px` });
    Object.assign(gauche.style, { top: `${t}px`, left: '0px', width: `${Math.max(0, trou.left)}px`, height: `${Math.max(0, b - t)}px` });
    Object.assign(droite.style, { top: `${t}px`, left: `${trou.right}px`, width: `${Math.max(0, W - trou.right)}px`, height: `${Math.max(0, b - t)}px` });
}

function avancer() {
    if (!actif) return;
    if (actif.etape >= PARCOURS[actif.nom].length - 1) {
        terminer(true);
        return;
    }
    actif.etape += 1;
    afficherEtape();
}

function terminer(complet) {
    ecrire(null);
    actif = null;
    cancelAnimationFrame(boucle);
    retirerElements();

    if (complet) {
        // Fin du parcours : retour au guide pour l'étape suivante.
        const fin = document.createElement('div');
        fin.className = 'guide-bulle guide-fin';
        fin.setAttribute('role', 'status');
        fin.innerHTML = `
            <p class="guide-titre">C'est fait !</p>
            <p class="guide-texte">Revenez au guide pour l'étape suivante.</p>
            <div class="guide-actions">
                <button type="button" class="guide-passer">Rester ici</button>
                <a class="guide-suivant" href="/tableau-de-bord">Retour au guide</a>
            </div>`;
        fin.querySelector('.guide-passer').addEventListener('click', () => fin.remove());
        document.body.append(fin);
    }
}

function demarrer(nom, etape = 0) {
    if (!PARCOURS[nom]) return;
    if (elements) terminer(false);

    actif = { nom, etape, page: window.location.pathname };
    elements = creerElements();
    afficherEtape();

    const suivre = () => {
        placer();
        boucle = requestAnimationFrame(suivre);
    };
    suivre();
}

// Clic sur l'élément en lumière : l'étape avance (après le rendu de Livewire).
document.addEventListener('click', (e) => {
    if (!actif) return;
    const etape = PARCOURS[actif.nom][actif.etape];
    const el = cible(etape.cible);
    if (etape.auClic && el && el.contains(e.target)) {
        setTimeout(avancer, 350);
    }
}, true);

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && actif) terminer(false);
});

/** Au chargement (et après une navigation) : parcours demandé par l'adresse, ou parcours en cours. */
function reprendre() {
    const params = new URLSearchParams(window.location.search);
    const demande = params.get('guide');

    if (demande && PARCOURS[demande]) {
        // L'adresse est nettoyée : recharger la page ne relance pas le parcours.
        params.delete('guide');
        const reste = params.toString();
        history.replaceState(history.state, '', window.location.pathname + (reste ? `?${reste}` : '') + window.location.hash);
        demarrer(demande);
        return;
    }

    // Un parcours appartient à sa page : quitter la page l'arrête.
    const enCours = lire();
    if (enCours && PARCOURS[enCours.nom] && !actif) {
        enCours.page === window.location.pathname ? demarrer(enCours.nom, enCours.etape) : ecrire(null);
    }
}

document.addEventListener('DOMContentLoaded', reprendre);
document.addEventListener('livewire:navigated', () => {
    // Le <body> est remplacé à chaque navigation (et l'événement suit aussi le
    // premier chargement) : on retire l'ancienne bulle avant de reprendre.
    if (elements) {
        cancelAnimationFrame(boucle);
        retirerElements();
        actif = null;
    }
    reprendre();
});
