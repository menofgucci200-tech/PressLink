// Comptoir hors connexion (resources/views/comptoir/show.blade.php).
// Fonctionne sans le serveur : tout se lit et s'écrit sur le PC
// (hors-ligne.js), puis part au serveur dès que la connexion revient.

import {
    StockHorsLigne, serveurJoignable, vueCourante, prix, nouveauNumero, normaliserTelephone, telephoneValide,
    formaterTelephone, fcfa, LIBELLES_STATUT, droitDeMagasinage, recuHtml, imprimer,
} from './hors-ligne';

function dansJours(n) {
    const d = new Date();
    d.setDate(d.getDate() + n);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const MAX_QUANTITE = 999;

/** « Chemise · Repassage · Bleu », comme en ligne. */
function nomAffiche(base, service, couleur) {
    return [base, service, couleur].filter((partie) => partie).join(' · ');
}

/** Nom de la pièce seule, à partir du nom affiché. */
function nomDeBase(nom, service, couleur) {
    let resultat = nom;
    for (const suffixe of [couleur, service]) {
        if (suffixe && resultat.endsWith(` · ${suffixe}`)) resultat = resultat.slice(0, -(` · ${suffixe}`).length);
    }
    return resultat;
}

function sansAccents(texte) {
    return String(texte || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('comptoir', (pressingId, userId, icones = {}, couleurs = {}) => ({
        icones,
        couleurs,
        stock: new StockHorsLigne(pressingId, userId),
        donnees: null,
        file: [],
        problemes: [],
        enLigne: navigator.onLine,
        envoiEnCours: false,
        dernierEnvoi: null,
        onglet: 'commande',
        message: null,
        vue: { clients: [], commandes: [] },

        // Nouvelle commande (même parcours que livewire/orders/create.blade.php)
        recherche: '',
        client: null,
        nouveauClient: { ouvert: false, prenom: '', nom: '', telephone: '', erreur: null },
        prestationId: null,
        couleur: null,
        rechercherPiece: '',
        variantesDe: null,
        autre: { ouvert: false, nom: '', prix: '' },
        quantite: { ouvert: false, pieceId: null, nom: '', valeur: '' },
        couleursLigne: null,
        lignes: [],
        dateRetrait: dansJours(2),
        noteOuverte: false,
        note: '',
        paiement: 'later',
        acompte: '',
        mode: 'especes',
        erreur: null,
        derniereCommande: null,

        // Commandes
        filtre: 'en_cours',
        rechercheCommandes: '',
        ouverte: null,
        encaissement: { montant: '', mode: 'especes', erreur: null },
        remise: { mode: 'especes', offrir: false },

        // Clients
        rechercheClients: '',

        init() {
            this.recharger();
            window.addEventListener('presslink-hors-ligne', () => this.recharger());
            window.addEventListener('storage', () => this.recharger());
            window.addEventListener('online', () => { this.enLigne = true; this.synchroniser(); });
            window.addEventListener('offline', () => { this.enLigne = false; });
            // Toutes les 15 s : le retour d'Internet est vu vite, et la file part aussitôt.
            setInterval(() => this.synchroniser(), 15000);
            this.synchroniser();
        },

        recharger() {
            this.donnees = this.stock.donnees();
            this.file = this.stock.file();
            this.problemes = this.stock.problemes();
            this.vue = vueCourante(this.donnees, this.file);
            if (this.donnees && (this.prestationId === null || !this.donnees.prestations.some((p) => p.id === this.prestationId))) {
                // Le service le plus demandé (sinon le premier), comme en ligne.
                const parService = new Map();
                for (const [prestation, , total] of this.donnees.usage || []) {
                    parService.set(prestation, (parService.get(prestation) || 0) + total);
                }
                const tries = [...this.donnees.prestations].sort((a, b) => (parService.get(b.id) || 0) - (parService.get(a.id) || 0));
                this.prestationId = tries[0]?.id ?? null;
            }
        },

        async synchroniser() {
            if (this.envoiEnCours) return;
            this.envoiEnCours = true;
            try {
                this.enLigne = await serveurJoignable();
                if (!this.enLigne) {
                    return;
                }
                const resultat = await this.stock.envoyer();
                if (resultat.etat === 'vide' && this.stock.donneesPerimees()) {
                    await this.stock.rafraichir();
                }
                if (resultat.etat === 'ok') {
                    this.dernierEnvoi = new Date();
                    if (resultat.envoyees > 0) {
                        this.afficher(`${resultat.envoyees} opération(s) envoyée(s) au serveur.`);
                    }
                } else if (resultat.etat === 'reconnexion') {
                    this.afficher('Connexion revenue, mais votre session a expiré : rouvrez PressLink et reconnectez-vous pour envoyer.', 'erreur');
                }
            } finally {
                this.envoiEnCours = false;
                this.recharger();
            }
        },

        afficher(texte, type = 'ok') {
            this.message = { texte, type };
            clearTimeout(this._message);
            this._message = setTimeout(() => { this.message = null; }, 6000);
        },

        get peut() {
            return this.donnees?.user?.can || {};
        },

        libelleMode(valeur) {
            return this.donnees?.payment_methods.find((m) => m.value === valeur)?.label || valeur;
        },

        styleStatut(statut) {
            const couleurs = {
                recue: ['var(--color-info-tint)', 'var(--color-info)'],
                traitement: ['color-mix(in srgb, var(--color-secondary) 16%, transparent)', 'var(--color-secondary)'],
                prete: ['var(--color-success-tint)', 'var(--color-success-text)'],
                recuperee: ['var(--color-border)', 'var(--color-text-secondary)'],
                attente: ['var(--color-warning-tint)', 'var(--color-warning-text)'],
                annulee: ['var(--color-error-tint)', 'var(--color-error)'],
            }[statut] || ['var(--color-border)', 'var(--color-text-secondary)'];
            return `background:${couleurs[0]};color:${couleurs[1]}`;
        },

        libelleStatut(statut) {
            return LIBELLES_STATUT[statut] || statut;
        },

        fcfa,
        formaterTelephone,

        // ── Nouvelle commande ────────────────────────────────────────────

        icone(type, cle, taille = 40) {
            const svg = this.icones[type]?.[cle] || this.icones[type]?.autre || '';
            return `<span class="inline-flex flex-none items-center justify-center rounded-2xl bg-white ring-1 ring-black/5" style="width:${Math.round(taille * 1.28)}px;height:${Math.round(taille * 1.28)}px">${svg.replace(/width="40" height="40"/, `width="${taille}" height="${taille}"`)}</span>`;
        },

        initiales(nom) {
            return String(nom || '').split(/\s+/).filter(Boolean).slice(0, 2).map((m) => m[0].toUpperCase()).join('');
        },

        get clientsTrouves() {
            const q = sansAccents(this.recherche.trim());
            if (q === '') return [];
            const chiffres = q.replace(/\D/g, '');
            return this.vue.clients.filter((c) => sansAccents(c.name).includes(q)
                || (chiffres.length >= 3 && c.phone.replace(/\D/g, '').includes(chiffres))).slice(0, 6);
        },

        choisirClient(c) {
            this.client = { id: c.id, uuid: c.uuid ?? null, name: c.name, phone: c.phone };
            this.recherche = '';
            this.nouveauClient.ouvert = false;
        },

        choisirPremierClient() {
            if (this.clientsTrouves.length) this.choisirClient(this.clientsTrouves[0]);
        },

        ouvrirNouveauClient() {
            const saisie = this.recherche.trim();
            this.nouveauClient = { ouvert: true, prenom: '', nom: '', telephone: /\d/.test(saisie) ? saisie : '', erreur: null };
            if (saisie && !/\d/.test(saisie)) {
                const [prenom, ...nom] = saisie.split(/\s+/);
                this.nouveauClient.prenom = prenom || '';
                this.nouveauClient.nom = nom.join(' ');
            }
        },

        creerClient() {
            const n = this.nouveauClient;
            const telephone = normaliserTelephone(n.telephone);
            if (!telephoneValide(telephone)) {
                n.erreur = 'Entrez un numéro de mobile ivoirien : il commence par 01, 05 ou 07.';
                return;
            }
            const existant = this.vue.clients.find((c) => c.phone === telephone);
            if (existant) {
                this.choisirClient(existant);
                this.afficher(`${existant.name} est déjà dans vos clients : il a été choisi.`);
                return;
            }
            if (!n.prenom.trim() || !n.nom.trim()) {
                n.erreur = 'Indiquez le prénom et le nom du client.';
                return;
            }
            const op = this.stock.ajouter({ type: 'client', first_name: n.prenom.trim(), last_name: n.nom.trim(), phone: telephone });
            this.recharger();
            this.choisirClient({ id: null, uuid: op.uuid, name: `${op.first_name} ${op.last_name}`, phone: telephone });
        },

        /** Pièces de la dernière commande du client (au prix actuel), comme en ligne. */
        get derniereDuClient() {
            if (!this.client?.id) return null;
            const lignes = this.donnees?.last_orders?.[this.client.id] || [];
            return lignes.length ? lignes : null;
        },

        refaireDerniereCommande() {
            const pieces = new Map(this.donnees.pieces.map((p) => [p.id, p]));
            const prestations = new Map(this.donnees.prestations.map((p) => [p.id, p]));
            for (const [pieceId, varianteId, prestationId, nomService, nom, couleur, prixAncien, quantite] of this.derniereDuClient) {
                const piece = pieceId ? pieces.get(pieceId) : null;
                const variante = varianteId ? piece?.variants.find((v) => v.id === varianteId) : null;
                const prestation = prestationId ? prestations.get(prestationId) : null;
                // Pièce, variante ou service retirés depuis : la ligne n'est pas reprise.
                if ((pieceId && !piece) || (varianteId && !variante) || (prestationId && !prestation)) continue;
                const prixActuel = piece && prestation ? (prix(this.donnees, piece.id, variante?.id ?? null, prestation.id) ?? prixAncien) : prixAncien;
                const base = piece ? `${piece.name}${variante ? ` · ${variante.name}` : ''}` : nomDeBase(nom, nomService, couleur);
                this.ajouterLigne(piece?.id ?? null, variante?.id ?? null, prestation?.id ?? prestationId, prestation?.name ?? nomService, base, prixActuel, couleur, quantite);
            }
            this.afficher('Dernière commande recopiée : ajustez si besoin.');
        },

        get prestation() {
            return this.donnees?.prestations.find((p) => p.id === this.prestationId) || null;
        },

        get piecesDuService() {
            if (!this.donnees || this.prestationId === null) return [];
            const usage = new Map((this.donnees.usage || []).filter(([prestation]) => prestation === this.prestationId).map(([, piece, total]) => [piece, total]));
            return [...this.donnees.pieces].sort((a, b) => (usage.get(b.id) || 0) - (usage.get(a.id) || 0)).map((piece) => {
                const prixBase = prix(this.donnees, piece.id, null, this.prestationId);
                const variantes = piece.variants
                    .map((v) => ({ ...v, prix: prix(this.donnees, piece.id, v.id, this.prestationId) }))
                    .filter((v) => v.prix !== null);
                const aPartirDe = variantes.length ? Math.min(...variantes.map((v) => v.prix)) : prixBase;
                return { ...piece, prix: prixBase, variantes, aPartirDe, cherche: sansAccents(piece.name) };
            }).filter((piece) => piece.prix !== null || piece.variantes.length > 0);
        },

        pieceVisible(piece) {
            return this.rechercherPiece === '' || piece.cherche.includes(sansAccents(this.rechercherPiece.trim()));
        },

        quantiteDe(pieceId) {
            return this.lignes.filter((l) => l.service_id === pieceId && l.prestation_id === this.prestationId).reduce((s, l) => s + l.quantity, 0);
        },

        quantitePinceau(pieceId) {
            return this.lignes.find((l) => l.service_id === pieceId && l.service_variant_id === null
                && l.prestation_id === this.prestationId && l.color === this.couleur)?.quantity || 0;
        },

        ajouterPiece(piece) {
            if (piece.variantes.length > 0) {
                this.variantesDe = piece;
                return;
            }
            this.ajouterLigne(piece.id, null, this.prestation.id, this.prestation.name, piece.name, piece.prix, this.couleur);
        },

        ajouterPremierePiece() {
            const piece = this.piecesDuService.find((p) => this.pieceVisible(p));
            if (piece) {
                this.ajouterPiece(piece);
                this.rechercherPiece = '';
            }
        },

        ajouterVariante(variante) {
            const piece = this.variantesDe;
            this.ajouterLigne(piece.id, variante.id, this.prestation.id, this.prestation.name, `${piece.name} · ${variante.name}`, variante.prix, this.couleur);
            this.variantesDe = null;
        },

        retirerPiece(pieceId) {
            for (let i = this.lignes.length - 1; i >= 0; i--) {
                const l = this.lignes[i];
                if (l.service_id === pieceId && l.prestation_id === this.prestationId && l.service_variant_id === null) {
                    this.changerQuantite(i, l.quantity - 1);
                    return;
                }
            }
        },

        ouvrirQuantite(piece) {
            this.quantite = { ouvert: true, pieceId: piece.id, nom: piece.name + (this.couleur ? ` · ${this.couleur}` : ''), valeur: String(this.quantitePinceau(piece.id) || '') };
            this.$nextTick(() => this.$refs.quantite?.select());
        },

        validerQuantite() {
            const n = Math.max(0, Math.min(MAX_QUANTITE, parseInt(this.quantite.valeur, 10) || 0));
            const piece = this.piecesDuService.find((p) => p.id === this.quantite.pieceId);
            this.quantite.ouvert = false;
            if (!piece || piece.prix === null) return;
            const index = this.trouverLigne(piece.id, null, this.prestation.id, piece.name, piece.prix, this.couleur);
            if (index === -1) {
                if (n > 0) this.ajouterLigne(piece.id, null, this.prestation.id, this.prestation.name, piece.name, piece.prix, this.couleur, n);
            } else {
                this.changerQuantite(index, n);
            }
        },

        ajouterAutre() {
            const prixSaisi = parseInt(this.autre.prix, 10);
            if (!this.autre.nom.trim() || Number.isNaN(prixSaisi) || prixSaisi < 0) return;
            this.ajouterLigne(null, null, this.prestation.id, this.prestation.name, this.autre.nom.trim(), prixSaisi, this.couleur);
            this.autre = { ouvert: false, nom: '', prix: '' };
        },

        trouverLigne(pieceId, varianteId, prestationId, base, prixUnitaire, couleur) {
            return this.lignes.findIndex((l) => l.service_id === pieceId && l.service_variant_id === varianteId
                && l.prestation_id === prestationId && l.base === base && l.unit_price_fcfa === prixUnitaire && l.color === couleur);
        },

        ajouterLigne(pieceId, varianteId, prestationId, nomService, base, prixUnitaire, couleur = null, quantite = 1) {
            const index = this.trouverLigne(pieceId, varianteId, prestationId, base, prixUnitaire, couleur);
            if (index !== -1) {
                this.lignes[index].quantity = Math.min(MAX_QUANTITE, this.lignes[index].quantity + quantite);
                return;
            }
            this.lignes.push({
                service_id: pieceId,
                service_variant_id: varianteId,
                prestation_id: prestationId,
                prestation_name: nomService,
                base,
                name: nomAffiche(base, nomService, couleur),
                color: couleur,
                unit_price_fcfa: prixUnitaire,
                quantity: Math.min(MAX_QUANTITE, quantite),
            });
        },

        changerQuantite(index, quantite) {
            if (quantite <= 0) {
                this.lignes.splice(index, 1);
                return;
            }
            this.lignes[index].quantity = Math.min(MAX_QUANTITE, quantite);
        },

        // Couleurs : le pinceau (prochaines pièces) et la couleur d'une ligne.
        hexDe(nom) {
            return nom ? this.couleurs.all?.[nom] || null : null;
        },

        fondCouleur(nom) {
            if (nom === this.couleurs.multi) return 'background: conic-gradient(#dc2626, #facc15, #16a34a, #2563eb, #7c3aed, #dc2626)';
            const hex = this.hexDe(nom);
            return hex ? `background-color: ${hex}` : '';
        },

        couleurProche(hex) {
            const m = /^#?([0-9a-f]{6})$/i.exec(String(hex).trim());
            if (!m) return null;
            const rgb = (h) => [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
            const [r, g, b] = rgb(m[1]);
            let meilleur = null;
            let distanceMin = Infinity;
            for (const [nom, candidat] of Object.entries(this.couleurs.all || {})) {
                const [cr, cg, cb] = rgb(candidat.replace('#', ''));
                const rouge = (r + cr) / 2;
                const distance = (2 + rouge / 256) * (r - cr) ** 2 + 4 * (g - cg) ** 2 + (2 + (255 - rouge) / 256) * (b - cb) ** 2;
                if (distance < distanceMin) {
                    distanceMin = distance;
                    meilleur = nom;
                }
            }
            return meilleur;
        },

        choisirCouleur(nom) {
            this.couleur = this.couleur === nom ? null : nom;
        },

        couleurDeLigne(index, nom) {
            const ligne = this.lignes[index];
            ligne.color = ligne.color === nom ? null : nom;
            ligne.name = nomAffiche(ligne.base, ligne.prestation_name, ligne.color);
            // Deux lignes devenues identiques n'en font plus qu'une.
            const fusion = new Map();
            for (const l of this.lignes) {
                const cle = [l.service_id, l.service_variant_id, l.prestation_id, l.base, l.unit_price_fcfa, l.color].join('|');
                if (fusion.has(cle)) fusion.get(cle).quantity = Math.min(MAX_QUANTITE, fusion.get(cle).quantity + l.quantity);
                else fusion.set(cle, l);
            }
            this.lignes = [...fusion.values()];
        },

        viderTicket() {
            this.lignes = [];
        },

        get nombrePieces() {
            return this.lignes.reduce((s, l) => s + l.quantity, 0);
        },

        get total() {
            return this.lignes.reduce((s, l) => s + l.unit_price_fcfa * l.quantity, 0);
        },

        get moitie() {
            return Math.round(this.total / 2 / 100) * 100;
        },

        get montantPaye() {
            if (!this.peut.pay || this.total === 0) return 0;
            if (this.paiement === 'full') return this.total;
            if (this.paiement === 'partial') return Math.max(0, parseInt(this.acompte, 10) || 0);
            return 0;
        },

        get pret() {
            return this.client !== null && this.lignes.length > 0;
        },

        get libelleValider() {
            if (this.client === null) return 'Choisir le client';
            if (this.lignes.length === 0) return 'Ajouter des pièces';
            return `Valider · ${fcfa(this.total)} F`;
        },

        enregistrerCommande() {
            this.erreur = null;
            if (!this.client) {
                this.afficher('Choisissez d\'abord le client.', 'erreur');
                this.$refs.rechercheClient?.focus();
                return;
            }
            if (this.lignes.length === 0) {
                this.afficher('Ajoutez au moins une pièce.', 'erreur');
                return;
            }
            if (this.paiement === 'partial' && (this.montantPaye <= 0 || this.montantPaye >= this.total)) {
                this.erreur = `Pour un acompte, le montant doit rester inférieur au total (${fcfa(this.total)} F). Sinon, choisissez « Payée ».`;
                return;
            }

            const numeros = new Set(this.vue.commandes.map((c) => c.number));
            const op = this.stock.ajouter({
                type: 'commande',
                number: nouveauNumero(numeros),
                customer_id: this.client.id,
                customer_uuid: this.client.id ? null : this.client.uuid,
                items: this.lignes.map(({ base, ...ligne }) => ligne),
                expected_at: this.dateRetrait || null,
                notes: this.note.trim() || null,
                paid: this.montantPaye,
                method: this.mode,
            });
            this.recharger();
            // L'écran se remet à zéro, prêt pour le client suivant.
            this.derniereCommande = op.uuid;
            this.client = null;
            this.lignes = [];
            this.couleur = null;
            this.note = '';
            this.noteOuverte = false;
            this.paiement = 'later';
            this.acompte = '';
            this.mode = 'especes';
            this.dateRetrait = dansJours(2);
            this.$nextTick(() => this.$refs.rechercheClient?.focus());
            this.synchroniser();
        },

        get commandeEnregistree() {
            return this.derniereCommande ? this.vue.commandes.find((c) => c.uuid === this.derniereCommande) || null : null;
        },

        imprimerRecu(commande) {
            imprimer(recuHtml(this.donnees, commande, (m) => this.libelleMode(m)));
        },

        clavier(e) {
            const saisie = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName);
            if (this.onglet !== 'commande') return;
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                this.enregistrerCommande();
            } else if (e.key === 'Escape') {
                this.quantite.ouvert = false;
                this.variantesDe = null;
                this.autre.ouvert = false;
            } else if (!saisie && e.key === '/') {
                e.preventDefault();
                (this.$refs.rechercheClient && !this.client ? this.$refs.rechercheClient : this.$refs.recherchePiece)?.focus();
            }
        },

        // ── Commandes ────────────────────────────────────────────────────

        get commandesFiltrees() {
            const q = sansAccents(this.rechercheCommandes.trim());
            return this.vue.commandes.filter((c) => {
                const statutOk = this.filtre === 'toutes'
                    || (this.filtre === 'en_cours' && ['recue', 'traitement', 'attente'].includes(c.status))
                    || (this.filtre === 'pretes' && c.status === 'prete')
                    || (this.filtre === 'recuperees' && c.status === 'recuperee');
                return statutOk && (q === '' || sansAccents(`${c.number} ${c.client?.name || ''} ${c.client?.phone || ''}`).includes(q));
            });
        },

        compter(filtre) {
            const statuts = { en_cours: ['recue', 'traitement', 'attente'], pretes: ['prete'], recuperees: ['recuperee'] }[filtre];
            return statuts ? this.vue.commandes.filter((c) => statuts.includes(c.status)).length : this.vue.commandes.length;
        },

        get commandeOuverte() {
            return this.ouverte ? this.vue.commandes.find((c) => c.cle === this.ouverte) || null : null;
        },

        ouvrir(commande) {
            this.ouverte = this.ouverte === commande.cle ? null : commande.cle;
            this.encaissement = { montant: commande.reste > 0 ? String(commande.reste) : '', mode: 'especes', erreur: null };
            this.remise = { mode: 'especes', offrir: false };
        },

        reference(commande) {
            return commande.id ? { order_id: commande.id } : { order_uuid: commande.uuid };
        },

        changerStatut(commande, statut) {
            this.stock.ajouter({ type: 'statut', status: statut, ...this.reference(commande) });
            this.recharger();
            this.afficher(`${commande.number} : ${this.libelleStatut(statut)}.`);
            this.synchroniser();
        },

        encaisser(commande) {
            const montant = parseInt(this.encaissement.montant, 10) || 0;
            if (montant <= 0 || montant > commande.reste) {
                this.encaissement.erreur = `Le montant doit être entre 1 et ${fcfa(commande.reste)} F.`;
                return;
            }
            this.stock.ajouter({ type: 'paiement', amount: montant, method: this.encaissement.mode, ...this.reference(commande) });
            this.recharger();
            this.afficher(`${fcfa(montant)} F encaissés sur ${commande.number}.`);
            this.encaissement = { montant: '', mode: 'especes', erreur: null };
            this.synchroniser();
        },

        droitDe(commande) {
            return droitDeMagasinage(commande);
        },

        aPayerALaRemise(commande) {
            const droit = this.remise.offrir ? 0 : this.droitDe(commande);
            return commande.reste + droit;
        },

        remettre(commande) {
            const droit = this.remise.offrir ? 0 : this.droitDe(commande);
            this.stock.ajouter({
                type: 'statut',
                status: 'recuperee',
                method: this.remise.mode,
                waive_storage_fee: this.remise.offrir,
                storage_fee: droit,
                ...this.reference(commande),
            });
            this.recharger();
            this.afficher(`${commande.number} remise au client.`);
            this.ouverte = null;
            this.synchroniser();
        },

        // ── Clients ──────────────────────────────────────────────────────

        get clientsFiltres() {
            const q = sansAccents(this.rechercheClients.trim());
            const chiffres = q.replace(/\D/g, '');
            return this.vue.clients.filter((c) => q === '' || sansAccents(c.name).includes(q)
                || (chiffres.length >= 3 && c.phone.replace(/\D/g, '').includes(chiffres))).slice(0, 200);
        },

        commandesDu(client) {
            return this.vue.commandes.filter((c) => c.client === client).length;
        },

        nouvelleCommandePour(client) {
            this.choisirClient(client);
            this.onglet = 'commande';
        },

        dateCourte(iso) {
            return iso ? new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : '';
        },

        heureDonnees() {
            return this.donnees ? new Date(this.donnees.generated_at).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
        },
    }));
});
