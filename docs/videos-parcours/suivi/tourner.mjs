// Tournage du parcours « suivi d'une commande » : le tableau de bord du
// pressing (ordinateur) et l'application de la cliente (téléphone), filmés
// en même temps sur une horloge commune. Le montage passe de l'un à l'autre.
//
//   node tourner.mjs <url_api> <url_app> <repliques.json> <dossier_sortie>
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { camera } from '../outils/camera.mjs';

const [API, APP, repliquesPath, sortie] = process.argv.slice(2);
const voix = Object.fromEntries(JSON.parse(fs.readFileSync(repliquesPath, 'utf8')).map((r) => [r.cle, r.duree]));
const b = await chromium.launch();

// Tableau de bord : connexion puis écran « Nouvelle commande »
const bureau = await (await b.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1, locale: 'fr-FR' })).newPage();
bureau.on('pageerror', (e) => console.log('ERREUR bureau', e.message.slice(0, 160)));
await bureau.goto(`${API}/login`);
await bureau.fill('#login', 'admin@pressing-elegance.test');
await bureau.fill('#password', 'password');
await bureau.click('button[type=submit]');
await bureau.waitForURL(/tableau-de-bord|vue-ensemble|commandes/, { timeout: 15000 });
await bureau.goto(`${API}/commandes/nouvelle`);
await bureau.waitForTimeout(1500);

// Application : Awa se connecte
const tel = await (await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-FR' })).newPage();
tel.on('pageerror', (e) => console.log('ERREUR app', e.message.slice(0, 160)));
await tel.goto(APP);
await tel.evaluate(() => { localStorage.clear(); localStorage.setItem('flutter.pwa_install_dismissed_at', String(Date.now())); });
await tel.goto(APP);
await tel.waitForTimeout(8000);
await tel.mouse.click(241, 272); await tel.keyboard.type('0758211436'); await tel.mouse.click(194, 349); await tel.waitForTimeout(2500);
await tel.mouse.click(194, 202); await tel.keyboard.type('awa2026'); await tel.mouse.click(194, 311); await tel.waitForTimeout(4000);

const debut = Date.now() / 1000;
const camB = await camera(bureau, path.join(sortie, 'pressing'), debut);
const camT = await camera(tel, path.join(sortie, 'client'), debut);
const scenario = [];
async function etape(nom, ecran, actions = async () => {}, marge = 0.6) {
    scenario.push({ nom, ecran, t: Date.now() / 1000 - debut });
    const t0 = Date.now();
    await actions();
    const reste = ((voix[nom] ?? 0) + marge) * 1000 - (Date.now() - t0);
    if (reste > 0) await bureau.waitForTimeout(reste);
}
/** Recharge l'application hors champ, pendant que l'ordinateur est à l'écran. */
const recharger = async () => { await tel.goto(APP); await tel.waitForTimeout(7000); };
const ouvrirCommande = async () => { await camT.toucher(280, 461, 2200); };

process.on('uncaughtException', async (e) => { console.log('ÉCHEC', e.message.split('\n')[0]); await bureau.screenshot({ path: path.join(sortie, 'erreur-bureau.png') }); await tel.screenshot({ path: path.join(sortie, 'erreur-tel.png') }); process.exit(1); });
await etape('creer', 'pressing', async () => {
    await camB.cliquer(bureau.getByText('Awa Koné').first(), 900);
    await camB.cliquer(bureau.getByRole('button', { name: /Costume/ }).first(), 600);
    await camB.cliquer(bureau.getByRole('button', { name: /Pantalon/ }).first(), 500);
    await camB.cliquer(bureau.getByRole('button', { name: /Pantalon/ }).first(), 700);
});
let rechargement;
await etape('valider', 'pressing', async () => {
    await camB.cliquer(bureau.getByRole('button', { name: /Valider/ }).first(), 1500);
    rechargement = recharger();
    await rechargement;
});
await etape('client_recue', 'client', async () => { await ouvrirCommande(); await camT.attendre(800); await tel.mouse.move(195, 500); await tel.mouse.wheel(0, 260); });
await etape('traitement', 'pressing', async () => {
    await camB.cliquer(bureau.getByText('Voir la commande').first(), 1800);
    await camB.cliquer(bureau.getByRole('button', { name: /Démarrer le traitement/ }), 1200);
    await recharger();
});
await etape('client_traitement', 'client', ouvrirCommande);
await etape('prete', 'pressing', async () => {
    await camB.cliquer(bureau.locator('main button:visible').filter({ hasText: /Marquer prête/ }).first(), 1600);
    await recharger();
});
await etape('client_prete', 'client', async () => {
    await ouvrirCommande();
    await camT.attendre(1400);
    await tel.mouse.move(195, 500);
    for (let i = 0; i < 6; i++) { await tel.mouse.wheel(0, 140); await camT.attendre(50); }
});
await etape('remise', 'pressing', async () => {
    await camB.cliquer(bureau.locator('main').getByText('Orange Money', { exact: true }).first(), 600);
    await camB.cliquer(bureau.getByRole('button', { name: /Encaisser .* et remettre/ }).first(), 1800);
    const confirmer = bureau.getByRole('button', { name: 'Encaisser et remettre', exact: true }).last();
    if (await confirmer.isVisible().catch(() => false)) await camB.cliquer(confirmer, 1500);
    await recharger();
});
await etape('client_recuperee', 'client', async () => {
    // une commande récupérée quitte l'accueil : on la retrouve dans « Commandes »
    await camT.toucher(117, 827, 1500);
    await camT.toucher(287, 277, 2200);
    await camT.attendre(2600);
});
scenario.push({ nom: 'fin', ecran: 'client', t: Date.now() / 1000 - debut });

console.log(await camB.arreter(), await camT.arreter());
fs.writeFileSync(path.join(sortie, 'scenario.json'), JSON.stringify(scenario, null, 1));
await b.close();
