// Décode les QR codes des rendus finaux (PNG) avec l'API BarcodeDetector de Chrome.
// Chaque image est décodée à sa taille d'origine (réduite à 2000 px max), puis réduite de moitié,
// puis floutée (2 px) : simulation d'une photo moins nette ou prise de plus loin.
//   NODE_PATH=<node_modules avec playwright-core> node verifier-qr.cjs image.png…
const { chromium } = require('playwright-core');
const fs = require('node:fs');
const path = require('node:path');
(async () => {
    const b = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
    const p = await b.newPage();
    const vide = path.join(__dirname, '_html', 'vide.html');
    fs.mkdirSync(path.dirname(vide), { recursive: true }); fs.writeFileSync(vide, '<!doctype html><title>vide</title>');
    await p.goto('file://' + vide);
    let echec = 0;
    for (const f of process.argv.slice(2)) {
        const url = 'data:image/png;base64,' + fs.readFileSync(f).toString('base64');
        const r = await p.evaluate(async (u) => {
            const im = new Image(); im.src = u; await im.decode();
            const det = new BarcodeDetector({ formats: ['qr_code'] });
            const res = [];
            const k0 = Math.min(1, 2000 / Math.max(im.width, im.height));
            for (const [k, flou, nom] of [[k0, 0, 'net'], [k0 / 2, 0, 'moitié'], [k0, 2, 'flou 2 px']]) {
                const c = document.createElement('canvas'); c.width = Math.round(im.width * k); c.height = Math.round(im.height * k);
                const g = c.getContext('2d'); if (flou) g.filter = `blur(${flou}px)`;
                g.drawImage(im, 0, 0, c.width, c.height);
                const d = await det.detect(c);
                res.push({ taille: `${nom} ${c.width}×${c.height}`, valeurs: d.map((x) => x.rawValue) });
            }
            return res;
        }, url);
        const ok = r.every((x) => x.valeurs.length === 1);
        if (!ok) echec++;
        console.log(`${ok ? 'OK ' : 'ÉCHEC'} ${path.basename(f)} : ${r.map((x) => `${x.taille} → ${JSON.stringify(x.valeurs)}`).join(' | ')}`);
    }
    await b.close();
    process.exit(echec ? 1 : 0);
})();
