// Ticket de la nouvelle commande (resources/views/livewire/orders/create.blade.php).
// Les pièces, quantités, couleurs et le paiement se règlent dans le navigateur,
// sans aller-retour avec le serveur : chaque toucher est instantané. Le ticket
// est relié aux propriétés Livewire ($entangle) et part avec la validation ;
// il est aussi gardé en brouillon côté serveur, en arrière-plan, après une
// courte pause. Le serveur recalcule les prix à la validation.

/** « Chemise · Repassage · Bleu », comme sur le reçu. */
function nomAffiche(base, service, couleur) {
    return [base, service, couleur].filter((partie) => partie).join(' · ');
}

function sansAccents(texte) {
    return String(texte || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
}

/** Nom de la couleur nommée la plus proche d'un code « #RRGGBB » (même calcul que ColorPalette::nameFor). */
function couleurLaPlusProche(hex, palette) {
    const m = /^#?([0-9a-f]{6})$/i.exec(String(hex).trim());
    if (!m) return null;
    const rgb = (h) => [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
    const [r, g, b] = rgb(m[1]);
    let meilleure = null;
    let distanceMin = Infinity;
    for (const [nom, code] of Object.entries(palette)) {
        const [cr, cg, cb] = rgb(code.replace('#', ''));
        const rougeMoyen = (r + cr) / 2;
        const distance = (2 + rougeMoyen / 256) * (r - cr) ** 2 + 4 * (g - cg) ** 2 + (2 + (255 - rougeMoyen) / 256) * (b - cb) ** 2;
        if (distance < distanceMin) {
            distanceMin = distance;
            meilleure = nom;
        }
    }
    return meilleure;
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('ticketCommande', (lies) => ({
        ...lies,
        catalogue: { services: {}, pieces: {} },
        palette: {},
        presets: {},
        multicolore: 'Multicolore',
        max: 999,
        q: '',
        qty: { open: false, piece: null, name: '', value: '' },
        variantes: { open: false, piece: null },
        autre: { open: false, name: '', price: '', erreur: '' },
        ticketOpen: false,
        couleursLigne: null,
        sauvegarde: null,

        init() {
            const donnees = document.getElementById('catalogue-commande');
            if (donnees) {
                const lu = JSON.parse(donnees.textContent);
                this.catalogue = lu.catalogue;
                this.palette = lu.palette;
                this.presets = lu.presets;
                this.multicolore = lu.multicolore;
                this.max = lu.max;
            }
            if (!this.$wire.selectedCustomerId) this.focus('client');
            // Brouillon gardé côté serveur, en arrière-plan, après une pause.
            this.$watch('items', () => this.planifierSauvegarde());
        },

        planifierSauvegarde() {
            clearTimeout(this.sauvegarde);
            this.sauvegarde = setTimeout(() => this.$wire.$commit(), 4000);
        },

        // ── Pièces ─────────────────────────────────────────────────────────

        piece(service, id) {
            return this.catalogue.pieces[`${service}:${id}`] || null;
        },

        /** Quantité de la pièce pour le service affiché (toutes variantes et couleurs). */
        quantite(service, id) {
            return this.items.reduce((total, ligne) => total + (ligne.service_id === id && ligne.prestation_id === service ? ligne.quantity : 0), 0);
        },

        /** Quantité de la pièce sans variante, dans la couleur du pinceau. */
        quantitePinceau(id) {
            const ligne = this.items.find((l) => l.service_id === id && l.service_variant_id === null && l.prestation_id === this.prestationId && l.color === this.activeColor);
            return ligne ? ligne.quantity : 0;
        },

        matches(nom) {
            return this.q === '' || nom.includes(sansAccents(this.q));
        },

        ajouter(id) {
            const piece = this.piece(this.prestationId, id);
            if (!piece) return;
            if (piece.variants.length > 0) {
                this.variantes = { open: true, piece };
                return;
            }
            if (piece.price === null) return;
            this.ajouterLigne(piece.id, null, piece.name, piece.price, 1);
        },

        ajouterVariante(variante) {
            const piece = this.variantes.piece;
            this.ajouterLigne(piece.id, variante.id, `${piece.name} · ${variante.name}`, variante.price, 1);
            // La pièce reste affichée le temps que la fenêtre se ferme.
            this.variantes.open = false;
        },

        ajouterPremiere() {
            const tuile = Array.from(this.$root.querySelectorAll('[data-piece-tile]')).find((t) => t.offsetParent !== null);
            if (tuile) {
                tuile.querySelector('[data-add]').click();
                this.q = '';
            }
        },

        ajouterLigne(pieceId, varianteId, base, prix, quantite) {
            const service = this.catalogue.services[this.prestationId] || null;
            const lignes = this.items.map((l) => ({ ...l }));
            const existante = lignes.find((l) => l.service_id === pieceId && l.service_variant_id === varianteId && l.prestation_id === this.prestationId
                && l.base_name === base && l.unit_price_fcfa === prix && l.color === this.activeColor);
            if (existante) {
                existante.quantity = Math.min(this.max, existante.quantity + quantite);
            } else {
                lignes.push({
                    service_id: pieceId,
                    service_variant_id: varianteId,
                    prestation_id: this.prestationId,
                    prestation_name: service ? service.name : null,
                    base_name: base,
                    name: nomAffiche(base, service ? service.name : null, this.activeColor),
                    color: this.activeColor,
                    unit_price_fcfa: prix,
                    quantity: Math.min(this.max, quantite),
                });
            }
            this.items = lignes;
        },

        /** Retire une unité de la pièce (la dernière ligne ajoutée, hors variantes). */
        retirer(id) {
            for (let i = this.items.length - 1; i >= 0; i--) {
                const l = this.items[i];
                if (l.service_id === id && l.prestation_id === this.prestationId && l.service_variant_id === null) {
                    this.fixerQuantite(i, l.quantity - 1);
                    return;
                }
            }
        },

        ouvrirQuantite(id) {
            const piece = this.piece(this.prestationId, id);
            const valeur = this.quantitePinceau(id);
            this.qty = { open: true, piece, name: piece.name + (this.activeColor ? ` · ${this.activeColor}` : ''), value: valeur > 0 ? String(valeur) : '' };
            this.$nextTick(() => this.$refs.qtyInput && this.$refs.qtyInput.select());
        },

        validerQuantite() {
            const n = Math.max(0, Math.min(this.max, parseInt(this.qty.value, 10)));
            const piece = this.qty.piece;
            this.qty.open = false;
            if (isNaN(n) || !piece || piece.price === null) return;
            const index = this.items.findIndex((l) => l.service_id === piece.id && l.service_variant_id === null && l.prestation_id === this.prestationId
                && l.base_name === piece.name && l.unit_price_fcfa === piece.price && l.color === this.activeColor);
            if (index === -1) {
                if (n > 0) this.ajouterLigne(piece.id, null, piece.name, piece.price, n);
                return;
            }
            this.fixerQuantite(index, n);
        },

        ouvrirAutre() {
            this.autre = { open: true, name: '', price: '', erreur: '' };
            this.$nextTick(() => this.$refs.autreNom && this.$refs.autreNom.focus());
        },

        ajouterAutre() {
            const nom = this.autre.name.trim();
            const prix = parseInt(this.autre.price, 10);
            if (nom === '') { this.autre.erreur = 'Indiquez le nom de la pièce.'; return; }
            if (isNaN(prix) || prix < 0 || String(prix) !== String(this.autre.price).trim()) { this.autre.erreur = 'Le prix doit être un nombre entier de francs.'; return; }
            this.ajouterLigne(null, null, nom.slice(0, 150), prix, 1);
            this.autre.open = false;
        },

        // ── Service et couleur ─────────────────────────────────────────────

        choisirService(id) {
            this.prestationId = id;
            this.q = '';
        },

        choisirPinceau(couleur) {
            this.activeColor = couleur === this.activeColor ? null : couleur;
        },

        pinceauDepuisSelecteur(hex) {
            const nom = couleurLaPlusProche(hex, this.palette);
            if (nom) this.activeColor = nom;
        },

        /** Couleur choisie au sélecteur libre (ni proposée en un geste, ni multicolore). */
        estPersonnalisee(couleur) {
            return !!couleur && couleur !== this.multicolore && !(couleur in this.presets);
        },

        hex(couleur) {
            return couleur ? this.palette[couleur] || null : null;
        },

        pastille(couleur) {
            if (couleur === this.multicolore) return 'background: conic-gradient(#dc2626, #facc15, #16a34a, #2563eb, #7c3aed, #dc2626)';
            const code = this.hex(couleur);
            return code ? `background-color: ${code}` : '';
        },

        // ── Lignes du ticket ───────────────────────────────────────────────

        plus(i) {
            this.fixerQuantite(i, this.items[i].quantity + 1);
        },

        moins(i) {
            this.fixerQuantite(i, this.items[i].quantity - 1);
        },

        /** Quantité d'une ligne ; 0 la retire. */
        fixerQuantite(i, quantite) {
            const lignes = this.items.map((l) => ({ ...l }));
            if (!lignes[i]) return;
            if (!(quantite > 0)) {
                lignes.splice(i, 1);
            } else {
                lignes[i].quantity = Math.min(this.max, quantite);
            }
            this.items = lignes;
        },

        couleurLigne(i, couleur) {
            const lignes = this.items.map((l) => ({ ...l }));
            const ligne = lignes[i];
            if (!ligne) return;
            ligne.color = couleur && couleur !== ligne.color ? couleur.slice(0, 50) : null;
            ligne.name = nomAffiche(ligne.base_name, ligne.prestation_name, ligne.color);
            // Deux lignes devenues identiques n'en font plus qu'une.
            const fusion = [];
            for (const l of lignes) {
                const meme = fusion.find((f) => f.service_id === l.service_id && f.service_variant_id === l.service_variant_id && f.prestation_id === l.prestation_id
                    && f.base_name === l.base_name && f.unit_price_fcfa === l.unit_price_fcfa && f.color === l.color);
                if (meme) meme.quantity = Math.min(this.max, meme.quantity + l.quantity);
                else fusion.push(l);
            }
            this.items = fusion;
        },

        couleurLigneDepuisSelecteur(i, hex) {
            const nom = couleurLaPlusProche(hex, this.palette);
            if (nom) this.couleurLigne(i, nom === this.items[i]?.color ? null : nom);
        },

        vider() {
            this.items = [];
            this.$wire.clearDraft();
        },

        // ── Totaux et paiement ─────────────────────────────────────────────

        get total() {
            return this.items.reduce((s, l) => s + l.unit_price_fcfa * l.quantity, 0);
        },

        get pieces() {
            return this.items.reduce((s, l) => s + l.quantity, 0);
        },

        get payeMaintenant() {
            if (this.paymentChoice === 'full') return this.total;
            if (this.paymentChoice === 'partial') return Math.max(0, parseInt(this.depositAmount, 10) || 0);
            return 0;
        },

        get moitie() {
            return Math.round(this.total / 2 / 100) * 100;
        },

        choisirPaiement(choix) {
            this.paymentChoice = choix;
            if (choix !== 'partial') this.depositAmount = '';
        },

        fcfa(montant) {
            return new Intl.NumberFormat('fr-FR').format(montant).replace(/ | /g, ' ');
        },

        // ── Validation ─────────────────────────────────────────────────────

        get pret() {
            return this.ouvert && this.clientId !== null && this.items.length > 0;
        },

        /** Le bouton dit ce qu'il manque, sinon le montant à valider. */
        get libelleValider() {
            if (!this.ouvert) return 'Pressing fermé';
            if (this.clientId === null) return 'Choisir le client';
            if (this.items.length === 0) return 'Ajouter des pièces';
            return `Valider · ${this.fcfa(this.total)} F`;
        },

        valider() {
            clearTimeout(this.sauvegarde);
            this.$wire.create();
        },

        // ── Clavier et focus ───────────────────────────────────────────────

        isDesktop() {
            return window.matchMedia('(pointer: fine)').matches;
        },

        focus(cible) {
            this.$nextTick(() => {
                const el = cible === 'client' ? this.$refs.clientSearch : (this.$refs.pieceSearch || this.$refs.pieces);
                if (!el) return;
                if (this.isDesktop() && el.focus) el.focus({ preventScroll: true });
                el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            });
        },

        onKey(e) {
            const saisie = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName);
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); this.valider(); return; }
            if (e.key === 'Escape') { this.qty.open = false; this.variantes.open = false; this.autre.open = false; return; }
            if (!saisie && e.key === '/') { e.preventDefault(); this.focus(this.$refs.clientSearch ? 'client' : 'pieces'); }
        },
    }));
});
