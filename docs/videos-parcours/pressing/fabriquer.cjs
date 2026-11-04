// Montage du parcours pressing : l'enregistrement de tourner.mjs, habillé par montage.html,
// avec la voix off calée sur les repères « replique » du tournage.
//
//   node fabriquer.cjs plan   <travail>                  écrit <travail>/plan.json (durée, voix, repères) pour son.py
//   node fabriquer.cjs apercu <travail> 3 40.5 …         images fixes <travail>/apercu/<t>.jpg
//   node fabriquer.cjs video  <travail> <mix.wav> <sortie.mp4>
// <travail> contient rec/ (sortie de tourner.mjs) et voix/ (sortie de voix.py).
// playwright-core (NODE_PATH) et Google Chrome installé.
const { chromium } = require('playwright-core');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');

const ICI = __dirname;
const [mode, travail, ...reste] = process.argv.slice(2);
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const FPS = 30, INTRO = 4.6, AVANCE_VOIX = 0.1, FIN_VOIX = 1.0, FIN_APRES = 2.4;

// Accélère les passages sans voix (saisies, chargements) : chaque trou de plus de 1,2 s
// est joué jusqu'à 3 fois plus vite. Renvoie la correspondance temps enregistré → temps du film.
function accelerer(evenements, repliques, duree) {
    const garder = [];
    for (const e of evenements) {
        if (e.type === 'replique') garder.push([e.t - 0.3, e.t + repliques[e.cle].duree + 0.5]);
        if (e.type === 'chapitre') garder.push([e.t - 0.2, e.t + e.duree + 0.3]);
        if (e.type === 'telephone') garder.push([e.t, e.t + 4.6]);
    }
    garder.sort((a, b) => a[0] - b[0]);
    const points = [[0, 0]];
    let rec = 0, film = 0;
    const avancer = (jusqua, vitesse) => { if (jusqua > rec) { film += (jusqua - rec) / vitesse; rec = jusqua; points.push([rec, film]); } };
    for (const [a, b] of garder) {
        const trou = Math.max(0, a - rec);
        if (trou > 1.2) avancer(a, trou / Math.max(1.2, trou / 3)); else avancer(a, 1);
        avancer(Math.min(b, duree), 1);
    }
    avancer(duree, 1);
    return (t) => {
        if (t <= 0) return t;
        for (let i = 1; i < points.length; i++) {
            const [r0, f0] = points[i - 1], [r1, f1] = points[i];
            if (t <= r1) return f0 + (t - r0) * (f1 - f0) / Math.max(1e-9, r1 - r0);
        }
        const [rl, fl] = points[points.length - 1];
        return fl + (t - rl);
    };
}

// La réplique « fin » est dite sur la carte de fin : l'enregistrement s'arrête là où elle commençait.
function donnees() {
    const rec = JSON.parse(fs.readFileSync(path.join(travail, 'rec', 'enregistrement.json'), 'utf8'));
    const repliques = Object.fromEntries(JSON.parse(fs.readFileSync(path.join(travail, 'voix', 'repliques.json'), 'utf8')).map((r) => [r.cle, r]));
    const fin = rec.evenements.find((e) => e.type === 'replique' && e.cle === 'fin');
    let duree = fin ? fin.t + 0.5 : rec.duree;
    const bruts = rec.evenements.filter((e) => e.t <= duree && !(e.type === 'replique' && e.cle === 'fin'));
    const f = accelerer(bruts, repliques, duree);
    const evenements = bruts.map((e) => ({ ...e, t: f(e.t) }));
    const images = rec.images.filter((i) => i.t <= duree + 1).map((i) => ({ src: pathToFileURL(path.join(travail, 'rec', 'images', i.f)).href, t: f(i.t) }));
    duree = f(duree);
    const FIN = INTRO + duree;
    const voix = evenements.filter((e) => e.type === 'replique').map((e) => ({ cle: e.cle, debut: +(INTRO + e.t + AVANCE_VOIX).toFixed(3) }));
    voix.push({ cle: 'fin', debut: FIN + FIN_VOIX });
    const total = FIN + FIN_VOIX + repliques.fin.duree + FIN_APRES;
    const reperes = [
        ...evenements.filter((e) => e.type === 'chapitre').map((e) => ({ son: 'souffle', t: INTRO + e.t })),
        ...evenements.filter((e) => e.type === 'clic').map((e) => ({ son: 'clic', t: INTRO + e.t + 0.18 })),
        ...evenements.filter((e) => e.type === 'telephone').map((e) => ({ son: 'ding', t: INTRO + e.t + 0.95 })),
        { son: 'montee', t: FIN - 0.6 },
    ];
    return {
        film: { intro: INTRO, duree, images, evenements, repliques, horloge: { heure: '16:30', jour: 'Mercredi' } },
        plan: { duree: +total.toFixed(3), intro: INTRO, fin: FIN, voix, reperes },
    };
}

(async () => {
    const { film, plan } = donnees();
    if (mode === 'plan') {
        fs.writeFileSync(path.join(travail, 'plan.json'), JSON.stringify(plan, null, 1));
        console.log('plan :', plan.duree.toFixed(1), 's,', plan.voix.length, 'répliques');
        return;
    }
    const navigateur = await chromium.launch({ executablePath: CHROME, args: ['--allow-file-access-from-files'] });
    const page = await navigateur.newPage({ viewport: { width: 1920, height: 1080 } });
    page.on('pageerror', (e) => console.error('ERREUR', e.message));
    await page.goto(pathToFileURL(path.join(ICI, 'montage.html')).href);
    await page.evaluate(() => window.ready);
    await page.evaluate((d) => window.setup(d), film);

    if (mode === 'apercu') {
        const dossier = path.join(travail, 'apercu');
        fs.mkdirSync(dossier, { recursive: true });
        for (const t of reste.map(Number)) {
            await page.evaluate((x) => window.renderAt(x), t);
            await page.screenshot({ path: path.join(dossier, `${t}.jpg`), type: 'jpeg', quality: 88 });
        }
    } else if (mode === 'video') {
        const [audio, sortie] = reste;
        const total = Math.round(plan.duree * FPS);
        const ff = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'mjpeg', '-i', '-', '-i', audio,
            '-map', '0:v', '-map', '1:a', '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-profile:v', 'high', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '192k', '-movflags', '+faststart', '-t', plan.duree.toFixed(3), sortie], { stdio: ['pipe', 'inherit', 'inherit'] });
        for (let i = 0; i < total; i++) {
            await page.evaluate((x) => window.renderAt(x), i / FPS);
            const img = await page.screenshot({ type: 'jpeg', quality: 94 });
            if (!ff.stdin.write(img)) await new Promise((r) => ff.stdin.once('drain', r));
            if (i % 300 === 0) console.log(`${i}/${total}`);
        }
        ff.stdin.end();
        await new Promise((r) => ff.on('close', r));
        console.log('✓', sortie);
    }
    await navigateur.close();
})();
