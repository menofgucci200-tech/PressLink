// Montage d'une vidéo de parcours à deux appareils : le tableau de bord du
// pressing (ordinateur) et l'application de la cliente (téléphone), filmés
// ensemble. L'appareil à l'écran suit le scénario, avec un fondu à chaque bascule.
//
//   node composer2.mjs <dossier_parcours> <tournage> <dossier_voix> plan
//   node composer2.mjs <dossier_parcours> <tournage> <dossier_voix> apercu <dossier_png> 3 12.5 …
//   node composer2.mjs <dossier_parcours> <tournage> <dossier_voix> video <mix.wav> <sortie.mp4>
import { chromium } from 'playwright';
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const ICI = path.dirname(fileURLToPath(import.meta.url));
const [parcours, tournage, dossierVoix, mode, ...reste] = process.argv.slice(2);
const lire = (f) => JSON.parse(fs.readFileSync(f, 'utf8'));
const montage = lire(path.join(parcours, 'montage.json'));
const scenario = lire(path.join(tournage, 'scenario.json'));
const recP = lire(path.join(tournage, 'pressing', 'enregistrement.json'));
const recC = lire(path.join(tournage, 'client', 'enregistrement.json'));
const barre = lire(path.join(tournage, 'client', 'barre.json'));
const repliques = Object.fromEntries(lire(path.join(dossierVoix, 'repliques.json')).map((r) => [r.cle, r]));
const FPS = 30;

// Ordinateur (écran 1440×900 réduit) et téléphone, centrés au même endroit à gauche.
const ordi = { x: 120, y: 236, w: 1056, h: 660 };
const L = 340, H = Math.round(L * recC.hauteur / recC.largeur), BARRE = 40, BORD = 14;
const centre = ordi.x + ordi.w / 2;
const appareil = { x: Math.round(centre - (L + 2 * BORD) / 2), w: L + 2 * BORD, h: H + BARRE + 2 * BORD, r: 56 };
appareil.y = Math.round((1080 - appareil.h) / 2) - 8;
const trou = { x: appareil.x + BORD, y: appareil.y + BORD + BARRE, w: L, h: H, r: 42 };

const dureeVoix = (c) => repliques[c]?.duree ?? 0;
const INTRO = dureeVoix('intro') + 1.4;
const finTournage = scenario.find((e) => e.nom === 'fin')?.t ?? Math.min(recP.duree, recC.duree);
const FIN = INTRO + finTournage;
const TOTAL = FIN + dureeVoix('fin') + 3.2;
const voix = [{ cle: 'intro', debut: 0.7 }];
const etapes = [], segments = [];
for (const e of scenario.filter((x) => x.nom !== 'fin')) {
    const debut = INTRO + e.t;
    segments.push({ ecran: e.ecran, debut });
    const def = montage.etapes[e.nom];
    if (def) etapes.push({ cle: e.nom, debut, ...def });
    if (repliques[e.nom]) voix.push({ cle: e.nom, debut: debut + 0.25 });
}
voix.push({ cle: 'fin', debut: FIN + 0.9 });
const clics = recP.evenements.filter((x) => x.type === 'tap');
const touchers = recC.evenements.filter((x) => x.type === 'tap');
const notif = etapes.find((e) => e.notification);
const plan = {
    duree: TOTAL, fps: FPS,
    voix: voix.map((v) => ({ ...v, duree: repliques[v.cle].duree, texte: repliques[v.cle].texte })),
    touchers: [...clics, ...touchers].map((x) => INTRO + x.t).sort((a, b) => a - b),
    puces: etapes.flatMap((e) => e.puces.map((_, i) => e.debut + 0.45 + i * 0.25)),
    signature: [0.35, FIN + 0.3],
    notification: notif ? notif.debut + 0.7 : null,
};
fs.writeFileSync(path.join(tournage, 'plan.json'), JSON.stringify(plan, null, 1));
if (mode === 'plan') { console.log(`plan : ${TOTAL.toFixed(1)} s`); process.exit(0); }

const donnees = {
    titre: montage.titre, sousTitre: montage.sousTitre, fin: montage.fin, chapitres: montage.chapitres,
    cadre: { trou, appareil, barre: BARRE }, ordi, etapes, segments, voix: plan.voix, touchers, clics, barre,
    notification: plan.notification, temps: { intro: INTRO, fin: FIN },
};
const b = await chromium.launch();
const page = await b.newPage({ viewport: { width: 1920, height: 1080 } });
page.on('pageerror', (e) => console.error('ERREUR', e.message));
await page.goto(pathToFileURL(path.join(ICI, 'ui2.html')).href);
await page.evaluate(() => window.ready);
await page.evaluate((d) => window.setup(d), donnees);

// L'enregistrement de chaque appareil n'est visible que pendant ses séquences.
const plages = (ecran) => segments.map((s, i) => [s.debut, segments[i + 1]?.debut ?? TOTAL + 1, s.ecran]).filter((s) => s[2] === ecran)
    .map(([a, z]) => `between(t,${a.toFixed(3)},${z.toFixed(3)})`).join('+') || '0';
const tpad = `tpad=start_duration=${INTRO.toFixed(3)}:start_mode=clone:stop_mode=clone:stop_duration=60`;
const filtreBase = `color=c=white:s=1920x1080:r=${FPS}[fond];`
    + `[1:v]scale=${ordi.w}:${ordi.h}:flags=lanczos,${tpad}[o];[2:v]scale=${L}:${H}:flags=lanczos,${tpad}[c];`
    + `[fond][o]overlay=x=${ordi.x}:y=${ordi.y}:enable='${plages('pressing')}'[b1];[b1][c]overlay=x=${trou.x}:y=${trou.y}:enable='${plages('client')}'[r]`;
const entrees = ['-i', path.join(tournage, 'pressing', 'app.mp4'), '-i', path.join(tournage, 'client', 'app.mp4')];

if (mode === 'apercu') {
    const [dossier, ...temps] = reste;
    fs.mkdirSync(dossier, { recursive: true });
    for (const t of temps.map(Number)) {
        await page.evaluate((x) => window.renderAt(x), t);
        const ui = path.join(dossier, `ui-${t}.png`);
        await page.screenshot({ path: ui, omitBackground: true });
        execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-i', ui, ...entrees, '-filter_complex',
            `${filtreBase};[r]trim=start=${t},setpts=PTS-STARTPTS[r2];[r2][0:v]overlay=format=auto`, '-frames:v', '1', path.join(dossier, `image-${t}.png`)]);
        fs.rmSync(ui);
    }
} else {
    const [audio, sortie] = reste;
    const total = Math.round(TOTAL * FPS);
    const ff = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y',
        '-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'png', '-i', '-', ...entrees, '-i', audio,
        '-filter_complex', `${filtreBase};[r][0:v]overlay=format=auto:shortest=1,format=yuv420p[v]`,
        '-map', '[v]', '-map', '3:a', '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-profile:v', 'high',
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
