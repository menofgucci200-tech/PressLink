// Film PressLink V2 : vrais plans (Pexels + Google Flow), habillage animé (film.html) et voix off.
//
//   python voix.py <kokoro> . <travail>/voix            (../../videos-parcours/outils/voix.py, lit script.json)
//   node fabriquer.cjs images <travail>                 extrait les images de chaque plan (30 i/s)
//   node fabriquer.cjs plan <travail>                   écrit <travail>/plan.json (durée, repères, voix) pour son.py
//   node fabriquer.cjs apercu <travail> 4.5 21 …        images fixes <travail>/apercu/<t>.jpg
//   node fabriquer.cjs video <travail> <mix.wav> <sortie.mp4>
// playwright-core (NODE_PATH) et Google Chrome installé.
const { chromium } = require('playwright-core');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');

const ICI = __dirname;
const [mode, travail, ...reste] = process.argv.slice(2);
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

(async () => {
    const navigateur = await chromium.launch({ executablePath: CHROME });
    const page = await navigateur.newPage({ viewport: { width: 1920, height: 1080 } });
    page.on('pageerror', (e) => console.error('ERREUR', e.message));
    await page.goto(pathToFileURL(path.join(ICI, 'film.html')).href);
    await page.evaluate(() => window.ready);
    const film = await page.evaluate(() => window.FILM);

    if (mode === 'images') {
        for (const pl of film.PLANS) {
            const dossier = path.join(travail, 'images', pl.id);
            fs.rmSync(dossier, { recursive: true, force: true });
            fs.mkdirSync(dossier, { recursive: true });
            const vf = 'fps=30,scale=1920:1080:force_original_aspect_ratio=increase:flags=lanczos,crop=1920:1080' + (pl.flow ? ',unsharp=5:5:0.5' : '');
            execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-ss', String(pl.debut), '-i', path.join(ICI, pl.src),
                '-t', ((pl.a - pl.de) * (pl.vitesse ?? 1) + 0.2).toFixed(2), '-vf', vf, '-q:v', '3', path.join(dossier, '%04d.jpg')]);
            console.log(pl.id, fs.readdirSync(dossier).length, 'images');
        }
        await navigateur.close();
        return;
    }
    if (mode === 'plan') {
        fs.writeFileSync(path.join(travail, 'plan.json'), JSON.stringify({ duree: film.DUREE, reperes: film.REPERES, sections: film.S, voix: film.VOIX }, null, 1));
        console.log('plan :', film.DUREE, 's');
        await navigateur.close();
        return;
    }

    const images = Object.fromEntries(film.PLANS.map((pl) => {
        const dossier = path.join(travail, 'images', pl.id);
        return [pl.id, fs.readdirSync(dossier).filter((f) => f.endsWith('.jpg')).sort().map((f) => pathToFileURL(path.join(dossier, f)).href)];
    }));
    const logo = fs.readFileSync(path.join(ICI, 'img', 'presslink-horizontal-couleur.svg'), 'utf8');
    // Voix off (script.json → voix.py) : durées et textes des légendes.
    const repliques = Object.fromEntries(JSON.parse(fs.readFileSync(path.join(travail, 'voix', 'repliques.json'), 'utf8')).map((r) => [r.cle, r]));
    await page.evaluate((d) => window.setup(d), { logo, images, repliques });

    if (mode === 'apercu') {
        const dossier = path.join(travail, 'apercu');
        fs.mkdirSync(dossier, { recursive: true });
        for (const t of reste.map(Number)) {
            await page.evaluate((x) => window.renderAt(x), t);
            await page.screenshot({ path: path.join(dossier, `${t}.jpg`), type: 'jpeg', quality: 85 });
        }
    } else if (mode === 'video') {
        const [audio, sortie] = reste;
        const total = Math.round(film.DUREE * film.FPS);
        const ff = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'image2pipe', '-framerate', String(film.FPS), '-c:v', 'mjpeg', '-i', '-', '-i', audio,
            '-map', '0:v', '-map', '1:a', '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-profile:v', 'high', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '192k', '-movflags', '+faststart', '-t', film.DUREE.toFixed(3), sortie], { stdio: ['pipe', 'inherit', 'inherit'] });
        for (let i = 0; i < total; i++) {
            await page.evaluate((x) => window.renderAt(x), i / film.FPS);
            const img = await page.screenshot({ type: 'jpeg', quality: 94 });
            if (!ff.stdin.write(img)) await new Promise((r) => ff.stdin.once('drain', r));
            if (i % 150 === 0) console.log(`${i}/${total}`);
        }
        ff.stdin.end();
        await new Promise((r) => ff.on('close', r));
        console.log('✓', sortie);
    }
    await navigateur.close();
})();
