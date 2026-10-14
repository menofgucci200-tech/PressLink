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
    if (row) openRowLink(row.dataset.href, e.ctrlKey || e.metaKey || e.shiftKey);
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
    openRowLink(e.target.dataset.href, e.ctrlKey || e.metaKey);
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
