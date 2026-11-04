// Tournage du parcours pressing, sur une copie de la base (serveur api-capture, port 8001) :
// le site, le test, l'inscription, la mise en place, une commande de bout en bout, le pilotage.
// Chaque réplique dure au moins le temps de sa voix ; les préparations se font hors champ.
//
//   PW=<node_modules avec playwright-core> node tourner.mjs <url> <repliques.json> <sortie> [apercu]
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
import { camera, preparer } from '../outils/camera-pro.mjs';

const { chromium } = createRequire(process.env.PW + '/')('playwright-core');
const [URL, repliquesPath, sortie, modeApercu] = process.argv.slice(2);
const voix = Object.fromEntries(JSON.parse(fs.readFileSync(repliquesPath, 'utf8')).map((r) => [r.cle, r.duree]));
const APERCU = modeApercu === 'apercu';
fs.mkdirSync(path.join(sortie, 'controle'), { recursive: true });

// Valeurs de test (pressing fictif, base de tournage locale).
const PRESSING = { nom: 'Pressing La Perle', commune: 'Cocody Angré', tel: '0700112233' };
const GERANT = { nom: 'Awa Kouassi', tel: '0700112233', mdp: 'LaPerle2026' };
const CLIENT = { tel: '0778451236', prenom: 'Aïcha', nom: 'Koné' };
const EMPLOYE = { nom: 'Koffi Yao', login: 'koffi.yao', mdp: 'Koffi2026' };

const navigateur = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
const nouveauContexte = async () => {
    const ctx = await navigateur.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, locale: 'fr-FR', colorScheme: 'light' });
    await ctx.addInitScript(() => { try { localStorage.setItem('presslink-theme', 'light'); } catch {} });
    await preparer(ctx);
    const page = await ctx.newPage();
    page.on('pageerror', (e) => console.log('ERREUR page', e.message.slice(0, 160)));
    return page;
};

let page = await nouveauContexte();
await page.goto(URL + '/');
await page.waitForTimeout(1500);
const cam = await camera(page, sortie);
const adresse = (u) => { try { const x = new globalThis.URL(u); return 'presslink.org' + (x.pathname === '/' ? '' : x.pathname); } catch { return 'presslink.org'; } };
const suivreAdresse = (p) => p.on('framenavigated', (f) => { if (f === p.mainFrame()) cam.noter('url', { url: adresse(f.url()) }); });
suivreAdresse(page);
cam.noter('url', { url: adresse(page.url()) });

let numero = 0;
const controle = async (nom) => { if (APERCU) await page.screenshot({ path: path.join(sortie, 'controle', `${String(++numero).padStart(2, '0')}-${nom}.jpg`), type: 'jpeg', quality: 70 }); };
const echec = async (e) => { console.log('ÉCHEC', String(e?.message ?? e).split('\n').slice(0, 3).join(' | ')); await page.screenshot({ path: path.join(sortie, 'controle', 'echec.jpg') }).catch(() => {}); process.exit(1); };
process.on('unhandledRejection', echec);
process.on('uncaughtException', echec);

async function replique(cle, actions = async () => {}, marge = 0.6) {
    cam.noter('replique', { cle });
    const t0 = Date.now();
    await actions();
    const reste = ((voix[cle] ?? 0) + marge) * 1000 - (Date.now() - t0);
    if (reste > 0) await page.waitForTimeout(reste);
    await controle(cle);
    console.log('✓', cle);
}
async function chapitre(titre, sous, court, duree = 2.8) {
    cam.noter('chapitre', { titre, sous, court, duree });
    await page.waitForTimeout(duree * 1000);
}
const horsChamp = async (actions) => { await cam.pause(); await actions(); await cam.reprendre(page); };
const bouton = (nom) => page.getByRole('button', { name: nom }).first();
const lien = (nom) => page.getByRole('link', { name: nom }).first();
// Navigation filmée par le menu latéral (wire:navigate, sans écran blanc).
async function menu(nom) {
    cam.plein();
    await cam.attendre(700);
    await cam.cliquer(page.getByRole('link', { name: nom, exact: true }).first(), 200);
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(700);
    cam.plein();
}
// Hors champ : une commande de plus, pour que le tableau de bord, la liste et la caisse soient parlants.
async function commandeRapide({ tel, prenom, nom }, pieces, paiement, methode, etapes) {
    await page.goto(URL + '/commandes/nouvelle'); await page.waitForTimeout(1200);
    await page.locator('#client-search').fill(tel); await page.waitForTimeout(900);
    await page.locator('[data-guide="commande-client"]').getByRole('button', { name: /Créer «|Nouveau client/ }).click(); await page.waitForTimeout(800);
    await page.getByPlaceholder('Prénom').fill(prenom); await page.getByPlaceholder('Nom', { exact: true }).fill(nom);
    await bouton(/Créer et choisir ce client/).click(); await page.waitForTimeout(1200);
    const zone = page.locator('[data-guide="commande-pieces"]');
    for (const [service, piece, n] of pieces) {
        await zone.getByRole('tab', { name: new RegExp(service) }).click(); await page.waitForTimeout(300);
        for (let i = 0; i < n; i++) { await zone.locator('button[data-add]:visible', { hasText: piece }).first().click(); await page.waitForTimeout(150); }
    }
    const ticket = page.locator('[data-guide="commande-ticket"]');
    if (paiement) {
        await ticket.getByRole('radio', { name: new RegExp(paiement) }).first().click(); await page.waitForTimeout(300);
        if (paiement === 'Acompte') { await ticket.getByRole('button', { name: /La moitié/ }).first().click(); await page.waitForTimeout(200); }
        await ticket.getByRole('button', { name: new RegExp('^' + methode) }).first().click(); await page.waitForTimeout(200);
    }
    await page.locator('[data-guide="commande-valider"]:visible').first().click(); await page.waitForTimeout(1800);
    await lien(/Voir la commande/).click(); await page.waitForURL(/commandes\/\d+/); await page.waitForTimeout(1000);
    for (const e of etapes) { await bouton(e).click(); await page.waitForTimeout(1300); }
}

// ---------------------------------------------------------------- 1. Découvrir
await chapitre('Découvrir PressLink', 'Le site, et vos trois choix', 'Découvrir');
await replique('intro', async () => { await cam.glisser(980, 560, 900); });
const cartes = page.locator('a.acc-carte');
await replique('site1', async () => {
    await cam.cadrer(cartes.first().locator('xpath=..'), 1.3);
    await cam.survoler(cartes.nth(0));
});
await replique('site2', async () => {
    for (let i = 0; i < 3; i++) { await cam.survoler(cartes.nth(i), 700); await cam.attendre(i === 2 ? 600 : 1000); }
});

// ---------------------------------------------------------------- 2. Tester
await cam.cliquer(cartes.nth(0), 300);
await page.waitForURL(/tester/);
await page.waitForTimeout(800);
cam.plein();
await chapitre('Tester sans engagement', 'Un pressing fictif, pendant 3 jours', 'Tester');
await replique('test1', async () => {
    await cam.cadrer(page.locator('#pressingName').locator('xpath=ancestor::form[1]'), 1.5);
    await cam.cliquer(page.locator('#pressingName'), 150);
    await cam.saisir(PRESSING.nom);
    await cam.cliquer(page.locator('#phone'), 150);
    await cam.saisir('07 00 11 22 33', 60);
}, 0.2);
await cam.cliquer(bouton(/Lancer mon test/), 200);
await page.waitForURL(/tableau-de-bord/, { timeout: 30000 });
await page.waitForTimeout(1200);
cam.plein();
await replique('test2', async () => {
    await cam.attendre(1500);
    await cam.defiler(500, 1600);
    await cam.attendre(800);
    await cam.defiler(-500, 1200);
});
await replique('test3', async () => {
    const bandeau = page.getByText(/Pressing de test/).first();
    await cam.cadrer(bandeau.locator('xpath=ancestor::div[1]'), 1.8);
    await cam.survoler(page.getByRole('link', { name: /Créer mon vrai compte/ }).first());
});

// ---------------------------------------------------------------- 3. Créer son pressing (nouvel onglet, sans le test)
await cam.pause();
page = await nouveauContexte();
suivreAdresse(page);
await page.goto(URL + '/inscription');
await page.waitForTimeout(1500);
await cam.reprendre(page);
cam.noter('url', { url: adresse(page.url()) });
cam.plein();
await chapitre('Créer votre pressing', 'En deux minutes, sans engagement', 'Inscription');
await replique('insc1', async () => {
    await cam.cadrer(page.locator('#pressingName').locator('xpath=ancestor::form[1]'), 1.5);
    await cam.cliquer(page.locator('#pressingName'), 150); await cam.saisir(PRESSING.nom);
    await cam.cliquer(page.locator('#city'), 150); await cam.saisir(PRESSING.commune);
    await cam.cliquer(page.locator('#pressingPhone'), 150); await cam.saisir('07 00 11 22 33', 60);
}, 0.2);
await cam.cliquer(bouton(/^Continuer/), 900);
await replique('insc2', async () => {
    await cam.cadrer(page.locator('#ownerName').locator('xpath=ancestor::form[1]'), 1.4);
    await cam.cliquer(page.locator('#ownerName'), 150); await cam.saisir(GERANT.nom);
    await cam.cliquer(page.locator('#password'), 150); await cam.saisir(GERANT.mdp, 55);
    await cam.cliquer(page.locator('input[type="checkbox"][wire\\:model="acceptTerms"]'), 200);
}, 0.2);
await cam.cliquer(bouton(/Créer mon espace pressing/), 300);
await page.waitForURL(/tableau-de-bord/, { timeout: 30000 });
await page.waitForTimeout(1500);
cam.plein();
await replique('insc3', async () => {
    await cam.attendre(1200);
    await cam.cadrer(page.locator('[data-testid="onboarding-guide"]'), 1.5);
    await cam.survoler(page.locator('[data-testid="onboarding-guide"]').getByText(/Me guider/).first());
});

// ---------------------------------------------------------------- 4. Préparer son espace
await page.goto(URL + '/services');
await page.waitForTimeout(1200);
cam.plein();
await chapitre('Préparer votre espace', 'Prix, paramètres et équipe', 'Mise en place');
await replique('conf1', async () => {
    await cam.cliquer(page.locator('[data-guide="ajouter-piece"]'), 700);
    const nom = page.locator('[data-guide="piece-nom"] input');
    await cam.cadrer(nom.locator('xpath=ancestor::form[1]'), 1.5);
    await cam.cliquer(nom, 150); await cam.saisir('Chemise');
    const prix = page.locator('[data-guide="piece-prix"] input');
    await cam.cliquer(prix.nth(0), 150); await cam.saisir('500', 90);
    await cam.cliquer(prix.nth(1), 150); await cam.saisir('1000', 90);
}, 0.2);
await cam.cliquer(page.locator('[data-guide="piece-enregistrer"]'), 1200);
// Hors champ : le reste du catalogue (même formulaire).
await horsChamp(async () => {
    for (const [nom, rep, net] of [['Pantalon', '500', '1000'], ['Robe', '700', '1500'], ['Costume', '1500', '4000'], ['Boubou', '1000', '2500']]) {
        await page.locator('[data-guide="ajouter-piece"]').click(); await page.waitForTimeout(600);
        await page.locator('[data-guide="piece-nom"] input').fill(nom);
        const prix = page.locator('[data-guide="piece-prix"] input');
        await prix.nth(0).fill(rep); await prix.nth(1).fill(net);
        await page.locator('[data-guide="piece-enregistrer"]').click(); await page.waitForTimeout(1300);
    }
});
cam.plein();
await replique('conf2', async () => {
    await cam.attendre(600);
    await cam.defiler(300, 1200);
    await cam.survoler(page.locator('input[aria-label^="Prix de Chemise"]:visible').first());
});
await replique('conf3', async () => {
    await menu('Paramètres');
    await cam.defiler(700, 2200);
    await cam.attendre(600);
    await cam.defiler(600, 1800);
});
await replique('conf4', async () => {
    await menu('Équipe');
    await cam.cliquer(page.locator('[data-guide="ajouter-membre"]'), 700);
    const nom = page.locator('[data-guide="membre-identite"] input');
    await cam.cadrer(nom.locator('xpath=ancestor::form[1]'), 1.4);
    await cam.cliquer(nom, 150); await cam.saisir(EMPLOYE.nom);
    await cam.cliquer(page.locator('[data-guide="membre-acces"] input'), 150); await page.keyboard.press('ControlOrMeta+a'); await cam.saisir(EMPLOYE.login, 60);
    await cam.survoler(page.locator('select[wire\\:model="roleId"]'), 500);
    await cam.attendre(400);
    const mdp = page.locator('input[wire\\:model="password"]');
    await cam.cliquer(mdp, 150); await cam.saisir(EMPLOYE.mdp, 50);
}, 0.2);
await cam.cliquer(page.locator('[data-guide="membre-creer"]'), 1600);

// ---------------------------------------------------------------- 5. Enregistrer une commande
await page.goto(URL + '/commandes/nouvelle');
await page.waitForTimeout(1500);
cam.plein();
await chapitre('Enregistrer une commande', 'Au comptoir, en quelques touches', 'Commande');
await replique('cmd1', async () => {
    await cam.cadrer(page.locator('[data-guide="commande-client"]'), 1.5);
    await cam.cliquer(page.locator('#client-search'), 150);
    await cam.saisir('07 78 45 12 36', 70);
    await cam.attendre(700);
    await cam.cliquer(page.locator('[data-guide="commande-client"]').getByRole('button', { name: /Créer «|Nouveau client/ }), 700);
    const prenom = page.getByPlaceholder('Prénom');
    await cam.cliquer(prenom, 150); await cam.saisir(CLIENT.prenom);
    await cam.cliquer(page.getByPlaceholder('Nom', { exact: true }), 150); await cam.saisir(CLIENT.nom);
}, 0.1);
await cam.cliquer(bouton(/Créer et choisir ce client/), 1000);
await replique('cmd2', async () => {
    const pieces = page.locator('[data-guide="commande-pieces"]');
    await cam.cadrer(pieces, 1.3);
    const tuile = (nom) => pieces.locator('button[data-add]:visible', { hasText: nom }).first();
    await cam.cliquer(pieces.getByRole('tab', { name: /Repassage/ }), 500);
    for (let i = 0; i < 3; i++) await cam.cliquer(tuile('Chemise'), 250, i ? 250 : 650);
    await cam.cliquer(tuile('Pantalon'), 300);
    await cam.cliquer(pieces.getByRole('tab', { name: /Nettoyage/ }), 500);
    await cam.cliquer(tuile('Costume'), 400);
});
await replique('cmd3', async () => {
    const ticket = page.locator('[data-guide="commande-ticket"]');
    await cam.cadrer(ticket, 1.3);
    await cam.survoler(ticket.locator('input[type="date"]'));
    await cam.attendre(500);
    await cam.cliquer(ticket.getByRole('radio', { name: /Acompte/ }).first(), 500);
    await cam.cliquer(ticket.getByRole('button', { name: /La moitié/ }).first(), 400);
    await cam.cliquer(ticket.getByRole('button', { name: /^Wave/ }).first(), 400);
});
await replique('cmd4', async () => {
    await cam.cliquer(page.locator('[data-guide="commande-valider"]:visible').first(), 1800);
    await cam.cadrer(page.getByText(/enregistrée/).first().locator('xpath=ancestor::div[2]'), 1.4);
});

// ---------------------------------------------------------------- 6. Suivre jusqu'au retrait
await cam.cliquer(lien(/Voir la commande/), 300);
await page.waitForURL(/commandes\/\d+/);
await page.waitForTimeout(1200);
cam.plein();
await chapitre("Suivre jusqu'au retrait", 'Atelier, notification, remise', 'Suivi');
await replique('suivi1', async () => { await cam.attendre(800); await cam.defiler(300, 1200); await cam.defiler(-300, 900); });
await replique('suivi2', async () => {
    const b = bouton(/Démarrer le traitement/);
    await cam.cadrer(b.locator('xpath=ancestor::div[2]'), 1.6);
    await cam.cliquer(b, 1500);
});
await replique('suivi3', async () => {
    const b = bouton(/Marquer prête/);
    await cam.cadrer(b.locator('xpath=ancestor::div[2]'), 1.6);
    // Notification « commande prête » : mêmes textes que OrderReadyNotification (titre, corps), notée au clic.
    const numero = (await page.getByText(/^Commande PL-\d+/).first().innerText()).match(/PL-\d+/)[0];
    const reste = (await page.locator('body').innerText()).match(/Reste à payer\s*([\d\s\u202f\u00a0]+)\s*F/)?.[1].trim().replace(/\s+/g, ' ');
    await cam.cliquer(b, 300);
    cam.noter('telephone', { titre: 'Votre commande est prête !', corps: `La commande #${numero} est prête à être récupérée au ${PRESSING.nom}.` + (reste ? ` Reste à payer au retrait : ${reste} F.` : ' Elle est déjà payée.') });
});
await replique('suivi4', async () => {
    const b = bouton(/Encaisser .* et remettre/);
    await cam.cadrer(b.locator('xpath=ancestor::div[3]'), 1.5);
    await cam.cliquer(b, 900);
    await cam.cliquer(page.getByRole('button', { name: /Encaisser et remettre|Confirmer/ }).last(), 1500);
});
await replique('suivi5', async () => {
    cam.plein();
    await cam.defiler(-1400, 900);
    await cam.attendre(300);
    const recu = lien(/^Reçu$/);
    await recu.evaluate((a) => a.removeAttribute('target'));
    await cam.cliquer(recu, 1500);
    // Cadre serré sur les actions du reçu (Imprimer, PDF, Partager, formats).
    await cam.cadrer(page.locator('.toolbar').getByText('Partager'), 1.8, 260);
    await cam.attendre(1500);
});

// ---------------------------------------------------------------- 7. Piloter
await horsChamp(async () => {
    await commandeRapide({ tel: '0141526374', prenom: 'Yao', nom: 'Kouamé' }, [['Repassage', 'Chemise', 5], ['Repassage', 'Pantalon', 2]], 'Payée', 'Orange Money', [/Démarrer le traitement/]);
    await commandeRapide({ tel: '0554879012', prenom: 'Mariam', nom: 'Traoré' }, [['Nettoyage', 'Robe', 2], ['Repassage', 'Boubou', 1]], 'Acompte', 'Espèces', [/Démarrer le traitement/, /Marquer prête/]);
    await commandeRapide({ tel: '0707334455', prenom: 'Serge', nom: "N'Guessan" }, [['Nettoyage', 'Costume', 2]], null, null, []);
    await commandeRapide({ tel: '0102030405', prenom: 'Fatou', nom: 'Bamba' }, [['Repassage', 'Chemise', 4], ['Nettoyage', 'Pantalon', 2]], 'Payée', 'Wave', [/Démarrer le traitement/]);
    await page.goto(URL + '/tableau-de-bord');
    await page.waitForTimeout(1500);
});
cam.plein();
await chapitre('Piloter votre pressing', 'Tableau de bord, clients, caisse', 'Pilotage');
await replique('pilot1', async () => { await cam.attendre(900); await cam.defiler(350, 1500); await cam.defiler(-350, 1000); });
await replique('pilot2', async () => {
    await menu('Commandes');
    await cam.survoler(page.getByPlaceholder('N°, client, téléphone…'), 700);
    await cam.attendre(700);
    await cam.cliquer(bouton(/^Exporter/), 1400);
    await cam.cadrer(page.getByText('Excel (.xlsx)').first().locator('xpath=ancestor::div[1]'), 1.6);
    await cam.survoler(page.getByText('Excel (.xlsx)').first(), 500);
});
await page.keyboard.press('Escape');
await replique('pilot3', async () => {
    await menu('Clients');
    await cam.cliquer(page.getByText(`${CLIENT.prenom} ${CLIENT.nom}`).first(), 200);
    await page.waitForLoadState('networkidle').catch(() => {});
    await cam.attendre(900);
    await cam.survoler(page.getByText('WhatsApp').first());
});
await replique('pilot4', async () => {
    await menu('Caisse');
    const c = page.locator('#declared-cash');
    const attendu = (await page.getByText('Doit être dans la caisse').locator('xpath=..').innerText()).replace(/\D/g, '');
    await cam.cadrer(c.locator('xpath=ancestor::div[3]'), 1.4);
    await cam.cliquer(c, 200);
    await cam.saisir(attendu, 110);
});
await replique('fin', async () => { cam.plein(); await cam.attendre(400); });

const r = await cam.arreter();
console.log('tournage :', r);
await navigateur.close();
