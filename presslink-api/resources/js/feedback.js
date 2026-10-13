// Messages de l'interface aux couleurs de PressLink : boîte de confirmation
// et notifications (« toasts ») à la place des fenêtres natives du
// navigateur (confirm/alert), y compris celles qu'affiche Livewire par
// défaut (session expirée, erreur serveur). L'affichage est assuré par le
// composant Blade <x-feedback />, présent dans chaque layout.

const CONFIRM_EVENT = 'press:confirm';
const TOAST_EVENT = 'toast';

const DANGER_WORDS = /supprim|définitiv|irréversible|retirer|désactiv|suspend|annuler la commande/i;

/**
 * Ouvre la boîte de confirmation et résout `true` si l'utilisateur
 * confirme. Retombe sur `window.confirm` si le composant est absent.
 *
 * @param {{ title?: string, message: string, confirmLabel?: string, cancelLabel?: string, tone?: 'danger'|'warning'|'primary' }} options
 * @returns {Promise<boolean>}
 */
function confirmAction(options) {
    if (!document.querySelector('[data-press-feedback]')) {
        return Promise.resolve(window.confirm(options.message));
    }

    return new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent(CONFIRM_EVENT, { detail: { ...options, resolve } }));
    });
}

/**
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} [type]
 */
function toast(message, type = 'success') {
    window.dispatchEvent(new CustomEvent(TOAST_EVENT, { detail: { message, type } }));
}

window.PressLink = { ...(window.PressLink ?? {}), confirm: confirmAction, toast };

// ----- wire:confirm -----------------------------------------------------
// Livewire lit `el.__livewire_confirm(action, instead)` au moment du clic :
// on le remplace (en phase de capture, donc avant le gestionnaire de
// Livewire) par notre boîte de confirmation, asynchrone. Le message reste
// celui de `wire:confirm` ; titre, libellé du bouton et ton se règlent
// avec data-confirm-title, data-confirm-label et data-confirm-tone.
function patchConfirm(event) {
    let el = event.target instanceof Element ? event.target : null;

    for (; el; el = el.parentElement) {
        if (!el.hasAttribute('wire:confirm') || typeof el.__livewire_confirm !== 'function') continue;

        // Remplacé à chaque clic : Livewire peut réinitialiser la directive
        // quand l'attribut change (message différent après une action).
        const target = el;
        target.__livewire_confirm = (action, instead) => {
            const message = (target.getAttribute('wire:confirm') || 'Confirmer cette action ?').replaceAll('\\n', '\n');
            confirmAction({
                title: target.dataset.confirmTitle || 'Confirmer l’action',
                message,
                confirmLabel: target.dataset.confirmLabel || 'Confirmer',
                cancelLabel: target.dataset.confirmCancel || 'Annuler',
                tone: target.dataset.confirmTone || (DANGER_WORDS.test(message) ? 'danger' : 'primary'),
            }).then((ok) => (ok ? action() : instead()));
        };
    }
}

document.addEventListener('click', patchConfirm, true);
document.addEventListener('submit', patchConfirm, true);

// ----- Erreurs des requêtes Livewire ------------------------------------
const isDebug = () => document.querySelector('meta[name="app-debug"]')?.content === '1';

const ERROR_MESSAGES = {
    403: 'Vous n’avez pas l’autorisation d’effectuer cette action.',
    404: 'Cet élément n’existe plus. Rechargez la page pour voir les données à jour.',
    413: 'Le fichier envoyé est trop volumineux.',
    429: 'Trop de tentatives en peu de temps. Patientez un instant puis réessayez.',
    503: 'PressLink est en maintenance pour quelques minutes. Réessayez bientôt.',
};

let sessionExpiredShown = false;

function registerLivewireErrors() {
    window.Livewire.interceptRequest(({ onError, onFailure }) => {
        onError(({ response, preventDefault }) => {
            if (response.status === 419) {
                preventDefault();
                if (sessionExpiredShown) return;
                sessionExpiredShown = true;
                confirmAction({
                    title: 'Session expirée',
                    message: 'Par sécurité, votre session a expiré après une période d’inactivité. Rechargez la page pour continuer : vos données enregistrées sont conservées.',
                    confirmLabel: 'Recharger la page',
                    cancelLabel: 'Plus tard',
                    tone: 'warning',
                }).then((ok) => {
                    sessionExpiredShown = false;
                    if (ok) window.location.reload();
                });
                return;
            }

            // En développement, on garde la page d'erreur détaillée de Laravel.
            if (isDebug() && response.status >= 500) return;

            preventDefault();
            toast(ERROR_MESSAGES[response.status] ?? 'Une erreur inattendue est survenue. Réessayez dans un instant ; si le problème persiste, contactez le support PressLink.', 'error');
        });

        onFailure(() => {
            toast('Impossible de joindre PressLink. Vérifiez votre connexion internet puis réessayez.', 'error');
        });
    });
}

if (window.Livewire?.interceptRequest) {
    registerLivewireErrors();
} else {
    document.addEventListener('livewire:init', registerLivewireErrors, { once: true });
}
