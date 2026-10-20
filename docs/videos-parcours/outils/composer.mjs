// Montage d'une vidéo de parcours : carte d'ouverture, enregistrement de
// l'application dans un téléphone, étapes et légendes, carte de fin.
//
//   node composer.mjs <dossier_parcours> <tournage> <dossier_voix> plan
//       → écrit <tournage>/plan.json (temps des voix, touchers…) pour la bande son
//   node composer.mjs <dossier_parcours> <tournage> <dossier_voix> apercu <dossier_png> 3 12.5 …
//   node composer.mjs <dossier_parcours> <tournage> <dossier_voix> video <mix.wav> <sortie.mp4>
import { chromium } from 'playwright';
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const ICI = path.dirname(fileURLToPath(import.meta.url));
const [parcours, tournage, dossierVoix, mode, ...reste] = process.argv.slice(2);
const lire = (f) => JSON.parse(fs.readFileSync(f, 'utf8'));
const montage = lire(path.join(parcours, 'montage.json'));
const rec = lire(path.join(tournage, 'enregistrement.json'));
const barre = lire(path.join(tournage, 'barre.json'));
const repliques = Object.fromEntries(lire(path.join(dossierVoix, 'repliques.json')).map((r) => [r.cle, r]));
const FPS = 30;

// Téléphone : écran de l'application (même proportions que l'enregistrement) et corps autour.
const L = 360, H = Math.round(L * rec.hauteur / rec.largeur), BARRE = 40, BORD = 14;
const appareil = { x: 380, w: L + 2 * BORD, h: H + BARRE + 2 * BORD, r: 58 };
appareil.y = Math.round((1080 - appareil.h) / 2) - 12;
const trou = { x: appareil.x + BORD, y: appareil.y + BORD + BARRE, w: L, h: H, r: 44 };

// Temps : ouverture (voix d'intro), tournage, fin (voix de fin + carte).
const dureeVoix = (c) => repliques[c]?.duree ?? 0;
const INTRO = dureeVoix('intro') + 1.4;
const FIN = INTRO + rec.duree;
const TOTAL = FIN + dureeVoix('fin') + 3.2;
const voix = [{ cle: 'intro', debut: 0.7 }];
const etapes = [];
for (const e of rec.evenements.filter((x) => x.type === 'etape')) {
    const def = montage.etapes[e.nom];
    if (def) etapes.push({ cle: e.nom, debut: INTRO + e.t, ...def });
    if (repliques[e.nom]) voix.push({ cle: e.nom, debut: INTRO + e.t + 0.2 });
}
voix.push({ cle: 'fin', debut: FIN + 0.9 });
const touchers = rec.evenements.filter((x) => x.type === 'tap');
const plan = {
    duree: TOTAL, fps: FPS,
    voix: voix.map((v) => ({ ...v, duree: repliques[v.cle].duree, texte: repliques[v.cle].texte })),
    touchers: touchers.map((x) => INTRO + x.t),
    puces: etapes.flatMap((e) => e.puces.map((_, i) => e.debut + 0.45 + i * 0.25)),
    signature: [0.35, FIN + 0.3],
};
fs.writeFileSync(path.join(tournage, 'plan.json'), JSON.stringify(plan, null, 1));
if (mode === 'plan') { console.log(`plan : ${TOTAL.toFixed(1)} s`); process.exit(0); }

const donnees = {
    titre: montage.titre, sousTitre: montage.sousTitre, fin: montage.fin, chapitres: montage.chapitres,
    cadre: { trou, appareil, barre: BARRE }, etapes, voix: plan.voix, touchers, barre,
    temps: { intro: INTRO, fin: FIN },
};
const b = await chromium.launch();
const page = await b.newPage({ viewport: { width: 1920, height: 1080 } });
page.on('pageerror', (e) => console.error('ERREUR', e.message));
await page.goto(pathToFileURL(path.join(ICI, 'ui.html')).href);
await page.evaluate(() => window.ready);
await page.evaluate((d) => window.setup(d), donnees);
const app = path.join(tournage, 'app.mp4');
const filtreApp = `[1:v]scale=${L}:${H}:flags=lanczos,tpad=start_duration=${INTRO.toFixed(3)}:start_mode=clone:stop_mode=clone:stop_duration=60,pad=1920:1080:${trou.x}:${trou.y}:color=white[r]`;

if (mode === 'apercu') {
    const [dossier, ...temps] = reste;
    fs.mkdirSync(dossier, { recursive: true });
    for (const t of temps.map(Number)) {
        await page.evaluate((x) => window.renderAt(x), t);
        const ui = path.join(dossier, `ui-${t}.png`);
        await page.screenshot({ path: ui, omitBackground: true });
        execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-i', ui, '-i', app, '-filter_complex',
            `${filtreApp};[r]trim=start=${t},setpts=PTS-STARTPTS[r2];[r2][0:v]overlay=format=auto`, '-frames:v', '1', path.join(dossier, `image-${t}.png`)]);
        fs.rmSync(ui);
    }
} else {
    const [audio, sortie] = reste;
    const total = Math.round(TOTAL * FPS);
    const ff = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y',
        '-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'png', '-i', '-', '-i', app, '-i', audio,
        '-filter_complex', `${filtreApp};[r][0:v]overlay=format=auto:shortest=1,format=yuv420p[v]`,
        '-map', '[v]', '-map', '2:a', '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-profile:v', 'high',
        '-c:a', 'aac', '-b:a', '160k', '-movflags', '+faststart', '-t', TOTAL.toFixed(3), sortie], { stdio: ['pipe', 'inherit', 'inherit'] });
    for (let i = 0; i < total; i++) {
        await page.evaluate((x) => window.renderAt(x), i / FPS);
        const img = await page.screenshot({ type: 'png', omitBackground: true });
        if (!ff.stdin.write(img)) await new Promise((r) => ff.stdin.once('drain', r));
        if (i % 150 === 0) process.stdout.write(`\r${i}/${total}`);
    }
    ff.stdin.end();
    await new Promise((r) => ff.on('close', r));
    console.log(`\n✓ ${sortie}`);
}
await b.close();
