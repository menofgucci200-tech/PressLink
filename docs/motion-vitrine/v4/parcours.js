/*
 * Parcours commun aux variantes de la proposition 2 : les écrans derrière les
 * portes (test, inscription en 2 étapes, formules, paiement) et les petites
 * scènes animées des portes. Chaque page décide où les afficher et comment
 * montrer au visiteur où il en est (GPS, chemin, liste de mise en route).
 */
const fcfa = (n) => n.toLocaleString('fr-FR').replace(/ | /g, ' ');
const PLANS = {
    Starter: [20000, '100 commandes / mois'],
    Business: [30000, '200 commandes / mois'],
    Premium: [50000, '350 commandes, plusieurs pressings'],
};
const COCHE = '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

window.Parcours = function ({ conteneur, surChangement = () => {}, compact = false }) {
    const etat = { teste: false, cree: false, paye: false, formule: null, annuel: false, moyen: null, conseil: null, ici: null, ecran: null };

    const ECRANS = {
        tester: () => `
            <h3>Votre pressing fictif en 10 secondes</h3>
            <p class="sous">Rempli d'exemples : articles, prix, clients et commandes. Il s'efface tout seul après 3 jours.</p>
            <div class="${compact ? '' : 'deux'}" style="display:grid;gap:12px">
                <div class="champ"><label for="t-nom">Nom de votre pressing</label><input id="t-nom" placeholder="Ex. Pressing Élégance"></div>
                <div class="champ"><label for="t-tel">Votre numéro WhatsApp</label><input id="t-tel" inputmode="tel" placeholder="07 00 00 00 00"></div>
            </div>
            <button class="btn btn-vert" type="button" data-action="lancer-test">Lancer mon test de 3 jours</button>
            <div class="encart">ⓘ <span>Si vous aimez, vous créez votre vrai espace en gardant vos articles et vos prix.</span></div>`,
        chargementTest: () => `
            <h3>On prépare votre pressing…</h3>
            <p class="sous">Articles, tarifs, 12 commandes d'exemple et la caisse du jour.</p>
            <div class="chargement"><b></b></div>`,
        testPret: () => `
            <h3>Votre pressing fictif est prêt</h3>
            <div class="capture"><img src="img/launch-dashboard.webp" alt="Tableau de bord du pressing fictif" width="1600" height="1000"></div>
            <div class="suite"><div><small>Étape suivante</small><strong>Créer mon vrai espace, en gardant mes articles</strong></div><button class="btn btn-vert" type="button" data-aller="creer">Créer mon espace →</button></div>`,
        creer: () => `
            <h3>Votre pressing</h3>
            <p class="sous">Étape 1 sur 2 · ce que vos clients verront sur leurs reçus.</p>
            <div class="champ"><label for="c-nom">Nom du pressing</label><input id="c-nom" placeholder="Ex. Pressing Élégance"></div>
            <div class="${compact ? '' : 'deux'}" style="display:grid;gap:12px">
                <div class="champ"><label for="c-com">Commune</label><input id="c-com" placeholder="Ex. Cocody"></div>
                <div class="champ"><label for="c-tel">Téléphone du pressing</label><input id="c-tel" inputmode="tel" placeholder="07 00 00 00 00"></div>
            </div>
            <button class="btn btn-bleu" type="button" data-action="creer-2">Suivant : vous →</button>
            ${etat.formule ? `<div class="encart">★ <span>Formule choisie : <b>${etat.formule}</b>. Vous l'activerez juste après.</span></div>` : ''}`,
        creer2: () => `
            <h3>Vous, le gérant</h3>
            <p class="sous">Étape 2 sur 2 · pour vous connecter et vous joindre.</p>
            <div class="${compact ? '' : 'deux'}" style="display:grid;gap:12px">
                <div class="champ"><label for="g-nom">Votre nom</label><input id="g-nom" placeholder="Ex. Awa Koné"></div>
                <div class="champ"><label for="g-tel">Votre numéro</label><input id="g-tel" inputmode="tel" placeholder="07 00 00 00 00"></div>
            </div>
            <div class="champ"><label for="g-mdp">Mot de passe</label><input id="g-mdp" type="password" placeholder="8 caractères minimum"></div>
            <button class="btn btn-vert" type="button" data-action="creer-ok">Créer mon espace</button>`,
        creerOk: () => `
            <div class="grand-ok"><div class="coche">${COCHE}</div>
            <h3>Votre espace est créé</h3><p class="sous" style="margin:0">Notre équipe vous appelle aujourd'hui pour vous aider à démarrer.</p></div>
            <div class="suite"><div><small>Dernière étape</small><strong>Choisir ma formule${etat.formule ? ' · ' + etat.formule : ''}</strong></div><button class="btn btn-vert" type="button" data-aller="formules">Choisir ma formule →</button></div>`,
        formules: () => {
            const carte = (nom) => {
                const [prix, detail] = PLANS[nom];
                const montant = etat.annuel ? prix * 10 : prix;
                const badge = etat.conseil ? (etat.conseil === nom ? '<em class="pour-vous">Pour vous</em>' : '') : (nom === 'Business' ? '<em class="pour-vous">Le plus choisi</em>' : '');
                return `<button class="plan" type="button" data-plan="${nom}" aria-pressed="${etat.formule === nom}">${badge}<strong>${nom}</strong><span class="p">${fcfa(montant)} F <small>/ ${etat.annuel ? 'an' : 'mois'}</small></span><span>${detail}</span></button>`;
            };
            const moyen = (nom, couleur) => `<button type="button" data-moyen="${nom}" aria-pressed="${etat.moyen === nom}"><i style="background:${couleur}"></i>${nom}</button>`;
            let bas = '';
            if (etat.formule && etat.cree) {
                bas = `<div><p style="font-weight:600;margin-bottom:8px">Payer avec</p><div class="moyens">${moyen('Wave', '#1DC8FF')}${moyen('Orange Money', '#FF7900')}${moyen('MTN MoMo', '#FFCB05')}${moyen('Moov Money', '#0055A5')}</div></div>
                    <button class="btn btn-vert" type="button" data-action="payer">Payer ${fcfa(PLANS[etat.formule][0] * (etat.annuel ? 10 : 1))} F${etat.moyen ? ' avec ' + etat.moyen : ''}</button>`;
            } else if (etat.formule) {
                bas = `<div class="suite"><div><small>Pour activer ${etat.formule}</small><strong>Créez d'abord votre espace (2 minutes)</strong></div><button class="btn btn-vert" type="button" data-aller="creer">Créer mon espace →</button></div>`;
            }
            return `
                <h3>Choisissez votre formule</h3>
                <p class="sous">Sans engagement. Vous pouvez changer à tout moment.</p>
                <div class="bascule"><button type="button" data-action="mensuel" aria-pressed="${!etat.annuel}">Mensuel</button><button type="button" data-action="annuel" aria-pressed="${etat.annuel}">Annuel <em>2 mois offerts</em></button></div>
                <div class="plans">${carte('Starter')}${carte('Business')}${carte('Premium')}</div>
                ${bas}`;
        },
        pret: () => `
            <div class="grand-ok"><div class="coche">${COCHE}</div>
            <h3>C'est parti !</h3><p class="sous" style="margin:0">Formule ${etat.formule || 'Business'} activée. Votre tableau de bord vous attend.</p>
            <button class="btn btn-bleu" type="button">Ouvrir mon tableau de bord →</button></div>`,
    };

    function rendre(ecran, ici) {
        etat.ecran = ecran;
        if (ici) { etat.ici = ici; }
        conteneur.innerHTML = `<div class="ecran-parcours">${ECRANS[ecran]()}</div>`;
        surChangement(etat);
    }

    function aller(cible) {
        if (cible === 'tester') { rendre(etat.teste ? 'testPret' : 'tester', 'tester'); }
        if (cible === 'creer') { rendre(etat.cree ? 'creerOk' : 'creer', 'creer'); }
        if (cible === 'formules') { rendre(etat.paye ? 'pret' : 'formules', etat.paye ? 'pret' : 'formules'); }
        if (cible === 'pret') { rendre('pret', 'pret'); }
    }

    function confettis() {
        const box = document.createElement('div');
        box.className = 'confetti';
        const couleurs = ['#2563EB', '#22C55E', '#FBBF24', '#1DC8FF', '#F43F5E'];
        for (let i = 0; i < 70; i++) {
            const c = document.createElement('i');
            c.style.left = Math.random() * 100 + 'vw';
            c.style.background = couleurs[i % couleurs.length];
            c.style.animationDuration = (1.8 + Math.random() * 1.6) + 's';
            c.style.animationDelay = Math.random() * 0.4 + 's';
            box.appendChild(c);
        }
        document.body.appendChild(box);
        setTimeout(() => box.remove(), 4000);
    }

    conteneur.addEventListener('click', (e) => {
        const a = e.target.closest('[data-aller]');
        if (a) { e.preventDefault(); e.stopPropagation(); aller(a.dataset.aller); return; }
        const p = e.target.closest('[data-plan]');
        if (p) { etat.formule = p.dataset.plan; rendre('formules'); return; }
        const m = e.target.closest('[data-moyen]');
        if (m) { etat.moyen = m.dataset.moyen; rendre('formules'); return; }
        const act = e.target.closest('[data-action]');
        if (!act) { return; }
        switch (act.dataset.action) {
            case 'lancer-test':
                rendre('chargementTest');
                setTimeout(() => { etat.teste = true; rendre('testPret', 'tester'); }, 1700);
                break;
            case 'creer-2': rendre('creer2', 'creer'); break;
            case 'creer-ok': etat.cree = true; rendre('creerOk', 'creer'); break;
            case 'mensuel': etat.annuel = false; rendre('formules'); break;
            case 'annuel': etat.annuel = true; rendre('formules'); break;
            case 'payer': etat.paye = true; rendre('pret', 'pret'); confettis(); break;
        }
    });

    function reinitialiser() {
        Object.assign(etat, { teste: false, cree: false, paye: false, formule: null, annuel: false, moyen: null, conseil: null, ici: null, ecran: null });
        conteneur.innerHTML = '';
    }

    return { etat, aller, rendre, reinitialiser };
};

/* ----- Scènes animées des portes ----- */
window.Scenes = {
    test(box) {
        box.innerHTML = '<div class="scene-test"><div class="haut"><span>Pressing (test) · Aujourd\'hui</span><b>0 F</b></div></div>';
        const tdb = box.firstChild, total = tdb.querySelector('b');
        const articles = [1500, 3000, 4500, 2000];
        let tour = 0;
        const jouer = () => {
            tdb.querySelectorAll('.mini-ligne').forEach((l) => l.remove());
            let somme = 0;
            articles.forEach((prix, i) => setTimeout(() => {
                const l = document.createElement('div');
                l.className = 'mini-ligne';
                l.innerHTML = `<i></i><span style="width:${50 + ((i * 23 + tour * 7) % 40)}%"></span><em class="statut recue">Reçue</em>`;
                tdb.appendChild(l);
                somme += prix; total.textContent = fcfa(somme) + ' F';
                setTimeout(() => { const s = l.querySelector('.statut'); s.className = 'statut cours'; s.textContent = 'En cours'; }, 1300);
                setTimeout(() => { const s = l.querySelector('.statut'); s.className = 'statut prete'; s.textContent = 'Prête'; }, 2600 + i * 250);
            }, i * 450));
            tour++;
        };
        jouer();
        return setInterval(jouer, 7000);
    },
    creer(box) {
        box.innerHTML = `<div class="scene-creer"><div class="rail"><span><b></b></span><span><b></b></span></div>
            <div class="champ-anime"></div><div class="champ-anime"></div><div class="champ-anime"></div></div>
            <div class="scene-ok"><i>${COCHE.replace('viewBox', 'width="30" height="30" viewBox')}</i>Espace créé</div>`;
        const champs = box.querySelectorAll('.champ-anime'), rails = box.querySelectorAll('.rail b'), ok = box.querySelector('.scene-ok');
        const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
        const jouer = async () => {
            ok.classList.remove('visible');
            champs.forEach((c) => { c.textContent = ''; });
            rails.forEach((r) => { r.style.width = '0'; });
            const textes = ['Pressing Élégance', 'Cocody, Riviera 2', '07 08 45 12 30'];
            for (let i = 0; i < 3; i++) {
                for (const lettre of textes[i]) { champs[i].textContent += lettre; await attendre(55); }
                if (i === 1) { rails[0].style.width = '100%'; }
            }
            rails[1].style.width = '100%';
            await attendre(500);
            ok.classList.add('visible');
        };
        jouer();
        return setInterval(jouer, 7000);
    },
    formules(box, conseil = () => 'Business') {
        box.innerHTML = `<div class="scene-formules">${['Starter', 'Business', 'Premium'].map((nom, i) => `<div data-f="${nom}"><span class="prix">0</span><div class="b" data-h="${[54, 92, 128][i]}"></div>${nom}</div>`).join('')}</div>`;
        const jouer = () => {
            box.querySelectorAll('.scene-formules > div').forEach((d, i) => {
                const b = d.querySelector('.b'), p = d.querySelector('.prix'), nom = d.dataset.f;
                d.classList.toggle('mise', nom === conseil());
                b.textContent = nom === conseil() ? '★' : '';
                b.style.height = '0'; p.textContent = '0';
                setTimeout(() => {
                    b.style.height = b.dataset.h + 'px';
                    const cible = PLANS[nom][0], t0 = performance.now();
                    const pas = (t) => { const k = Math.min(1, (t - t0) / 900); p.textContent = fcfa(Math.round(cible * (1 - Math.pow(1 - k, 3)) / 500) * 500); if (k < 1) { requestAnimationFrame(pas); } };
                    requestAnimationFrame(pas);
                }, 250 + i * 220);
            });
        };
        jouer();
        return setInterval(jouer, 7000);
    },
};
