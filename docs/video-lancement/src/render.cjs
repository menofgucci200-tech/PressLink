// Rendu de la vidéo de lancement : chaque image est calculée par video.html (render(t)),
// capturée par Chromium puis envoyée directement à ffmpeg avec la piste audio.
//
//   node render.cjs apercu <repliques.json> <dossier> 1.0 5.5 ...   → captures PNG
//   node render.cjs video  <repliques.json> <mix.wav> <sortie.mp4>  → vidéo finale
const { chromium } = require('playwright');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');

const here = __dirname;
const timeline = JSON.parse(fs.readFileSync(path.join(here, 'timeline.json'), 'utf8'));

(async () => {
    const [mode, repliquesPath, ...rest] = process.argv.slice(2);
    const repliques = JSON.parse(fs.readFileSync(repliquesPath, 'utf8'));
    const browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 1080, height: 1920 }, deviceScaleFactor: 1 });
    await page.goto(pathToFileURL(path.join(here, 'video.html')).href);
    await page.evaluate(() => window.ready);
    const captions = await page.evaluate(([tl, rp]) => window.setup(tl, rp), [timeline, repliques]);

    if (mode === 'apercu') {
        const [dir, ...times] = rest;
        for (const t of times) {
            await page.evaluate((x) => window.renderAt(x), Number(t));
            await page.screenshot({ path: path.join(dir, `f-${String(t).padStart(5, '0')}.png`) });
        }
        console.log(captions.map((c) => `${c.a.toFixed(2)}-${c.b.toFixed(2)} ${c.text}`).join('\n'));
    } else {
        const [audio, out] = rest;
        const fps = timeline.fps, total = Math.round(timeline.duree * fps);
        const ff = spawn('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'image2pipe', '-framerate', String(fps), '-c:v', 'mjpeg', '-i', '-',
            '-i', audio,
            '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-pix_fmt', 'yuv420p', '-profile:v', 'high',
            '-movflags', '+faststart', '-c:a', 'aac', '-b:a', '192k', '-shortest', out], { stdio: ['pipe', 'inherit', 'inherit'] });
        for (let i = 0; i < total; i++) {
            await page.evaluate((x) => window.renderAt(x), i / fps);
            const buf = await page.screenshot({ type: 'jpeg', quality: 95 });
            if (!ff.stdin.write(buf)) await new Promise((r) => ff.stdin.once('drain', r));
            if (i % 60 === 0) process.stdout.write(`\r${i}/${total}`);
        }
        ff.stdin.end();
        await new Promise((r) => ff.on('close', r));
        console.log(`\n✓ ${out}`);
    }
    await browser.close();
})();
