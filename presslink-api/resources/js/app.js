// Raccourci ⌘K / Ctrl+K — focus la recherche globale de l'en-tête depuis
// n'importe où sur le dashboard, sans passer par la souris.
document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        const input = document.getElementById('global-search');
        if (input) {
            e.preventDefault();
            input.focus();
            input.select();
        }
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
