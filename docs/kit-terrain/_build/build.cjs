// Kit terrain PressLink : cartes de visite, carte numérique, affiche clients, flyer gérants.
// Mise en page HTML/CSS rendue par Chrome sans interface (playwright-core) en PDF vectoriels et PNG.
//
//   NODE_PATH=<node_modules avec qrcode>:<node_modules avec playwright-core> node build.cjs [nom…]
//
// Unité de mise en page : « u » (écrit « 12u » dans les styles) = 1 mm pour les supports imprimés
// (420/297 mm pour l’A3, qui reprend l’A4 à l’échelle au format exact 297 × 420 mm), 1 px pour les images numériques.
// Trois rendus par support imprimé :
//   - imprimerie : fond perdu 3 mm + traits de coupe (page = format fini + 2 × 10 mm) ;
//   - fond-perdu : fond perdu 3 mm seul (page = format fini + 2 × 3 mm), pour les imprimeurs en ligne ;
//   - aperçu PNG : format fini, sans fond perdu.
const fs = require('node:fs');
const path = require('node:path');
const QRCode = require('qrcode');
const { chromium } = require('playwright-core');

const KIT = path.resolve(__dirname, '..');
const LOGO = path.resolve(KIT, '..', 'identite-presslink', 'logo');
const HTML = path.join(__dirname, '_html');
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

// Coordonnées (presslink-api/config/presslink.php : le même numéro sert à l'appel et à WhatsApp).
const TEL = '07 18 00 22 93';
const MAIL = 'contact@presslink.org';
const SITE = 'presslink.org';
const APP = 'presslink.org/app';
const URL_SITE = 'https://presslink.org';
const URL_APP = 'https://presslink.org/app';

const BLEED = 3; // mm
// Navigateur « inconnu » pour Google Fonts : il sert alors les instances statiques TrueType de chaque graisse,
// que Chrome incorpore telles quelles dans le PDF (les polices variables y deviendraient des polices Type 3).
const UA = 'Mozilla/5.0 (kit terrain PressLink)';
const SLUG = 10; // mm autour du format fini, pour les traits de coupe

// ---------- Logo (fichiers d'origine, jamais redessinés)
function logo(name, style, dir = LOGO) {
    const s = fs.readFileSync(path.join(dir, name), 'utf8')
        .replace(/<title[^>]*>.*?<\/title>/s, '')
        .replace(/\s(role|aria-labelledby)="[^"]*"/g, '');
    return s.replace('<svg ', `<svg style="display:block;${style}" `);
}
// Le logo horizontal a une réserve de 42/256 de sa hauteur à gauche et 32/256 en haut et en bas :
// on la compense pour aligner le P sur la marge.
const icone = (style) => logo('icone-app-arrondie.svg', style, path.resolve(LOGO, '..', 'icones'));
const H_PAD_X = 42 / 256, H_PAD_Y = 32 / 256, H_RATIO = 1175.56 / 256;
function logoH(name, h, extra = '') {
    return logo(name, `height:${h}u;width:${(h * H_RATIO).toFixed(3)}u;margin-left:${(-h * H_PAD_X).toFixed(3)}u;margin-top:${(-h * H_PAD_Y).toFixed(3)}u;margin-bottom:${(-h * H_PAD_Y).toFixed(3)}u;${extra}`);
}

// ---------- QR codes : niveau H, modules Nuit sur blanc, zone de silence de 4 modules, symbole au centre
function qrSvg(url, { logoCentre = true, quiet = 4 } = {}) {
    const qr = QRCode.create(url, { errorCorrectionLevel: 'H' });
    const n = qr.modules.size, total = n + 2 * quiet;
    let c = Math.round(n * 0.24); if ((n - c) % 2) c += 1; // zone centrale, centrée au module près
    const d = (n - c) / 2;
    const libre = (x, y) => logoCentre && x >= d - 1 && x < d + c + 1 && y >= d - 1 && y < d + c + 1;
    let p = '';
    for (let y = 0; y < n; y++) for (let x = 0; x < n; x++)
        if (qr.modules.get(x, y) && !libre(x, y)) p += `M${x + quiet} ${y + quiet}h1v1h-1z`;
    let centre = '';
    if (logoCentre) {
        const sym = fs.readFileSync(path.join(LOGO, 'presslink-symbole-couleur.svg'), 'utf8').match(/<path[^>]*\/>/g).join('');
        // Le symbole occupe x 42→214 et y 32→224 dans son carré de 256 : on le cadre sur ces bornes.
        const s = c / 192, ox = quiet + d + (c - 172 * s) / 2 - 42 * s, oy = quiet + d - 32 * s;
        centre = `<g transform="translate(${ox.toFixed(4)} ${oy.toFixed(4)}) scale(${s.toFixed(5)})" shape-rendering="geometricPrecision">${sym}</g>`;
    }
    const surface = logoCentre ? ((c + 2) ** 2 / n ** 2) : 0;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${total}" shape-rendering="crispEdges">`
        + `<rect width="${total}" height="${total}" fill="#FFFFFF"/><path fill="#0F172A" d="${p}"/>${centre}</svg>`;
    return { svg, n, version: qr.version, surface };
}
const QR = { site: qrSvg(URL_SITE), app: qrSvg(URL_APP) };
function qr(which, size, extra = '') {
    return QR[which].svg.replace('<svg ', `<svg style="display:block;width:${size}u;height:${size}u;${extra}" `);
}

// ---------- Gabarit HTML commun
const FONTS = 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=block';
const CSS = `
:root{--nuit:#0F172A;--encre:#0B1530;--bleu:#2563EB;--clair:#60A5FA;--fond:#F5F7FC;--vert:#16A34A;--gris:#475569;--gris2:#64748B;--trait:#E2E8F0;--ciel:#EAF1FE}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;color:var(--nuit);-webkit-print-color-adjust:exact;print-color-adjust:exact;background:#fff;
  font-feature-settings:"tnum" 0;text-rendering:geometricPrecision}
.t{font-family:'Bricolage Grotesque',sans-serif;font-optical-sizing:auto;letter-spacing:-.03em;line-height:1}
.num{font-variant-numeric:tabular-nums}
p,li span,b{text-wrap:pretty}
.lab{font-weight:700;text-transform:uppercase;letter-spacing:.12em}
.feuille{position:relative;overflow:hidden;page-break-after:always;break-after:page}
.feuille:last-child{page-break-after:auto;break-after:auto}
.coupe{position:absolute;overflow:hidden}
.art{position:absolute}
.fp{position:absolute;inset:-${BLEED}mm} /* fond qui déborde dans le fond perdu */
`;
// Halo bleu en couleurs opaques : un dégradé semi-transparent serait rastérisé par Chrome dans le PDF.
function halo(pos, a1 = .62, a2 = .22, s2 = 32, s3 = 62) {
    const mix = (a) => '#' + [[11, 37], [21, 99], [48, 235]].map(([f, b]) => Math.round(f + (b - f) * a).toString(16).padStart(2, '0')).join('');
    return `radial-gradient(circle at ${pos},${mix(a1)} 0,${mix(a2)} ${s2}%,#0B1530 ${s3}%)`;
}
function unite(html) { return html.replace(/(-?\d*\.?\d+)u(?![a-zA-Z])/g, 'calc(var(--u)*$1)'); }
// Typographie française : espace insécable avant : ; ? ! » et après « (jamais de ponctuation en début de ligne).
function insecables(html) { return html.replace(/ ([:;?!»])/g, '\u00A0$1').replace(/« /g, '«\u00A0'); }

// mode : 'imprimerie' | 'fond-perdu' | 'apercu'
function feuille(sup, face, mode) {
    const W = sup.tw ?? sup.w * sup.k, H = sup.th ?? sup.h * sup.k; // format fini en mm
    const S = mode === 'imprimerie' ? SLUG : mode === 'fond-perdu' ? BLEED : 0;
    const B = mode === 'apercu' ? 0 : BLEED;
    const PW = W + 2 * S, PH = H + 2 * S;
    let reperes = '';
    if (mode === 'imprimerie') {
        const L = [], t = 0.1;
        const xs = [S, S + W], ys = [S, S + H];
        for (const x of xs) for (const y of ys) {
            const dx = x === S ? -1 : 1, dy = y === S ? -1 : 1;
            // traits horizontaux (prolongent les bords haut et bas) et verticaux, à 3 mm du format fini
            L.push(`<line x1="${x + dx * (BLEED)}" y1="${y}" x2="${x + dx * (S - 1.5)}" y2="${y}"/>`);
            L.push(`<line x1="${x}" y1="${y + dy * (BLEED)}" x2="${x}" y2="${y + dy * (S - 1.5)}"/>`);
        }
        reperes = `<svg style="position:absolute;inset:0" width="${PW}mm" height="${PH}mm" viewBox="0 0 ${PW} ${PH}">`
            + `<g stroke="#000" stroke-width="${t}">${L.join('')}</g></svg>`
            + `<div style="position:absolute;left:${S}mm;right:${S}mm;bottom:${(S - 3.2) / 2}mm;text-align:center;font:500 1.9mm/1 'Plus Jakarta Sans',sans-serif;color:#555;white-space:nowrap">`
            + `PressLink · ${sup.titre}${face.nom ? ' · ' + face.nom : ''} · format fini ${fmt(W)} × ${fmt(H)} mm · fond perdu ${BLEED} mm</div>`;
    }
    return { PW, PH, html: `<section class="feuille" style="width:${PW}mm;height:${PH}mm">
  <div class="coupe" style="left:${S - B}mm;top:${S - B}mm;width:${W + 2 * B}mm;height:${H + 2 * B}mm">
    <div class="art" style="left:${B}mm;top:${B}mm;width:${W}mm;height:${H}mm;--u:${sup.k}mm">${face.html}</div>
  </div>${reperes}</section>` };
}
const fmt = (v) => (Math.round(v * 10) / 10).toString().replace('.', ',');

function documentHtml(sheets, PW, PH, unit = 'mm') {
    return `<!doctype html><html lang="fr"><head><meta charset="utf-8"><link rel="stylesheet" href="${FONTS}">
<style>${CSS}@page{size:${PW}${unit} ${PH}${unit};margin:0}html,body{width:${PW}${unit}}</style></head><body>${insecables(unite(sheets))}</body></html>`;
}

// =====================================================================
// SUPPORTS
// =====================================================================
const SUP = {};

// ---------- 1. Carte de visite 85 × 55 mm
SUP['carte-visite'] = {
    dossier: '1-carte-visite', titre: 'Carte de visite', w: 85, h: 55, k: 1, dpi: 600,
    faces: [
        { nom: 'recto', fichier: 'carte-visite-recto', html: `
<div class="fp" style="background:${halo('92% 0%')}"></div>
<div style="position:absolute;left:8u;top:16.5u">
  ${logoH('presslink-horizontal-negatif.svg', 11.5)}
  <p style="margin-top:4.2u;font-size:3u;line-height:1.35;font-weight:600;color:#DCE6F5;max-width:52u">Le lien entre votre pressing<br>et vos clients</p>
</div>
<div style="position:absolute;left:8u;right:8u;bottom:6u;border-top:0.2u solid rgba(148,163,184,.35);padding-top:2.4u;display:flex;justify-content:space-between;align-items:baseline;font-size:2.35u;font-weight:600">
  <span style="color:#94A3B8">Logiciel de gestion de pressing</span>
  <span style="color:var(--clair);font-weight:700">${SITE}</span>
</div>` },
        { nom: 'verso', fichier: 'carte-visite-verso', html: `
<div class="fp" style="background:#fff"></div>
<div class="fp" style="top:auto;height:calc(${BLEED}mm + 2.2u);background:var(--bleu)"></div>
<div style="position:absolute;left:7u;top:6.5u">${logoH('presslink-horizontal-couleur.svg', 6)}</div>
<div style="position:absolute;left:7u;top:17.5u;width:44u">
  <p class="lab" style="font-size:2.05u;color:var(--gris2)">Téléphone et WhatsApp</p>
  <p class="t num" style="font-size:5.4u;font-weight:800;margin-top:1.2u;letter-spacing:-.01em;white-space:nowrap">${TEL}</p>
  <p class="lab" style="font-size:2.05u;color:var(--gris2);margin-top:3.6u">E-mail</p>
  <p style="font-size:3u;font-weight:600;margin-top:.9u">${MAIL}</p>
  <p class="lab" style="font-size:2.05u;color:var(--gris2);margin-top:3u">Site</p>
  <p style="font-size:3u;font-weight:700;margin-top:.9u;color:var(--bleu)">${SITE}</p>
</div>
<div style="position:absolute;right:5u;top:6u;width:30u;text-align:center">
  ${qr('site', 30)}
  <p style="font-size:2.35u;font-weight:700;margin-top:.6u;line-height:1.3">Scannez pour tester<br><span style="font-weight:500;color:var(--gris)">ou créer votre compte</span></p>
</div>` },
    ],
};

// ---------- 3. Affiche pour les clients des pressings (A4, et A3 à l'échelle √2)
const ETAPES_CLIENT = [
    ['Scannez le code', 'avec l’appareil photo de votre téléphone.'],
    ['Installez l’appli PressLink', 'gratuite, sur Android et iPhone.'],
    ['Entrez votre numéro', 'le même qu’au pressing : vos commandes sont déjà là.'],
];
function afficheClients() {
    const statuts = [['Reçue', false], ['En traitement', false], ['Prête', true], ['Récupérée', false]];
    return `
<div class="fp" style="background:#fff"></div>
<div class="fp" style="bottom:auto;height:calc(${BLEED}mm + 166u);background:var(--fond)"></div>
<div style="position:absolute;left:16u;right:16u;top:15u;display:flex;justify-content:space-between;align-items:center">
  ${logoH('presslink-horizontal-couleur.svg', 12)}
  <span style="font-size:4.4u;font-weight:700;color:var(--bleu);background:#fff;border:0.5u solid #C7D7FB;border-radius:20u;padding:2.2u 5u">Appli gratuite</span>
</div>
<h1 class="t" style="position:absolute;left:16u;right:10u;top:37u;font-size:18.5u;font-weight:800;line-height:.98;letter-spacing:-.035em;white-space:nowrap">Suivez vos<br>commandes sur<br>votre <span style="color:var(--bleu)">téléphone.</span></h1>
<p style="position:absolute;left:16u;right:16u;top:94u;font-size:6.9u;font-weight:600;line-height:1.3;color:#334155;white-space:nowrap">Soyez prévenu dès que vos vêtements sont prêts.</p>

<div style="position:absolute;left:16u;top:113u;width:96u;background:#fff;border-radius:6u;border:0.8u solid var(--bleu);padding:5u 5u 4.5u;text-align:center">
  ${qr('app', 86)}
  <p class="t" style="font-size:7.4u;font-weight:800;margin-top:2.5u;color:var(--bleu);letter-spacing:-.01em">${APP}</p>
</div>
<ol style="position:absolute;left:122u;right:14u;top:118u;list-style:none;display:grid;gap:9.5u">
  ${ETAPES_CLIENT.map(([t, d], i) => `<li style="display:grid;grid-template-columns:12u 1fr;gap:4u;align-items:start">
    <span class="t" style="width:12u;height:12u;border-radius:50%;background:var(--nuit);color:#fff;display:flex;align-items:center;justify-content:center;font-size:6.6u;font-weight:800;letter-spacing:0">${i + 1}</span>
    <span style="padding-top:.6u"><b class="t" style="display:block;font-size:6.3u;font-weight:800;line-height:1.08;letter-spacing:-.02em">${t}</b><span style="display:block;font-size:4.3u;line-height:1.35;color:var(--gris);margin-top:1.6u;font-weight:500">${d}</span></span>
  </li>`).join('')}
</ol>

<div style="position:absolute;left:16u;right:16u;top:231u">
  <p class="lab" style="font-size:3.4u;color:var(--gris2);margin-bottom:3.5u">Chaque commande, étape par étape</p>
  <div style="display:flex;align-items:center;gap:2.2u">
    ${statuts.map(([s, on], i) => `${i ? '<span style="flex:1;height:0.8u;background:#CBD5E1;min-width:3u"></span>' : ''}<span style="flex:none;font-size:4.6u;font-weight:700;padding:2.4u 4u;border-radius:20u;${on ? 'background:var(--vert);color:#fff' : 'background:#fff;color:var(--nuit);border:0.5u solid #CBD5E1'}">${on ? '✓ ' : ''}${s}</span>`).join('')}
  </div>
</div>
<div class="fp" style="top:auto;height:calc(${BLEED}mm + 32u);background:var(--nuit)"></div>
<div style="position:absolute;left:16u;right:16u;bottom:10u;display:flex;justify-content:space-between;align-items:center;color:#fff">
  <span style="font-size:5.4u;font-weight:700">Gratuit pour les clients</span>
  <span style="display:flex;align-items:center;gap:3u;font-size:4.2u;color:#CBD5E1;font-weight:600">Le lien entre votre pressing et vos clients ${logo('presslink-symbole-negatif.svg', 'width:9u;height:9u')}</span>
</div>`;
}
SUP['affiche-clients-a4'] = { dossier: '3-affiche-clients', titre: 'Affiche clients A4', w: 210, h: 297, k: 1, dpi: 300,
    faces: [{ nom: '', fichier: 'affiche-clients-A4', html: afficheClients() }] };
SUP['affiche-clients-a3'] = { dossier: '3-affiche-clients', titre: 'Affiche clients A3', w: 210, h: 297, k: 420 / 297, tw: 297, th: 420, dpi: 300,
    faces: [{ nom: '', fichier: 'affiche-clients-A3', html: afficheClients() }] };

// ---------- 4. Flyer A5 pour les gérants de pressing
const ATOUTS = [
    ['Commandes en 4 étapes', 'Client, articles, paiement, confirmation : un dépôt enregistré en quelques secondes.'],
    ['Clients prévenus', 'Une notification dès que la commande est prête. Fini les « c’est prêt ? ».'],
    ['Paiements et acomptes', 'Espèces, Wave, Orange Money, MTN MoMo, Moov Money.'],
    ['Caisse du jour', 'Encaissements par moyen de paiement et clôture de caisse.'],
    ['Services et tarifs', 'Vos services, vos prix, vos horaires.'],
    ['Clients et équipe', 'Fichier clients, historique et un rôle pour chaque membre.'],
];
SUP['flyer-gerants'] = { dossier: '4-flyer-gerants', titre: 'Flyer gérants A5', w: 148, h: 210, k: 1, dpi: 300,
    faces: [{ nom: '', fichier: 'flyer-gerants-A5', html: `
<div class="fp" style="background:#fff"></div>
<div class="fp" style="bottom:auto;height:calc(${BLEED}mm + 80u);background:${halo('100% 0%', .6, .2, 35, 65)}"></div>
<div style="position:absolute;left:11u;top:11u">${logoH('presslink-horizontal-negatif.svg', 8.5)}</div>
<span style="position:absolute;left:11u;top:25u;font-size:2.7u;font-weight:700;color:#DCE6F5;border:0.3u solid rgba(148,163,184,.45);border-radius:10u;padding:1.2u 3u">Logiciel de gestion de pressing</span>
<h1 class="t" style="position:absolute;left:11u;top:34.5u;font-size:13.5u;font-weight:800;color:#fff;line-height:.98;letter-spacing:-.035em">Votre pressing,<br>enfin <span style="color:var(--clair)">simple.</span></h1>
<p style="position:absolute;left:11u;top:64u;width:72u;font-size:3.5u;line-height:1.4;color:#CBD5E1;font-weight:500">Commandes, paiements et clients prévenus au même endroit.</p>

<div style="position:absolute;right:9u;top:63u;width:48u;background:#fff;border-radius:3.6u;padding:3u 3.2u;border:0.3u solid #CBD5E1">
  <div style="display:flex;align-items:center;gap:1.6u">${icone('width:5u;height:5u')}<span style="font-size:2.05u;color:var(--gris2);font-weight:600">PressLink · maintenant</span></div>
  <p style="font-size:2.9u;font-weight:800;margin-top:1.8u">Votre commande est prête !</p>
  <p style="font-size:2.3u;color:var(--gris);margin-top:.8u;line-height:1.35">La commande #0427 est prête à être récupérée.</p>
</div>

<div style="position:absolute;left:11u;right:11u;top:94u;display:grid;grid-template-columns:1fr 1fr;column-gap:7u;row-gap:4.2u">
  ${ATOUTS.map(([t, d]) => `<div style="display:grid;grid-template-columns:5u 1fr;gap:2.4u">
    <span style="width:5u;height:5u;border-radius:1.4u;background:var(--ciel);display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" style="width:3.4u;height:3.4u" fill="none" stroke="#2563EB" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
    <span><b style="display:block;font-size:3.15u;font-weight:800;line-height:1.2">${t}</b><span style="display:block;font-size:2.5u;line-height:1.36;color:var(--gris);margin-top:.6u">${d}</span></span></div>`).join('')}
</div>

<div style="position:absolute;left:11u;right:11u;top:141.5u;display:grid;grid-template-columns:repeat(3,1fr);gap:2.4u">
  ${[['1', 'Testez', 'Un pressing fictif, sans inscription.', 'presslink.org/tester'], ['2', 'Inscrivez-vous', 'En 2 minutes, sans engagement.', 'presslink.org/inscription'], ['3', 'On vous appelle', 'Notre équipe vous aide à démarrer.', '']]
        .map(([n, t, d, l], i) => `<div style="border-radius:2.6u;padding:2.6u 2.8u;${i === 1 ? 'background:var(--bleu);color:#fff' : 'background:var(--fond)'}">
    <p class="t" style="font-size:3.6u;font-weight:800;letter-spacing:-.02em"><span style="${i === 1 ? 'color:#BFD3FB' : 'color:var(--bleu)'}">${n}.</span> ${t}</p>
    <p style="font-size:2.35u;line-height:1.35;margin-top:1u;${i === 1 ? 'color:#E0E9FD' : 'color:var(--gris)'}">${d}</p>
    ${l ? `<p style="font-size:2.2u;font-weight:700;margin-top:1u;${i === 1 ? 'color:#fff' : 'color:var(--bleu)'}">${l}</p>` : ''}</div>`).join('')}
</div>

<div style="position:absolute;left:11u;right:11u;bottom:9u;height:33u;background:var(--nuit);border-radius:4u;display:flex;align-items:center;gap:5u;padding:0 4u 0 3u;color:#fff">
  <div style="background:#fff;border-radius:1.6u;overflow:hidden;flex:none">${qr('site', 27)}</div>
  <div style="flex:1">
    <p style="font-size:2.4u;color:#94A3B8;font-weight:600">Scannez ou rendez-vous sur</p>
    <p class="t" style="font-size:8u;font-weight:800;margin-top:.8u;letter-spacing:-.025em">${SITE}</p>
    <p style="font-size:2.9u;margin-top:2.2u;font-weight:600;color:#E2E8F0">Appel et WhatsApp : <b class="num" style="color:#fff">${TEL}</b></p>
    <p style="font-size:2.7u;margin-top:.8u;color:#CBD5E1">${MAIL}</p>
  </div>
</div>` }] };

// ---------- 2. Carte de visite numérique (images à partager sur WhatsApp)
const NUM = {};
NUM['carte-numerique-telephone'] = { w: 1080, h: 1920, html: `
<div style="position:absolute;inset:0;background:var(--encre)"></div>
<div style="position:absolute;inset:0;background:radial-gradient(circle at 100% 0%,rgba(37,99,235,.62) 0,rgba(37,99,235,.2) 30%,rgba(11,21,48,0) 58%)"></div>
<div style="position:absolute;left:96u;top:110u">${logoH('presslink-horizontal-negatif.svg', 104)}</div>
<span style="position:absolute;left:96u;top:270u;font-size:30u;font-weight:700;color:#DCE6F5;border:2u solid rgba(148,163,184,.45);border-radius:60u;padding:12u 30u">Logiciel de gestion de pressing</span>
<h1 class="t" style="position:absolute;left:96u;right:80u;top:378u;font-size:132u;font-weight:800;color:#fff;line-height:.97;letter-spacing:-.035em">Votre pressing,<br>enfin <span style="color:var(--clair)">simple.</span></h1>
<p style="position:absolute;left:96u;right:120u;top:660u;font-size:40u;line-height:1.38;color:#CBD5E1;font-weight:500">Commandes, paiements et clients prévenus au même endroit.</p>
<ol style="position:absolute;left:96u;right:60u;top:830u;list-style:none;display:grid;gap:34u">
  ${[['Testez', 'un pressing fictif, sans inscription'], ['Inscrivez-vous', '2 minutes, sans engagement'], ['On vous appelle', 'pour vous aider à démarrer']].map(([t, d], i) => `<li style="display:flex;align-items:center;gap:28u;font-size:35u;color:#CBD5E1;white-space:nowrap"><span class="t" style="flex:none;width:64u;height:64u;border-radius:50%;background:${i === 1 ? 'var(--bleu)' : 'rgba(255,255,255,.1)'};color:#fff;display:flex;align-items:center;justify-content:center;font-size:32u;font-weight:800">${i + 1}</span><span><b style="color:#fff;font-weight:700">${t}</b> · ${d}</span></li>`).join('')}
</ol>
<div style="position:absolute;left:64u;right:64u;bottom:150u;background:#fff;border-radius:44u;padding:52u 44u 52u 60u;display:flex;gap:44u;align-items:center">
  <div style="flex:1;min-width:0">
    <p class="lab" style="font-size:24u;color:var(--gris2)">Téléphone et WhatsApp</p>
    <p class="t num" style="font-size:72u;font-weight:800;margin-top:12u;letter-spacing:-.01em;white-space:nowrap">${TEL}</p>
    <p class="lab" style="font-size:24u;color:var(--gris2);margin-top:36u">E-mail</p>
    <p style="font-size:36u;font-weight:600;margin-top:8u">${MAIL}</p>
    <p class="lab" style="font-size:24u;color:var(--gris2);margin-top:30u">Site</p>
    <p style="font-size:36u;font-weight:700;margin-top:8u;color:var(--bleu)">${SITE}</p>
  </div>
  <div style="flex:none;width:330u;text-align:center">${qr('site', 330)}<p style="font-size:24u;font-weight:700;margin-top:6u;line-height:1.3;color:var(--nuit)">Scannez pour tester</p></div>
</div>
<p style="position:absolute;left:96u;right:96u;bottom:66u;text-align:center;font-size:30u;color:#94A3B8;font-weight:600">Le lien entre votre pressing et vos clients</p>` };
NUM['carte-numerique-carree'] = { w: 1080, h: 1080, html: `
<div style="position:absolute;inset:0;background:var(--encre)"></div>
<div style="position:absolute;inset:0;background:radial-gradient(circle at 100% 0%,rgba(37,99,235,.62) 0,rgba(37,99,235,.2) 32%,rgba(11,21,48,0) 60%)"></div>
<div style="position:absolute;left:84u;top:84u">${logoH('presslink-horizontal-negatif.svg', 92)}</div>
<h1 class="t" style="position:absolute;left:84u;right:84u;top:236u;font-size:88u;font-weight:800;color:#fff;line-height:1;letter-spacing:-.035em">Le lien entre votre pressing <span style="color:var(--clair)">et vos clients.</span></h1>
<p style="position:absolute;left:84u;right:200u;top:440u;font-size:32u;line-height:1.38;color:#CBD5E1;font-weight:500">Logiciel de gestion de pressing : commandes, paiements et clients prévenus au même endroit.</p>
<div style="position:absolute;left:56u;right:56u;bottom:56u;background:#fff;border-radius:40u;padding:48u 48u 48u 56u;display:flex;gap:40u;align-items:center">
  <div style="flex:1;min-width:0">
    <p class="lab" style="font-size:22u;color:var(--gris2)">Téléphone et WhatsApp</p>
    <p class="t num" style="font-size:64u;font-weight:800;margin-top:10u;letter-spacing:-.01em;white-space:nowrap">${TEL}</p>
    <p style="font-size:32u;font-weight:600;margin-top:30u">${MAIL}</p>
    <p style="font-size:32u;font-weight:700;margin-top:10u;color:var(--bleu)">${SITE}</p>
  </div>
  <div style="flex:none;width:260u;text-align:center">${qr('site', 260)}<p style="font-size:22u;font-weight:700;margin-top:4u;color:var(--nuit)">Scannez pour tester</p></div>
</div>` };

// =====================================================================
// RENDU
// =====================================================================
function boites(pdf, w, h, b, s) {
    const r = require('node:child_process').spawnSync('swift', [path.join(__dirname, 'boites-pdf.swift'), pdf, String(w), String(h), String(b), String(s)], { encoding: 'utf8' });
    if (r.status !== 0) throw new Error('boites-pdf : ' + r.stderr);
}
async function rendre() {
    const only = new Set(process.argv.slice(2));
    fs.mkdirSync(HTML, { recursive: true });
    const browser = await chromium.launch({ executablePath: CHROME });
    const ctxCache = {};
    // statique = true : instances TrueType statiques (supports imprimés, pour des PDF propres) ;
    // false : police variable servie aux navigateurs récents (images numériques, rendu identique au site).
    async function pageAt(scale, statique = true) {
        const k = `${scale}-${statique}`;
        if (!ctxCache[k]) ctxCache[k] = await browser.newContext(statique ? { deviceScaleFactor: scale, userAgent: UA } : { deviceScaleFactor: scale });
        return ctxCache[k].newPage();
    }
    async function charger(page, file) {
        await page.goto('file://' + file, { waitUntil: 'networkidle' });
        const ok = await page.evaluate(async () => {
            const w = [['Bricolage Grotesque', [800]], ['Plus Jakarta Sans', [500, 600, 700, 800]]];
            await Promise.all(w.flatMap(([f, ws]) => ws.map((x) => document.fonts.load(`${x} 20px "${f}"`, 'Aé0'))));
            await document.fonts.ready;
            return w.every(([f, ws]) => ws.every((x) => document.fonts.check(`${x} 20px "${f}"`, 'Aé0')));
        });
        if (!ok) throw new Error('Polices Google Fonts non chargées : ' + file);
    }
    const PX = 96 / 25.4;

    for (const [id, sup] of Object.entries(SUP)) {
        if (only.size && !only.has(id)) continue;
        const out = path.join(KIT, sup.dossier); fs.mkdirSync(out, { recursive: true });
        // PDF : imprimerie (traits de coupe) et fond perdu seul ; toutes les faces dans un même PDF.
        for (const mode of ['imprimerie', 'fond-perdu']) {
            const parts = sup.faces.map((f) => feuille(sup, f, mode));
            const file = path.join(HTML, `${id}-${mode}.html`);
            fs.writeFileSync(file, documentHtml(parts.map((p) => p.html).join(''), parts[0].PW, parts[0].PH));
            const page = await pageAt(1); await charger(page, file);
            const base = sup.faces.length > 1 ? id : sup.faces[0].fichier;
            const pdf = path.join(out, `${base}-${mode}.pdf`);
            await page.pdf({ path: pdf, preferCSSPageSize: true, printBackground: true });
            await page.close();
            boites(pdf, sup.tw ?? sup.w * sup.k, sup.th ?? sup.h * sup.k, BLEED, mode === 'imprimerie' ? SLUG : BLEED);
        }
        // Aperçus PNG au format fini.
        for (const f of sup.faces) {
            const p = feuille(sup, f, 'apercu');
            const file = path.join(HTML, `${f.fichier}-apercu.html`);
            fs.writeFileSync(file, documentHtml(p.html, p.PW, p.PH));
            const page = await pageAt(sup.dpi / 96);
            await page.setViewportSize({ width: Math.round(p.PW * PX), height: Math.round(p.PH * PX) });
            await charger(page, file);
            await page.screenshot({ path: path.join(out, `${f.fichier}.png`), clip: { x: 0, y: 0, width: p.PW * PX, height: p.PH * PX } });
            await page.close();
        }
        console.log('✓', id);
    }
    for (const [id, n] of Object.entries(NUM)) {
        if (only.size && !only.has(id) && !only.has('numerique')) continue;
        const out = path.join(KIT, '2-carte-numerique'); fs.mkdirSync(out, { recursive: true });
        const file = path.join(HTML, `${id}.html`);
        const html = `<section class="feuille" style="width:${n.w}px;height:${n.h}px;--u:1px">${n.html}</section>`;
        fs.writeFileSync(file, documentHtml(html, n.w, n.h, 'px'));
        const page = await pageAt(1, false);
        await page.setViewportSize({ width: n.w, height: n.h });
        await charger(page, file);
        await page.screenshot({ path: path.join(out, `${id}.png`) });
        await page.close();
        console.log('✓', id);
    }
    // QR codes seuls (SVG vectoriel + PNG), utiles pour d'autres supports.
    const qd = path.join(KIT, 'qr-codes'); fs.mkdirSync(qd, { recursive: true });
    for (const [k, name] of [['site', 'qr-presslink-site'], ['app', 'qr-presslink-app']]) {
        fs.writeFileSync(path.join(qd, `${name}.svg`), QR[k].svg + '\n');
        const page = await pageAt(1); await page.setViewportSize({ width: 1200, height: 1200 });
        await page.setContent(`<body style="margin:0">${QR[k].svg.replace('<svg ', '<svg width="1200" height="1200" ')}</body>`);
        await page.screenshot({ path: path.join(qd, `${name}.png`) }); await page.close();
        console.log('✓', name, `version ${QR[k].version}, ${QR[k].n} modules, zone centrale ${(QR[k].surface * 100).toFixed(1)} % de la surface`);
    }
    await browser.close();
}

// ---------- vCard (contact à enregistrer d'un clic). vCard 3.0 : la version la mieux lue par iPhone, Android et WhatsApp.
function vcard() {
    const plier = (l) => { // lignes de 75 octets au plus, suite précédée d'une espace (RFC 2426)
        const out = []; let cur = '';
        for (const ch of l) { if (Buffer.byteLength(cur + ch) > (out.length ? 74 : 75)) { out.push(cur); cur = ''; } cur += ch; }
        out.push(cur); return out.join('\r\n ');
    };
    const esc = (t) => t.replace(/\\/g, '\\\\').replace(/,/g, '\\,').replace(/;/g, '\\;');
    const photo = fs.readFileSync(path.resolve(LOGO, '..', 'icones', 'app', 'apple-touch-icon.png')).toString('base64');
    const lignes = [
        'BEGIN:VCARD', 'VERSION:3.0',
        'N:PressLink;;;;', 'FN:PressLink', 'ORG:PressLink', 'X-ABShowAs:COMPANY',
        `TITLE:${esc('Logiciel de gestion de pressing')}`,
        'TEL;TYPE=CELL,VOICE,pref:+225 07 18 00 22 93',
        `EMAIL;TYPE=INTERNET,WORK,pref:${MAIL}`,
        `URL;TYPE=WORK:${URL_SITE}`,
        `ADR;TYPE=WORK:;;;Abidjan;;;${esc('Côte d’Ivoire')}`,
        `NOTE:${esc(`Support PressLink : appel et WhatsApp au même numéro (${TEL}). Tester PressLink : ${SITE}/tester · Créer votre compte : ${SITE}/inscription · Appli client gratuite : ${APP}`)}`,
        `PHOTO;ENCODING=b;TYPE=PNG:${photo}`,
        'END:VCARD',
    ];
    const f = path.join(KIT, '2-carte-numerique', 'presslink.vcf');
    fs.mkdirSync(path.dirname(f), { recursive: true });
    fs.writeFileSync(f, lignes.map(plier).join('\r\n') + '\r\n');
    console.log('✓ presslink.vcf');
}

module.exports = { SUP, NUM, QR };
if (require.main === module) Promise.resolve(vcard()).then(rendre).catch((e) => { console.error(e); process.exit(1); });
