// Planche d'aperçu du kit terrain : tous les supports sur une seule image (apercu-kit.png).
//   NODE_PATH=<node_modules avec playwright-core> node planche.cjs   (après build.cjs)
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright-core');

const KIT = path.resolve(__dirname, '..');
const LOGO = path.resolve(KIT, '..', 'identite-presslink', 'logo', 'presslink-horizontal-couleur.svg');
const img = (f) => 'file://' + path.join(KIT, f);
const logo = fs.readFileSync(LOGO, 'utf8').replace(/<title[^>]*>.*?<\/title>/s, '').replace('<svg ', '<svg style="height:84px;width:auto;display:block;margin:-10px 0 -10px -14px" ');

const carte = (f, h, legende, detail, extra = '') => `<figure style="margin:0">
  <img src="${img(f)}" style="display:block;height:${h}px;border-radius:${extra.includes('arrondi') ? 14 : 4}px;box-shadow:0 2px 4px rgba(15,23,42,.06),0 18px 40px -12px rgba(15,23,42,.28)">
  <figcaption style="margin-top:22px"><b>${legende}</b><span>${detail}</span></figcaption></figure>`;

const html = `<!doctype html><html lang="fr"><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,800&family=Plus+Jakarta+Sans:wght@500;600;700&display=block">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{width:3640px;background:#F5F7FC;font-family:'Plus Jakarta Sans',sans-serif;color:#0F172A;padding:110px 120px 120px}
figcaption b{display:block;font-size:30px;font-weight:700}
figcaption span{display:block;font-size:24px;color:#64748B;margin-top:6px;font-weight:500}
.col{display:flex;flex-direction:column;gap:70px}
.lab{font-size:24px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#2563EB}
</style></head><body>
<header style="display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid #E2E8F0;padding-bottom:50px;margin-bottom:80px">
  <div>${logo}<h1 style="font-family:'Bricolage Grotesque';font-weight:800;font-size:86px;letter-spacing:-.03em;margin-top:40px">Kit terrain</h1>
  <p style="font-size:32px;color:#475569;margin-top:14px;font-weight:500">Les supports à laisser dans les pressings démarchés : imprimés et numériques.</p></div>
  <p style="font-size:26px;color:#64748B;text-align:right;line-height:1.6;font-weight:500">07 18 00 22 93 · appel et WhatsApp<br>contact@presslink.org · presslink.org</p>
</header>
<main style="display:flex;gap:96px;align-items:flex-start">
  <div class="col">
    <p class="lab">1 · Carte de visite</p>
    ${carte('1-carte-visite/carte-visite-recto.png', 440, 'Recto', '85 × 55 mm · PDF imprimerie')}
    ${carte('1-carte-visite/carte-visite-verso.png', 440, 'Verso', 'QR code → presslink.org')}
  </div>
  <div class="col">
    <p class="lab">3 · Affiche pour les clients</p>
    ${carte('3-affiche-clients/affiche-clients-A4.png', 1440, 'Affiche à coller dans le pressing', 'A4 et A3 · QR code → presslink.org/app')}
  </div>
  <div class="col">
    <p class="lab">4 · Flyer pour les gérants</p>
    ${carte('4-flyer-gerants/flyer-gerants-A5.png', 1100, 'Flyer A5 recto', 'QR code → presslink.org')}
    <div style="display:flex;align-items:center;gap:26px;background:#fff;border:2px solid #E2E8F0;border-radius:24px;padding:28px 32px">
      <img src="${img('../identite-presslink/icones/app/apple-touch-icon.png')}" style="width:84px;height:84px;border-radius:20px">
      <div><b style="font-size:30px;font-weight:700;display:block">presslink.vcf</b><span style="font-size:24px;color:#64748B;font-weight:500">Contact PressLink à enregistrer d’un clic</span></div>
    </div>
  </div>
  <div class="col">
    <p class="lab">2 · Carte numérique</p>
    <div style="display:flex;gap:56px;align-items:flex-start">
      ${carte('2-carte-numerique/carte-numerique-telephone.png', 1100, 'Format téléphone', '1080 × 1920 px · WhatsApp', 'arrondi')}
    </div>
    ${carte('2-carte-numerique/carte-numerique-carree.png', 400, 'Format carré', '1080 × 1080 px', 'arrondi')}
  </div>
</main>
</body></html>`;

(async () => {
    const file = path.join(__dirname, '_html', 'planche.html');
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, html);
    const b = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', args: ['--allow-file-access-from-files'] });
    const p = await b.newPage({ viewport: { width: 3640, height: 1000 } });
    await p.goto('file://' + file, { waitUntil: 'networkidle' });
    await p.evaluate(async () => { await document.fonts.load('800 20px "Bricolage Grotesque"'); await document.fonts.ready; await Promise.all([...document.images].map((i) => i.decode())); });
    await p.screenshot({ path: path.join(KIT, 'apercu-kit.png'), fullPage: true });
    await b.close();
    console.log('✓ apercu-kit.png');
})();
