// Exporte chaque logo SVG (logo/*.svg) en PNG transparent haute définition (logo/png/)
// et fabrique une présentation par logo, sur le fond prévu pour sa version.
//   NODE_PATH=<playwright-core> node logos-png.cjs <dossier des présentations>
const { chromium } = require('playwright-core');
const fs = require('node:fs');
const path = require('node:path');

const LOGO = path.join(__dirname, '..', 'logo');
const PNG = path.join(LOGO, 'png');
const presentations = process.argv[2];
const VERSIONS = { couleur: ['Couleur', '#FFFFFF'], noir: ['Noir', '#FFFFFF'], blanc: ['Blanc', '#0F172A'], negatif: ['Négatif', '#0F172A'] };

(async () => {
    fs.mkdirSync(PNG, { recursive: true });
    fs.mkdirSync(presentations, { recursive: true });
    const navigateur = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
    const page = await navigateur.newPage();
    for (const f of fs.readdirSync(LOGO).filter((x) => x.endsWith('.svg')).sort()) {
        const svg = fs.readFileSync(path.join(LOGO, f), 'utf8');
        const [, vw, vh] = svg.match(/viewBox="0 0 ([\d.]+) ([\d.]+)"/).map(Number);
        const forme = f.replace(/^presslink-|-(couleur|noir|blanc|negatif)\.svg$/g, '');
        const version = f.match(/-(couleur|noir|blanc|negatif)\.svg$/)[1];
        const largeur = forme.startsWith('symbole') ? 1024 : 2400, hauteur = Math.round(largeur * vh / vw);
        const marge = Math.round(largeur * 0.04);

        // PNG transparent, avec une petite marge de protection
        await page.setViewportSize({ width: largeur + 2 * marge, height: hauteur + 2 * marge });
        await page.setContent(`<style>html,body{margin:0;background:transparent}svg{display:block;margin:${marge}px;width:${largeur}px;height:${hauteur}px}</style>${svg}`);
        await page.screenshot({ path: path.join(PNG, f.replace('.svg', '.png')), omitBackground: true });

        // Présentation 1600 × 1000 sur le fond de la version
        const [, fond] = VERSIONS[version];
        const h = forme.startsWith('symbole') ? 380 : forme === 'empile' ? 430 : 210;
        await page.setViewportSize({ width: 1600, height: 1000 });
        await page.setContent(`<style>html,body{margin:0;width:1600px;height:1000px;background:${fond};font-family:-apple-system,Helvetica,sans-serif}
            .c{position:absolute;inset:0;display:flex;align-items:center;justify-content:center}svg{height:${h}px;width:auto;max-width:1280px}
            </style><div class="c">${svg}</div>`);
        await page.screenshot({ path: path.join(presentations, f.replace('.svg', '.png')) });
        console.log('✓', f);
    }
    await navigateur.close();
})();
