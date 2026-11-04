// Rendu d'une maquette motion : chaque image est calculée par <maquette>/video.html
// (window.renderAt(t)), capturée par Chromium puis envoyée à ffmpeg avec le son.
//
//   node render.cjs <maquette> apercu <dossier_voix> <dossier_png> 1.0 5.5 ...
//   node render.cjs <maquette> video  <dossier_voix> <mix.wav> <sortie.mp4>
const { chromium } = require('playwright');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');

(async () => {
    const [maquette, mode, dossierVoix, ...rest] = process.argv.slice(2);
    const dir = path.resolve(maquette);
    const timeline = JSON.parse(fs.readFileSync(path.join(dir, 'timeline.json'), 'utf8'));
    const repliques = JSON.parse(fs.readFileSync(path.join(dossierVoix, 'repliques.json'), 'utf8'));
    const browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1 });
    page.on('pageerror', (e) => console.error('PAGEERROR', e.message));
    await page.goto(pathToFileURL(path.join(dir, 'video.html')).href);
    await page.evaluate(() => window.ready);
    await page.evaluate(([tl, rp]) => window.setup(tl, rp), [timeline, repliques]);

    if (mode === 'apercu') {
        const [out, ...times] = rest;
        fs.mkdirSync(out, { recursive: true });
        for (const t of times) {
            await page.evaluate((x) => window.renderAt(x), Number(t));
            await page.screenshot({ path: path.join(out, `${path.basename(dir)}-${String(t).padStart(5, '0')}.png`) });
        }
    } else {
        const [audio, out] = rest;
        const fps = timeline.fps, total = Math.round(timeline.duree * fps);
        const ff = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'image2pipe', '-framerate', String(fps), '-c:v', 'mjpeg', '-i', '-', '-i', audio,
            '-c:v', 'libx264', '-preset', 'slow', '-crf', '19', '-pix_fmt', 'yuv420p', '-profile:v', 'high',
            '-movflags', '+faststart', '-c:a', 'aac', '-b:a', '192k', '-shortest', out], { stdio: ['pipe', 'inherit', 'inherit'] });
        for (let i = 0; i < total; i++) {
            await page.evaluate((x) => window.renderAt(x), i / fps);
            const buf = await page.screenshot({ type: 'jpeg', quality: 94 });
            if (!ff.stdin.write(buf)) await new Promise((r) => ff.stdin.once('drain', r));
            if (i % 90 === 0) process.stdout.write(`\r${i}/${total}`);
        }
        ff.stdin.end();
        await new Promise((r) => ff.on('close', r));
        console.log(`\n✓ ${out}`);
    }
    await browser.close();
})();
