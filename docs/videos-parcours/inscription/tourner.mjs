// Tournage du parcours « inscription, ajout du pressing et connexion » dans
// la vraie application (PWA). Chaque étape dure au moins le temps de sa voix.
//
//   node tourner.mjs <url_app> <repliques.json> <dossier_sortie>
import { chromium } from 'playwright';
import fs from 'node:fs';
import { camera } from '../outils/camera.mjs';

const [APP, repliquesPath, sortie] = process.argv.slice(2);
const voix = Object.fromEntries(JSON.parse(fs.readFileSync(repliquesPath, 'utf8')).map((r) => [r.cle, r.duree]));
const NUMERO = '0758211436', MDP = 'awa2026';

const b = await chromium.launch();
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-FR' });
const pg = await ctx.newPage();
pg.on('pageerror', (e) => console.log('ERREUR', e.message.slice(0, 160)));
await pg.goto(APP);
await pg.evaluate(() => { localStorage.clear(); localStorage.setItem('flutter.pwa_install_dismissed_at', String(Date.now())); });
await pg.goto(APP);
await pg.waitForTimeout(8000);
await pg.mouse.click(5, 700);
await pg.waitForTimeout(500);

const cam = await camera(pg, sortie);
/** Joue une étape : au moins la durée de sa voix (+ une respiration). */
async function etape(nom, actions = async () => {}, marge = 0.6) {
    cam.etape(nom);
    const debut = Date.now();
    await actions();
    const reste = ((voix[nom] ?? 0) + marge) * 1000 - (Date.now() - debut);
    if (reste > 0) await cam.attendre(reste);
}

await etape('ouvrir', async () => cam.attendre(600));
await etape('numero', async () => {
    await cam.toucher(241, 272, 500);
    await cam.saisir(NUMERO);
    await cam.attendre(700);
    await cam.toucher(194, 349, 2200);
});
await etape('compte', async () => {
    await cam.toucher(103, 202, 300); await cam.saisir('Awa', 140);
    await cam.toucher(286, 202, 300); await cam.saisir('Koné', 140);
    await cam.toucher(286, 291, 500);
    await cam.toucher(169, 467, 300); await cam.saisir(MDP, 120);
    await cam.toucher(194, 558, 300); await cam.saisir(MDP, 120);
    await cam.attendre(500);
    await cam.toucher(194, 636, 2800);
});
await etape('bienvenue', async () => cam.attendre(1400));
await etape('pressing', async () => {
    await cam.toucher(194, 430, 1600);
    await cam.toucher(194, 223, 400);
    await cam.saisir('ZN-8533', 160);
    await cam.attendre(500);
    await cam.toucher(194, 301, 1800);
});
await etape('trouve', async () => cam.attendre(800));
await etape('ajoute', async () => { await cam.toucher(194, 494, 2400); });
await etape('deco', async () => {
    await cam.toucher(350, 828, 1300);
    await cam.toucher(328, 521, 1700);
    await pg.mouse.move(195, 500);
    for (let i = 0; i < 12; i++) { await pg.mouse.wheel(0, 140); await cam.attendre(45); }
    await cam.attendre(900);
    await cam.toucher(194, 788, 1300);
    await cam.toucher(275, 511, 2200);
});
await etape('reco', async () => {
    await cam.toucher(241, 272, 500);
    await cam.saisir(NUMERO);
    await cam.attendre(600);
    await cam.toucher(194, 349, 2200);
});
await etape('aide', async () => cam.attendre(400), 0.3);
await etape('connecte', async () => {
    await cam.toucher(194, 202, 400);
    await cam.saisir(MDP, 130);
    await cam.attendre(500);
    await cam.toucher(194, 311, 3800);
});
console.log(await cam.arreter());
await b.close();
