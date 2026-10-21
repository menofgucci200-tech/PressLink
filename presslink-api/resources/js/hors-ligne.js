// Comptoir hors connexion : ce qui est gardé sur le PC et l'envoi au serveur.
//
// Gardé dans le navigateur (localStorage, par pressing) :
//  - « donnees » : copie du catalogue, des prix, des clients et des commandes
//    en cours, reprise régulièrement tant qu'on est en ligne ;
//  - « file » : les opérations faites hors connexion (nouveau client, nouvelle
//    commande, encaissement, changement de statut), dans l'ordre, chacune avec
//    un identifiant unique : le serveur ne les applique jamais deux fois ;
//  - « problemes » : ce que le serveur a refusé ou modifié à l'envoi (numéro
//    déjà pris, commande annulée entre-temps…), affiché pour être repris.
//
// Utilisé par la page du comptoir (comptoir.js) et par le tableau de bord
// (app.js), qui envoie la file et rafraîchit la copie dès qu'il est en ligne.

const PREFIX = 'pl-hors-ligne';
const DONNEES_PERIMEES_APRES = 3 * 60 * 1000;

function lire(cle, defaut) {
    try {
        const brut = localStorage.getItem(cle);
        return brut === null ? defaut : JSON.parse(brut);
    } catch {
        return defaut;
    }
}

function ecrire(cle, valeur) {
    localStorage.setItem(cle, JSON.stringify(valeur));
    window.dispatchEvent(new CustomEvent('presslink-hors-ligne', { detail: { cle } }));
}

/** Le serveur répond-il ? (petite requête, jamais servie par le cache.) */
export async function serveurJoignable() {
    try {
        const reponse = await fetch(`/favicon.ico?joignable=${Date.now()}`, { method: 'HEAD', cache: 'no-store', credentials: 'same-origin' });
        return reponse.ok || reponse.status < 500;
    } catch {
        return false;
    }
}

export function uuid() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

export class StockHorsLigne {
    constructor(pressingId, userId) {
        this.pressingId = Number(pressingId);
        this.userId = Number(userId);
    }

    cle(nom) {
        return `${PREFIX}:${this.pressingId}:${nom}`;
    }

    donnees() {
        return lire(this.cle('donnees'), null);
    }

    file() {
        return lire(this.cle('file'), []);
    }

    problemes() {
        return lire(this.cle('problemes'), []);
    }

    ajouter(operation) {
        const complete = { uuid: uuid(), at: new Date().toISOString(), user_id: this.userId, ...operation };
        ecrire(this.cle('file'), [...this.file(), complete]);
        return complete;
    }

    oublierDonnees() {
        localStorage.removeItem(this.cle('donnees'));
    }

    oublierProbleme(index) {
        const liste = this.problemes();
        liste.splice(index, 1);
        ecrire(this.cle('problemes'), liste);
    }

    donneesPerimees() {
        const d = this.donnees();
        return d === null || Date.now() - new Date(d.generated_at).getTime() > DONNEES_PERIMEES_APRES;
    }

    /** Reprend la copie des données depuis le serveur. Renvoie false hors connexion. */
    async rafraichir() {
        try {
            const reponse = await fetch('/comptoir-hors-ligne/donnees', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!reponse.ok) {
                return false;
            }
            const donnees = await reponse.json();
            if (Number(donnees.pressing?.id) !== this.pressingId) {
                return false;
            }
            ecrire(this.cle('donnees'), donnees);
            return true;
        } catch {
            return false;
        }
    }

    /**
     * Envoie la file au serveur. Renvoie { envoyees, refusees, etat } où etat
     * vaut 'ok', 'vide', 'hors-connexion' ou 'reconnexion' (session expirée).
     */
    async envoyer() {
        const verrou = this.cle('envoi');
        const enCours = Number(localStorage.getItem(verrou) || 0);
        if (Date.now() - enCours < 30000) {
            return { envoyees: 0, refusees: 0, etat: 'en-cours' };
        }

        const operations = this.file().filter((op) => op.user_id === this.userId || !op.user_id);
        if (operations.length === 0) {
            return { envoyees: 0, refusees: 0, etat: 'vide' };
        }

        localStorage.setItem(verrou, String(Date.now()));
        try {
            let reponse = await this.poster(operations);
            if (reponse.status === 419) {
                // Jeton expiré : on reprend la copie (qui en apporte un neuf), puis on réessaie.
                await this.rafraichir();
                reponse = await this.poster(operations);
            }
            if (reponse.status === 401 || reponse.status === 419) {
                return { envoyees: 0, refusees: 0, etat: 'reconnexion' };
            }
            if (!reponse.ok) {
                return { envoyees: 0, refusees: 0, etat: 'hors-connexion' };
            }

            const { results } = await reponse.json();
            const parUuid = new Map(results.map((r) => [r.uuid, r]));
            const restantes = [];
            const problemes = this.problemes();
            let envoyees = 0;
            let refusees = 0;

            for (const op of this.file()) {
                const r = parUuid.get(op.uuid);
                if (!r || r.retry) {
                    restantes.push(op);
                    continue;
                }
                if (r.ok) {
                    envoyees++;
                    if (r.renumbered) {
                        problemes.push({ at: new Date().toISOString(), message: `La commande ${op.number} a pris le numéro ${r.order_number} (numéro déjà utilisé). Le reçu imprimé porte l'ancien numéro.` });
                    }
                } else {
                    refusees++;
                    problemes.push({ at: new Date().toISOString(), message: `${decrire(op)} : ${r.message}` });
                }
            }

            ecrire(this.cle('file'), restantes);
            ecrire(this.cle('problemes'), problemes.slice(-50));
            await this.rafraichir();

            return { envoyees, refusees, etat: 'ok' };
        } catch {
            return { envoyees: 0, refusees: 0, etat: 'hors-connexion' };
        } finally {
            localStorage.removeItem(verrou);
        }
    }

    poster(operations) {
        const csrf = this.donnees()?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
        return fetch('/comptoir-hors-ligne/envoyer', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ pressing_id: this.pressingId, operations }),
        });
    }
}

function decrire(op) {
    switch (op.type) {
        case 'client': return `Client ${op.first_name} ${op.last_name}`;
        case 'commande': return `Commande ${op.number}`;
        case 'paiement': return `Encaissement de ${fcfa(op.amount)} F`;
        case 'statut': return `Changement de statut (${LIBELLES_STATUT[op.status] || op.status})`;
        default: return 'Opération';
    }
}

export const LIBELLES_STATUT = {
    recue: 'Reçue',
    traitement: 'En traitement',
    attente: 'En attente',
    prete: 'Prête',
    recuperee: 'Récupérée',
    annulee: 'Annulée',
};

export function fcfa(montant) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(Number(montant) || 0)).replace(/ | /g, ' ');
}

/** Même règle que App\Support\PhoneNumber::normalize. */
export function normaliserTelephone(saisie) {
    const compact = String(saisie || '').trim().replace(/[^\d+]/g, '');
    let chiffres;
    if (compact.startsWith('+')) {
        if (!compact.startsWith('+225')) return compact;
        chiffres = compact.slice(4);
    } else if (compact.startsWith('00225')) {
        chiffres = compact.slice(5);
    } else if (compact.startsWith('225') && compact.length === 13) {
        chiffres = compact.slice(3);
    } else {
        chiffres = compact;
    }
    return /^\d{10}$/.test(chiffres) ? `+225${chiffres}` : compact;
}

export function telephoneValide(telephone) {
    return /^\+225(01|05|07)\d{8}$/.test(telephone) || telephone.startsWith('+22500000000');
}

export function formaterTelephone(telephone) {
    const national = String(telephone || '').replace(/^\+225/, '');
    return /^\d{10}$/.test(national) ? `+225 ${national.match(/.{2}/g).join(' ')}` : telephone;
}

/** Numéro de commande libre (même format que le serveur, PL-123456). */
export function nouveauNumero(dejaPris) {
    let numero;
    do {
        numero = `PL-${String(Math.floor(Math.random() * 999999) + 1).padStart(6, '0')}`;
    } while (dejaPris.has(numero));
    return numero;
}

/**
 * Vue du comptoir : la copie du serveur, à laquelle on applique la file
 * (clients et commandes créés, encaissements, statuts) pour afficher l'état
 * réel au comptoir, même hors connexion.
 */
export function vueCourante(donnees, file) {
    const clients = (donnees?.customers || []).map((c) => ({ ...c, cle: `c${c.id}`, local: false }));
    const commandes = (donnees?.orders || []).map((o) => ({
        ...o,
        cle: `o${o.id}`,
        local: false,
        enAttente: false,
        items: [...o.items],
        payments: [...o.payments],
    }));
    const commandeDe = (op) => commandes.find((o) => (op.order_id ? o.id === op.order_id : o.uuid === op.order_uuid));

    for (const op of file) {
        if (op.type === 'client') {
            clients.push({ id: null, uuid: op.uuid, cle: `u${op.uuid}`, name: `${op.first_name} ${op.last_name}`.trim(), phone: op.phone, local: true });
        } else if (op.type === 'commande') {
            const total = op.items.reduce((s, i) => s + i.unit_price_fcfa * i.quantity, 0);
            commandes.unshift({
                id: null,
                uuid: op.uuid,
                cle: `u${op.uuid}`,
                number: op.number,
                customer_id: op.customer_id ?? null,
                customer_uuid: op.customer_uuid ?? null,
                status: 'recue',
                total,
                paid: op.paid || 0,
                dropped_off_at: op.at,
                expected_at: op.expected_at,
                notes: op.notes,
                items: op.items.map((i) => ({ name: i.name, quantity: i.quantity, unit: i.unit_price_fcfa, subtotal: i.unit_price_fcfa * i.quantity })),
                payments: op.paid > 0 ? [{ amount: op.paid, method: op.method, at: op.at }] : [],
                storage_fees: null,
                local: true,
                enAttente: true,
            });
        } else if (op.type === 'paiement') {
            const commande = commandeDe(op);
            if (commande) {
                commande.paid += op.amount;
                commande.payments.push({ amount: op.amount, method: op.method, at: op.at });
                commande.enAttente = true;
            }
        } else if (op.type === 'statut') {
            const commande = commandeDe(op);
            if (commande) {
                if (op.status === 'recuperee') {
                    if (op.storage_fee > 0) {
                        commande.items.push({ name: 'Droit de magasinage', quantity: 1, unit: op.storage_fee, subtotal: op.storage_fee });
                        commande.total += op.storage_fee;
                    }
                    const reste = commande.total - commande.paid;
                    if (reste > 0) {
                        commande.paid += reste;
                        commande.payments.push({ amount: reste, method: op.method, at: op.at });
                    }
                }
                commande.status = op.status;
                commande.enAttente = true;
            }
        }
    }

    const clientsParCle = new Map();
    for (const c of clients) {
        if (c.id) clientsParCle.set(`id:${c.id}`, c);
        if (c.uuid) clientsParCle.set(`uuid:${c.uuid}`, c);
    }
    for (const o of commandes) {
        o.client = clientsParCle.get(o.customer_id ? `id:${o.customer_id}` : `uuid:${o.customer_uuid}`) || null;
        o.reste = Math.max(0, o.total - o.paid);
    }

    return { clients, commandes };
}

/** Prix d'une pièce (ou variante) pour un service, d'après la copie ; null si non proposé. */
export function prix(donnees, pieceId, varianteId, prestationId) {
    const ligne = (donnees?.prices || []).find(([piece, variante, prestation]) => piece === pieceId
        && (variante ?? null) === (varianteId ?? null) && prestation === prestationId);
    return ligne ? ligne[3] : null;
}

/** Droit de magasinage estimé aujourd'hui (précalculé par le serveur pour les jours à venir). */
export function droitDeMagasinage(commande) {
    if (!commande.storage_fees) return 0;
    const aujourdhui = new Date();
    const cle = `${aujourdhui.getFullYear()}-${String(aujourdhui.getMonth() + 1).padStart(2, '0')}-${String(aujourdhui.getDate()).padStart(2, '0')}`;
    const jours = Object.keys(commande.storage_fees).sort();
    if (commande.storage_fees[cle] !== undefined) return commande.storage_fees[cle];
    // Copie plus ancienne que la période précalculée : dernier montant connu.
    return jours.length && cle > jours[jours.length - 1] ? commande.storage_fees[jours[jours.length - 1]] : 0;
}

function echapper(texte) {
    return String(texte ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function dateCourte(iso, avecHeure = true) {
    const d = new Date(iso);
    const jour = d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    return avecHeure ? `${jour} à ${d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}` : jour;
}

/** Reçu de dépôt (ticket 80 mm), sur le modèle de orders/_receipt-sheet.blade.php. */
export function recuHtml(donnees, commande, libelleMode) {
    const p = donnees.pressing;
    const client = commande.client;
    const lignes = commande.items.map((i) => `<tr><td class="qte">${i.quantity} ×</td><td>${echapper(i.name)}${i.quantity > 1 ? `<br><span class="m p">${fcfa(i.unit)} F l'unité</span>` : ''}</td><td class="d">${fcfa(i.subtotal)} F</td></tr>`).join('');
    const paiements = commande.payments.map((pa) => `<tr><td class="m p">${dateCourte(pa.at, false)} · ${echapper(libelleMode(pa.method))}</td><td class="d p">${fcfa(pa.amount)} F</td></tr>`).join('');
    const etat = commande.reste <= 0 ? 'Payée' : (commande.paid > 0 ? 'Acompte versé' : 'À payer');

    return `<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Reçu ${echapper(commande.number)}</title>
<style>
@page { size: 80mm auto; margin: 4mm; }
body { font: 12px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #000; width: 72mm; margin: 0 auto; }
.c { text-align: center; } .m { color: #444; } .p { font-size: 10.5px; } .b { font-weight: 700; } .d { text-align: right; white-space: nowrap; }
h1 { font-size: 15px; margin: 0 0 2px; } .t { margin: 8px 0 0; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; } .n { font-size: 16px; font-weight: 800; margin: 2px 0 0; }
hr { border: 0; border-top: 1px dashed #000; margin: 8px 0; } table { width: 100%; border-collapse: collapse; font-size: inherit; } td { padding: 1px 0; vertical-align: top; }
.qte { width: 30px; } .tot td { font-size: 14px; font-weight: 800; } .etat { margin: 6px 0; text-align: center; font-weight: 700; border: 1px solid #000; padding: 2px; }
.pied { text-align: center; margin-top: 6px; }
</style></head><body>
<div class="c"><h1>${echapper(p.name)}</h1>${p.address ? `<div class="m p">${echapper(p.address)}</div>` : ''}${p.phone ? `<div class="m p">Tél. ${echapper(p.phone)}</div>` : ''}
<p class="t">Reçu de dépôt</p><p class="n">${echapper(commande.number)}</p></div><hr>
<table><tr><td class="m">Client</td><td class="d b">${echapper(client?.name || '—')}</td></tr>
<tr><td class="m">Téléphone</td><td class="d">${echapper(formaterTelephone(client?.phone || ''))}</td></tr>
<tr><td class="m">Déposé le</td><td class="d">${dateCourte(commande.dropped_off_at)}</td></tr>
${commande.expected_at ? `<tr><td class="m">Retrait prévu</td><td class="d b">${dateCourte(`${commande.expected_at}T12:00:00`, false)}</td></tr>` : ''}
<tr><td class="m">Reçu par</td><td class="d">${echapper(donnees.user.name)}</td></tr></table><hr>
<table>${lignes}</table><hr>
<table><tr class="tot"><td>Total</td><td class="d">${fcfa(commande.total)} F</td></tr></table>
<div class="etat">${etat}</div>
<table>${paiements}<tr><td>Déjà payé</td><td class="d">${fcfa(commande.paid)} F</td></tr>
<tr><td class="b">Reste à payer au retrait</td><td class="d b">${fcfa(commande.reste)} F</td></tr></table>
${commande.notes ? `<p class="p"><span class="b">Note :</span> ${echapper(commande.notes)}</p>` : ''}
${p.terms || p.storage_clause ? `<hr><div class="p"><div class="b">Conditions</div>${p.terms ? `<div class="m" style="white-space:pre-line">${echapper(p.terms)}</div>` : ''}${p.storage_clause ? `<div class="m">${echapper(p.storage_clause)}</div>` : ''}</div>` : ''}
<hr><div class="pied p">Présentez ce reçu pour retirer vos vêtements.<br>Suivez votre commande dans l'application PressLink<br>avec le code pressing <b>${echapper(p.code)}</b>.<br>Merci de votre confiance !</div>
<div class="pied p m">Imprimé le ${dateCourte(new Date().toISOString())}</div>
</body></html>`;
}

/** Imprime un document HTML sans ouvrir de fenêtre (cadre caché). */
export function imprimer(html) {
    const cadre = document.createElement('iframe');
    cadre.setAttribute('aria-hidden', 'true');
    cadre.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    document.body.appendChild(cadre);
    cadre.contentDocument.open();
    cadre.contentDocument.write(html);
    cadre.contentDocument.close();
    setTimeout(() => {
        cadre.contentWindow.focus();
        cadre.contentWindow.print();
        setTimeout(() => cadre.remove(), 2000);
    }, 250);
}
