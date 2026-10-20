// Tournage de la vidéo de présentation : le site, l'application client et le
// tableau de bord du pressing, filmés ensemble (même méthode que le suivi).
//
//   node tourner.mjs <url_api> <url_app> <repliques.json> <dossier_sortie>
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { camera } from '../outils/camera.mjs';

const [API, APP, repliquesPath, sortie] = process.argv.slice(2);
const voix = Object.fromEntries(JSON.parse(fs.readFileSync(repliquesPath, 'utf8')).map((r) => [r.cle, r.duree]));
const b = await chromium.launch();

// Le site est ouvert avant la connexion (une fois connecté, l'accueil mène au tableau de bord) ;
// la connexion se fait dans un second onglet du même navigateur.
const ctxBureau = await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1, locale: 'fr-FR' });
const bureau = await ctxBureau.newPage();
bureau.on('pageerror', (e) => console.log('ERREUR bureau', e.message.slice(0, 160)));
const connexion = await ctxBureau.newPage();
await connexion.goto(`${API}/login`);
await connexion.fill('#login', 'admin@pressing-elegance.test');
await connexion.fill('#password', 'password');
await connexion.click('button[type=submit]');
await connexion.waitForURL(/tableau-de-bord|vue-ensemble|commandes/, { timeout: 15000 });
await connexion.close();
await ctxBureau.clearCookies();
await bureau.goto(`${API}/`);
await bureau.waitForTimeout(400);

const tel = await (await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-FR' })).newPage();
tel.on('pageerror', (e) => console.log('ERREUR app', e.message.slice(0, 160)));
await tel.goto(APP);
await tel.evaluate(() => { localStorage.clear(); localStorage.setItem('flutter.pwa_install_dismissed_at', String(Date.now())); });
await tel.goto(APP);
await tel.waitForTimeout(8000);
await tel.mouse.click(241, 272); await tel.keyboard.type('0701234567'); await tel.mouse.click(194, 349); await tel.waitForTimeout(2500);
await tel.mouse.click(194, 202); await tel.keyboard.type('password'); await tel.mouse.click(194, 311); await tel.waitForTimeout(4500);

const debut = Date.now() / 1000;
const camB = await camera(bureau, path.join(sortie, 'pressing'), debut);
const camT = await camera(tel, path.join(sortie, 'client'), debut);
const scenario = [];
process.on('uncaughtException', async (e) => { console.log('ÉCHEC', e.message.split('\n')[0]); await bureau.screenshot({ path: path.join(sortie, 'erreur-bureau.png') }); await tel.screenshot({ path: path.join(sortie, 'erreur-tel.png') }); process.exit(1); });
async function etape(nom, ecran, actions = async () => {}, marge = 0.7) {
    scenario.push({ nom, ecran, t: Date.now() / 1000 - debut });
    const t0 = Date.now();
    await actions();
    const reste = ((voix[nom] ?? 0) + marge) * 1000 - (Date.now() - t0);
    if (reste > 0) await bureau.waitForTimeout(reste);
}
const defiler = async (page, fois, pas = 120, pause = 70) => { const v = page.viewportSize(); await page.mouse.move(v.width / 2, v.height * 0.6); for (let i = 0; i < fois; i++) { await page.mouse.wheel(0, pas); await page.waitForTimeout(pause); } };

// 1. Le site vitrine (avec son arrivée animée), puis on descend doucement
await etape('site', 'pressing', async () => { await bureau.waitForTimeout(3600); await defiler(bureau, 14, 90, 110); });
// 2. L'application de la cliente
await etape('client_accueil', 'client', async () => camT.attendre(1200));
await etape('client_detail', 'client', async () => {
    await camT.toucher(280, 634, 2000);
    await defiler(tel, 6, 120, 80);
});
await etape('client_contact', 'client', async () => {
    await defiler(tel, 8, 150, 60);
    await camT.attendre(600);
    await camT.toucher(194, 746, 1800);
});
// 3. Le comptoir : commande express (connexion faite hors champ)
await bureau.goto(`${API}/login`);
await bureau.fill('#login', 'admin@pressing-elegance.test');
await bureau.fill('#password', 'password');
await bureau.click('button[type=submit]');
await bureau.waitForURL(/tableau-de-bord|vue-ensemble|commandes/, { timeout: 15000 });
await bureau.goto(`${API}/commandes/nouvelle`);
await bureau.waitForTimeout(1200);
await etape('depot', 'pressing', async () => {
    await camB.cliquer(bureau.getByPlaceholder('Nom ou numéro de téléphone'), 200);
    await bureau.keyboard.type('Konan', { delay: 110 });
    await bureau.waitForTimeout(900);
    await camB.cliquer(bureau.locator('main').getByText('Awa Konan').first(), 800);
    await camB.cliquer(bureau.getByRole('button', { name: /Robe/ }).first(), 500);
    await camB.cliquer(bureau.getByRole('button', { name: /Pantalon/ }).first(), 500);
    await camB.cliquer(bureau.getByRole('button', { name: /Valider/ }).first(), 1500);
});
// 4. Tableau de bord
await etape('tableau', 'pressing', async () => {
    await camB.cliquer(bureau.getByRole('link', { name: 'Dashboard' }).first(), 1800);
    await defiler(bureau, 6, 110, 120);
});
// 5. Caisse : une dépense, puis la clôture sans écart
await etape('caisse', 'pressing', async () => {
    await camB.cliquer(bureau.getByRole('link', { name: 'Caisse' }).first(), 1600);
    await camB.cliquer(bureau.getByLabel('Motif de la dépense'), 200);
    await bureau.keyboard.type('Achat de lessive', { delay: 45 });
    await camB.cliquer(bureau.getByLabel('Montant de la dépense'), 200);
    await bureau.keyboard.type('2000', { delay: 80 });
    await camB.cliquer(bureau.getByRole('button', { name: /Ajouter la dépense/ }), 1300);
    const attendu = (await bureau.locator('dt:has-text("Doit être dans la caisse") + dd').first().innerText()).replace(/\D/g, '');
    await camB.cliquer(bureau.locator('#declared-cash'), 200);
    await bureau.keyboard.type(attendu, { delay: 90 });
    await bureau.waitForTimeout(700);
    await camB.cliquer(bureau.getByRole('button', { name: 'Clôturer la caisse' }), 1800);
});
// 6. Équipe et rôles, puis journal
await etape('equipe', 'pressing', async () => {
    await camB.cliquer(bureau.getByRole('link', { name: 'Équipe' }).first(), 1600);
    await defiler(bureau, 7, 120, 110);
});
await etape('journal', 'pressing', async () => {
    // l'application revient à l'accueil hors champ, pendant le journal
    const retour = tel.goto(APP).then(() => tel.waitForTimeout(7000));
    await camB.cliquer(bureau.getByRole('link', { name: 'Journal' }).first(), 1800);
    await defiler(bureau, 4, 110, 120);
    await retour;
});
// 7. Le support PressLink, dans l'application
await etape('support', 'client', async () => { await camT.toucher(272, 827, 2200); });
scenario.push({ nom: 'fin', ecran: 'client', t: Date.now() / 1000 - debut });

console.log(await camB.arreter(), await camT.arreter());
fs.writeFileSync(path.join(sortie, 'scenario.json'), JSON.stringify(scenario, null, 1));
await b.close();
