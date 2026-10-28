import './feedback';
import './synchro-hors-ligne';
import './guide';

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
    window.Livewire.hook('morphed', ({ el }) => {
        animateCounts(el);
        // Nouveaux résultats dans la palette : le premier redevient actif.
        if (el.matches?.('[data-palette]')) setPaletteActive(el, 0);
    });
});

// Visible à l'écran (les éléments x-show masqués n'ont aucune boîte).
const isShown = (el) => !!el && el.getClientRects().length > 0;
const isTyping = (el) => !!el && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable);

// Palette de commandes (Ctrl/⌘ + K) : ↑ ↓ pour choisir, Entrée pour ouvrir,
// Échap pour fermer. Voir resources/views/livewire/command-palette.blade.php.
const paletteItems = (palette) => [...palette.querySelectorAll('[data-palette-item]')];

function setPaletteActive(palette, index) {
    const items = paletteItems(palette);
    items.forEach((el, i) => {
        el.classList.toggle('is-active', i === index);
        el.setAttribute('aria-selected', i === index ? 'true' : 'false');
    });
    items[index]?.scrollIntoView({ block: 'nearest' });
    palette.dataset.active = String(index);
}

window.PressLinkPalette = { reset: (palette) => setPaletteActive(palette, 0) };

const openPalette = () => window.dispatchEvent(new CustomEvent('open-palette'));
const closePalette = () => window.dispatchEvent(new CustomEvent('close-palette'));

document.addEventListener('keydown', (e) => {
    const input = e.target.closest?.('[data-palette-input]');
    if (!input) return;
    const palette = input.closest('[data-palette]');
    const items = paletteItems(palette);
    const index = Number(palette.dataset.active ?? 0);

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (items.length === 0) return;
        setPaletteActive(palette, (index + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        (items[index] ?? items[0])?.click();
    } else if (e.key === 'Escape') {
        // Ferme la palette seulement (pas le panneau d'aperçu dessous).
        e.stopPropagation();
        closePalette();
    }
});

document.addEventListener('mousemove', (e) => {
    const item = e.target.closest?.('[data-palette-item]');
    if (!item) return;
    const palette = item.closest('[data-palette]');
    const index = paletteItems(palette).indexOf(item);
    if (index >= 0 && String(index) !== palette.dataset.active) setPaletteActive(palette, index);
});

document.addEventListener('click', (e) => {
    const item = e.target.closest?.('[data-palette-item]');
    if (!item) return;
    closePalette();
    if (item.dataset.paletteAction === 'theme') window.PressLinkTheme?.toggle();
    if (item.dataset.paletteAction === 'shortcuts') window.dispatchEvent(new CustomEvent('open-shortcuts'));
});

// Raccourcis clavier (hors saisie) : Ctrl/⌘ + K ou « / » palette, « ? » aide,
// et ceux du layout (#shortcut-map) : « N » nouvelle commande, « G » puis une
// lettre pour aller à une page (G D tableau de bord, G C commandes…).
let pendingGoTo = 0;

document.addEventListener('keydown', (e) => {
    const palette = document.querySelector('[data-palette]');

    if ((e.ctrlKey || e.metaKey) && !e.altKey && e.key.toLowerCase() === 'k') {
        if (!palette) return;
        e.preventDefault();
        isShown(palette) ? closePalette() : openPalette();
        return;
    }

    if (e.ctrlKey || e.metaKey || e.altKey || isTyping(e.target)) return;
    // Une fenêtre (palette, confirmation, aide) est ouverte : on la laisse gérer.
    if (isShown(palette) || [...document.querySelectorAll('[role="alertdialog"], [data-shortcuts-help]')].some(isShown)) return;

    if (e.key === '/') {
        if (!palette) return;
        e.preventDefault();
        openPalette();
        return;
    }

    if (e.key === '?') {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('open-shortcuts'));
        return;
    }

    let map = {};
    try {
        map = JSON.parse(document.getElementById('shortcut-map')?.textContent || '{}');
    } catch {
        return;
    }

    const key = e.key.toLowerCase();
    if (pendingGoTo && Date.now() - pendingGoTo < 1200) {
        pendingGoTo = 0;
        if (map[`g ${key}`]) {
            e.preventDefault();
            openRowLink(map[`g ${key}`], false);
        }
        return;
    }
    if (key === 'g') {
        pendingGoTo = Date.now();
        return;
    }
    if (map[key]) {
        e.preventDefault();
        openRowLink(map[key], false);
    }
});

// Copier en un clic ([data-copy]) : n° de commande, téléphone… L'icône passe
// au « ✓ » et la bulle d'aide affiche « Copié ».
async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        const area = Object.assign(document.createElement('textarea'), { value: text });
        area.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();
        return ok;
    }
}

document.addEventListener('click', async (e) => {
    const button = e.target.closest?.('[data-copy]');
    if (!button) return;
    e.preventDefault();
    if (!(await copyText(button.dataset.copy))) return;
    const tooltip = button.dataset.tooltip;
    button.classList.add('is-copied');
    button.dataset.tooltip = 'Copié !';
    clearTimeout(button._copiedTimer);
    button._copiedTimer = setTimeout(() => {
        button.classList.remove('is-copied');
        button.dataset.tooltip = tooltip;
    }, 1400);
});
