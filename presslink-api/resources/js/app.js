import './feedback';

// Raccourci ⌘K / Ctrl+K — focus le champ de recherche de la page courante
// (chaque page qui en a besoin a la sienne, il n'y a pas de recherche
// globale unique), sans passer par la souris.
document.addEventListener('keydown', (e) => {
    if (!(e.metaKey || e.ctrlKey) || e.key.toLowerCase() !== 'k') return;

    // Les modificateurs Livewire (`.live.debounce.300ms`) font partie du
    // NOM de l'attribut, pas de sa valeur — un sélecteur CSS classique
    // (`[wire\\:model*="search"]`) ne peut donc pas le cibler : il faut
    // inspecter les attributs un par un.
    const input = Array.from(document.querySelectorAll('main input')).find((el) => Array.from(el.attributes)
        .some((attr) => attr.name.startsWith('wire:model') && attr.value === 'search'));

    if (input) {
        e.preventDefault();
        input.focus();
        input.select();
    }
});

// Transition douce entre les pages du dashboard (wire:navigate) : la page
// actuelle s'estompe légèrement avant le remplacement du DOM par Livewire,
// au lieu d'un remplacement instantané.
document.addEventListener('livewire:navigate', () => {
    const main = document.querySelector('main');
    if (main) main.classList.add('is-navigating');
});

document.addEventListener('livewire:navigated', () => {
    const main = document.querySelector('main');
    if (main) main.classList.remove('is-navigating');
});

// Lignes et cartes cliquables : tout élément [data-href] s'ouvre au clic,
// sans rechargement (navigation Livewire), sauf si le clic vise un lien, un
// bouton ou un champ placé dedans. Ctrl/⌘/Maj + clic ou clic molette :
// nouvel onglet. Au clavier, la ligne se sélectionne (Tab) et s'ouvre (Entrée).
function rowLinkFrom(event) {
    const row = event.target.closest?.('[data-href]');
    if (!row) return null;
    if (event.target.closest('a, button, input, select, textarea, label, [data-no-row-link]')) return null;
    // Sélection de texte (copier un numéro) : on n'ouvre pas la ligne.
    if (String(window.getSelection?.() ?? '').length > 0) return null;
    return row;
}

// Ligne avec aperçu ([data-preview-id]) : un clic simple ouvre le panneau
// d'aperçu du composant Livewire de la liste au lieu de quitter la page.
function previewRow(row) {
    const root = row.closest('[wire\\:id]');
    const wire = root && window.Livewire?.find(root.getAttribute('wire:id'));
    if (!wire) return false;
    wire.$call('preview', Number(row.dataset.previewId));
    return true;
}

function openRowLink(url, newTab) {
    if (newTab) {
        window.open(url, '_blank', 'noopener');
    } else if (window.Livewire?.navigate) {
        window.Livewire.navigate(url);
    } else {
        window.location.href = url;
    }
}

document.addEventListener('click', (e) => {
    const row = rowLinkFrom(e);
    if (!row) return;
    const newTab = e.ctrlKey || e.metaKey || e.shiftKey;
    if (!newTab && row.dataset.previewId && previewRow(row)) return;
    openRowLink(row.dataset.href, newTab);
});

document.addEventListener('auxclick', (e) => {
    if (e.button !== 1) return;
    const row = rowLinkFrom(e);
    if (row) {
        e.preventDefault();
        openRowLink(row.dataset.href, true);
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || !e.target.matches?.('[data-href]')) return;
    e.preventDefault();
    const newTab = e.ctrlKey || e.metaKey;
    if (!newTab && e.target.dataset.previewId && previewRow(e.target)) return;
    openRowLink(e.target.dataset.href, newTab);
});

// Bouton retour des pages détail ([data-back], sans wire:navigate : Livewire
// naviguerait dès le relâchement du clic) : revient à la page précédente de
// l'application — liste avec ses filtres, sa recherche et sa position — au
// lieu de repartir d'une liste vierge. Ouverte directement (lien partagé,
// rechargement), ou venant d'un formulaire de création ([data-skip-back] :
// on ne revient pas sur un formulaire déjà envoyé), la page renvoie vers la
// liste par défaut.
let navigatedPages = 0;
let leavingSkippablePage = false;
let previousPageSkippable = false;
document.addEventListener('livewire:navigating', () => {
    leavingSkippablePage = !!document.querySelector('[data-skip-back]');
});
document.addEventListener('livewire:navigated', () => {
    navigatedPages += 1;
    previousPageSkippable = leavingSkippablePage;
    leavingSkippablePage = false;
});

document.addEventListener('click', (e) => {
    const back = e.target.closest?.('[data-back]');
    if (!back || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;
    e.preventDefault();
    if (navigatedPages >= 2 && !previousPageSkippable) {
        window.history.back();
    } else {
        openRowLink(back.getAttribute('href'), false);
    }
});

// Chiffres animés ([data-count]) : la valeur défile jusqu'au nouveau chiffre
// — depuis 0 au premier affichage, puis depuis la valeur précédente de la
// même carte ([data-count-key]) quand le tableau de bord se met à jour.
const shownCounts = new Map();
const formatCount = (n) => n.toLocaleString('fr-FR').replace(/[\u202f\u00a0]/g, ' ');

function animateCounts(root = document) {
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    const elements = root.matches?.('[data-count]') ? [root] : root.querySelectorAll?.('[data-count]') ?? [];

    elements.forEach((el) => {
        if (el.dataset.countShown === el.dataset.count) return;
        const to = Number(el.dataset.count);
        const key = el.dataset.countKey;
        const from = key && shownCounts.has(key) ? shownCounts.get(key) : 0;
        if (key) shownCounts.set(key, to);
        el.dataset.countShown = el.dataset.count;

        if (from === to || reduceMotion || !Number.isFinite(to)) {
            el.textContent = formatCount(to);
            return;
        }

        el.textContent = formatCount(from);
        const start = performance.now();
        const duration = 650;
        const step = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = formatCount(Math.round(from + (to - from) * eased));
            if (t < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    });
}

document.addEventListener('livewire:navigated', () => animateCounts());
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morphed', ({ el }) => animateCounts(el));
});
