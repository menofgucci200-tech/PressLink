// Caméra commune : filme un onglet Chromium image par image (Page.startScreencast)
// et note les étapes et les touchers, pour caler ensuite le montage dessus.
import fs from 'node:fs';
import path from 'node:path';

export async function camera(page, dossier, debutCommun = null) {
    fs.rmSync(dossier, { recursive: true, force: true });
    fs.mkdirSync(path.join(dossier, 'images'), { recursive: true });
    const cdp = await page.context().newCDPSession(page);
    const images = [], evenements = [];
    let n = 0;
    const debut = debutCommun ?? Date.now() / 1000;
    cdp.on('Page.screencastFrame', async ({ data, metadata, sessionId }) => {
        const f = `${String(n++).padStart(5, '0')}.jpg`;
        fs.writeFileSync(path.join(dossier, 'images', f), Buffer.from(data, 'base64'));
        images.push({ f, t: metadata.timestamp - debut });
        await cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
    });
    const vp = page.viewportSize();
    await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: vp.width * 2, maxHeight: vp.height * 2, everyNthFrame: 1 });
    const maintenant = () => Date.now() / 1000 - debut;
    return {
        etape: (nom, extra = {}) => evenements.push({ type: 'etape', nom, t: maintenant(), ...extra }),
        async toucher(x, y, attente = 250) {
            evenements.push({ type: 'tap', x: x / vp.width, y: y / vp.height, t: maintenant() });
            await page.mouse.click(x, y);
            await page.waitForTimeout(attente);
        },
        /** Clique un élément (souris visible au montage) et note sa position. */
        async cliquer(locator, attente = 400) {
            const r = await locator.boundingBox();
            if (r) evenements.push({ type: 'tap', x: (r.x + r.width / 2) / vp.width, y: (r.y + r.height / 2) / vp.height, t: maintenant() + 0.5 });
            if (r) await page.mouse.move(r.x + r.width / 2, r.y + r.height / 2, { steps: 12 });
            await page.waitForTimeout(450);
            await locator.click();
            await page.waitForTimeout(attente);
        },
        async saisir(texte, delai = 110) { await page.keyboard.type(texte, { delay: delai }); },
        attendre: (ms) => page.waitForTimeout(ms),
        async arreter() {
            await page.waitForTimeout(400);
            await cdp.send('Page.stopScreencast');
            const fin = maintenant();
            // Images et évènements partagent la même horloge (secondes depuis le début du tournage).
            fs.writeFileSync(path.join(dossier, 'enregistrement.json'), JSON.stringify({ duree: fin, images, evenements, largeur: vp.width, hauteur: vp.height }, null, 1));
            return { images: images.length, duree: fin };
        },
    };
}
