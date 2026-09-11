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
