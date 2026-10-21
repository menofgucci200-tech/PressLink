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

function sansAccents(texte) {
    return String(texte || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('comptoir', (pressingId, userId) => ({
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

        // Nouvelle commande
        recherche: '',
        client: null,
        nouveauClient: { ouvert: false, prenom: '', nom: '', telephone: '', erreur: null },
        prestationId: null,
        varianteDe: null,
        autre: { ouvert: false, nom: '', prix: '' },
        lignes: [],
        dateRetrait: dansJours(2),
        note: '',
        paiement: 'plus_tard',
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
                this.prestationId = this.donnees.prestations[0]?.id ?? null;
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

        libelleStatut(statut) {
            return LIBELLES_STATUT[statut] || statut;
        },

        fcfa,
        formaterTelephone,

        // ── Nouvelle commande ────────────────────────────────────────────

        get clientsTrouves() {
            const q = sansAccents(this.recherche.trim());
            if (q.length < 2) return [];
            const chiffres = q.replace(/\D/g, '');
            return this.vue.clients.filter((c) => sansAccents(c.name).includes(q)
                || (chiffres.length >= 3 && c.phone.replace(/\D/g, '').includes(chiffres))).slice(0, 8);
        },

        choisirClient(c) {
            this.client = { id: c.id, uuid: c.uuid ?? null, name: c.name, phone: c.phone };
            this.recherche = '';
            this.nouveauClient.ouvert = false;
        },

        ouvrirNouveauClient() {
            const saisie = this.recherche.trim();
            this.nouveauClient = { ouvert: true, prenom: '', nom: '', telephone: /\d/.test(saisie) ? saisie : '', erreur: null };
            if (!/\d/.test(saisie)) {
                const [prenom, ...nom] = saisie.split(/\s+/);
                this.nouveauClient.prenom = prenom || '';
                this.nouveauClient.nom = nom.join(' ');
            }
        },

        creerClient() {
            const n = this.nouveauClient;
            const telephone = normaliserTelephone(n.telephone);
            if (!n.prenom.trim() || !n.nom.trim()) {
                n.erreur = 'Indiquez le prénom et le nom du client.';
                return;
            }
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
            const op = this.stock.ajouter({ type: 'client', first_name: n.prenom.trim(), last_name: n.nom.trim(), phone: telephone });
            this.recharger();
            this.choisirClient({ id: null, uuid: op.uuid, name: `${op.first_name} ${op.last_name}`, phone: telephone });
            this.afficher('Client ajouté sur ce PC.');
        },

        get piecesDuService() {
            if (!this.donnees || this.prestationId === null) return [];
            return this.donnees.pieces.map((piece) => {
                const prixBase = prix(this.donnees, piece.id, null, this.prestationId);
                const variantes = piece.variants
                    .map((v) => ({ ...v, prix: prix(this.donnees, piece.id, v.id, this.prestationId) }))
                    .filter((v) => v.prix !== null);
                return { ...piece, prix: prixBase, variantes };
            }).filter((piece) => piece.prix !== null || piece.variantes.length > 0);
        },

        get prestation() {
            return this.donnees?.prestations.find((p) => p.id === this.prestationId) || null;
        },

        quantiteDe(pieceId) {
            return this.lignes.filter((l) => l.service_id === pieceId && l.prestation_id === this.prestationId).reduce((s, l) => s + l.quantity, 0);
        },

        ajouterPiece(piece) {
            if (piece.variantes.length > 0) {
                this.varianteDe = this.varianteDe === piece.id ? null : piece.id;
                return;
            }
            this.ajouterLigne(piece.id, null, piece.name, piece.prix);
        },

        ajouterVariante(piece, variante) {
            this.ajouterLigne(piece.id, variante.id, `${piece.name} · ${variante.name}`, variante.prix);
            this.varianteDe = null;
        },

        ajouterAutre() {
            const prixSaisi = parseInt(this.autre.prix, 10);
            if (!this.autre.nom.trim() || Number.isNaN(prixSaisi) || prixSaisi < 0) return;
            this.ajouterLigne(null, null, this.autre.nom.trim(), prixSaisi);
            this.autre = { ouvert: false, nom: '', prix: '' };
        },

        ajouterLigne(pieceId, varianteId, nomBase, prixUnitaire) {
            const prestation = this.prestation;
            const existante = this.lignes.find((l) => l.service_id === pieceId && l.service_variant_id === varianteId
                && l.prestation_id === prestation.id && l.base === nomBase && l.unit_price_fcfa === prixUnitaire);
            if (existante) {
                existante.quantity = Math.min(999, existante.quantity + 1);
                return;
            }
            this.lignes.push({
                service_id: pieceId,
                service_variant_id: varianteId,
                prestation_id: prestation.id,
                prestation_name: prestation.name,
                base: nomBase,
                name: `${nomBase} · ${prestation.name}`,
                color: null,
                unit_price_fcfa: prixUnitaire,
                quantity: 1,
            });
        },

        changerQuantite(index, delta) {
            const ligne = this.lignes[index];
            ligne.quantity += delta;
            if (ligne.quantity <= 0) this.lignes.splice(index, 1);
        },

        get total() {
            return this.lignes.reduce((s, l) => s + l.unit_price_fcfa * l.quantity, 0);
        },

        get montantPaye() {
            if (!this.peut.pay || this.total === 0) return 0;
            if (this.paiement === 'payee') return this.total;
            if (this.paiement === 'acompte') return Math.max(0, parseInt(this.acompte, 10) || 0);
            return 0;
        },

        enregistrerCommande() {
            this.erreur = null;
            if (!this.client) {
                this.erreur = 'Choisissez d\'abord le client.';
                return;
            }
            if (this.lignes.length === 0) {
                this.erreur = 'Ajoutez au moins une pièce.';
                return;
            }
            if (this.paiement === 'acompte' && (this.montantPaye <= 0 || this.montantPaye >= this.total)) {
                this.erreur = `Pour un acompte, indiquez un montant entre 1 et ${fcfa(this.total - 1)} F.`;
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
            this.derniereCommande = op.uuid;
            this.client = null;
            this.lignes = [];
            this.note = '';
            this.paiement = 'plus_tard';
            this.acompte = '';
            this.dateRetrait = dansJours(2);
            this.synchroniser();
        },

        get commandeEnregistree() {
            return this.derniereCommande ? this.vue.commandes.find((c) => c.uuid === this.derniereCommande) || null : null;
        },

        imprimerRecu(commande) {
            imprimer(recuHtml(this.donnees, commande, (m) => this.libelleMode(m)));
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
