// Photos des articles d'une commande (resources/views/livewire/orders/_photos.blade.php).
// Chaque photo est réduite dans le téléphone avant l'envoi (1600 px, JPEG) :
// quelques centaines de Ko au lieu de plusieurs Mo, même en 3G. Elles partent
// une à une ; le serveur la convertit en WebP et retire les données EXIF.

const COTE_MAX = 1600;

async function reduire(fichier) {
    let image;
    try {
        image = await createImageBitmap(fichier, { imageOrientation: 'from-image' });
    } catch {
        image = await new Promise((ok, ko) => {
            const img = new Image();
            img.onload = () => ok(img);
            img.onerror = ko;
            img.src = URL.createObjectURL(fichier);
        });
    }
    const echelle = Math.min(1, COTE_MAX / Math.max(image.width, image.height));
    const toile = document.createElement('canvas');
    toile.width = Math.round(image.width * echelle);
    toile.height = Math.round(image.height * echelle);
    toile.getContext('2d').drawImage(image, 0, 0, toile.width, toile.height);
    const blob = await new Promise((ok) => toile.toBlob(ok, 'image/jpeg', 0.82));
    return blob ? new File([blob], 'photo.jpg', { type: 'image/jpeg' }) : fichier;
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('photosCommande', () => ({
        enCours: 0,
        total: 0,
        progression: 0,
        ouverte: null,
        debutGlisse: null,

        async envoyer(evenement) {
            const fichiers = [...evenement.target.files];
            evenement.target.value = '';
            this.total = fichiers.length;
            for (const [i, fichier] of fichiers.entries()) {
                this.enCours = i + 1;
                this.progression = 0;
                try {
                    const reduite = await reduire(fichier);
                    await new Promise((ok, ko) => this.$wire.upload('photo', reduite, ok, ko, (e) => { this.progression = e.detail.progress; }));
                    await this.$wire.savePhoto();
                } catch {
                    this.$wire.$refresh();
                }
            }
            this.enCours = 0;
            this.total = 0;
        },

        // Les liens sont relus dans la page : la liste change après chaque envoi.
        get urls() { return [...document.querySelectorAll('#photos [data-photo]')].map((el) => el.dataset.photo); },
        voir(i) { this.ouverte = i; },
        fermer() { this.ouverte = null; },
        glisser(fin) {
            if (this.debutGlisse === null) return;
            const ecart = fin - this.debutGlisse;
            if (Math.abs(ecart) > 50) this.suivante(ecart < 0 ? 1 : -1);
            this.debutGlisse = null;
        },
        suivante(pas) { const n = this.urls.length; if (n) this.ouverte = (this.ouverte + pas + n) % n; },
    }));
});
