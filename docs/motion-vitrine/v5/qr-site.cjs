// QR code de la carte de fin : il ouvre le site presslink.org (Tester, Créer un compte…), symbole PressLink au centre ; marge réduite, le cadre blanc de la carte complète la zone de silence.
//   NODE_PATH=<node_modules avec qrcode> node qr-site.cjs <sortie.svg>
const QRCode = require('qrcode');
const fs = require('node:fs');
const qr = QRCode.create('https://presslink.org', { errorCorrectionLevel: 'H' });
const n = qr.modules.size, marge = 2, total = n + 2 * marge;
const centre = Math.round(n * 0.26); // zone libre au centre pour le symbole (le niveau H tolère ~30 % de pertes)
const debut = Math.floor((n - centre) / 2);
let carres = '';
for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) {
    const auCentre = x >= debut - 1 && x < debut + centre + 1 && y >= debut - 1 && y < debut + centre + 1;
    if (qr.modules.get(x, y) && !auCentre) carres += `M${x + marge} ${y + marge}h1v1h-1z`;
}
const symbole = fs.readFileSync(__dirname + '/img/presslink-symbole-couleur.svg', 'utf8').match(/<path[^>]*\/>/g).join('');
const c0 = marge + debut, s = centre / 256;
fs.writeFileSync(process.argv[2], `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${total}" shape-rendering="crispEdges">
<rect width="${total}" height="${total}" fill="#fff"/><path fill="#0F172A" d="${carres}"/>
<g transform="translate(${c0} ${c0}) scale(${s})" shape-rendering="geometricPrecision">${symbole}</g></svg>`);
console.log('QR', n, 'modules →', process.argv[2]);
