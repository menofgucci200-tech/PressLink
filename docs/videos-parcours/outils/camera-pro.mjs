// Caméra du parcours pressing : filme un onglet image par image (Page.startScreencast),
// avec un curseur dessiné dans la page (donc filmé) et un repère de chaque zone à cadrer,
// pour que le montage zoome au bon endroit et au bon moment.
import fs from 'node:fs';
import path from 'node:path';

// Curseur et onde de clic injectés dans chaque page (pointer-events: none).
const CURSEUR = () => {
    const poser = () => {
        if (!document.documentElement || document.getElementById('pl-curseur')) return;
        const s = document.createElement('style');
        s.textContent = `#pl-curseur{position:fixed;left:0;top:0;width:26px;height:26px;z-index:2147483647;pointer-events:none;transform:translate(-3px,-2px);transition:transform .08s}
#pl-curseur.appui{transform:translate(-3px,-2px) scale(.86)}
.pl-onde{position:fixed;z-index:2147483646;pointer-events:none;width:46px;height:46px;margin:-23px 0 0 -23px;border-radius:50%;border:3px solid rgba(37,99,235,.9);background:rgba(37,99,235,.18);animation:pl-onde .6s ease-out forwards}
@keyframes pl-onde{from{transform:scale(.35);opacity:1}to{transform:scale(1.5);opacity:0}}`;
        document.documentElement.appendChild(s);
        const c = document.createElement('div');
        c.id = 'pl-curseur';
        c.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" style="filter:drop-shadow(0 2px 3px rgba(0,0,0,.35))"><path d="M5 2 L5 20 L10 15 L13.5 22 L16.5 20.5 L13 14 L20 14 Z" fill="#fff" stroke="#0f172a" stroke-width="1.5" stroke-linejoin="round"/></svg>';
        const xy = window.__plCurseur || [-60, -60];
        c.style.left = xy[0] + 'px'; c.style.top = xy[1] + 'px';
        document.documentElement.appendChild(c);
    };
    document.addEventListener('mousemove', (e) => {
        window.__plCurseur = [e.clientX, e.clientY];
        const c = document.getElementById('pl-curseur');
        if (c) { c.style.left = e.clientX + 'px'; c.style.top = e.clientY + 'px'; }
        try { sessionStorage.setItem('pl-curseur', JSON.stringify([e.clientX, e.clientY])); } catch {}
    }, true);
    document.addEventListener('mousedown', (e) => {
        const c = document.getElementById('pl-curseur'); c?.classList.add('appui');
        const o = document.createElement('div'); o.className = 'pl-onde'; o.style.left = e.clientX + 'px'; o.style.top = e.clientY + 'px';
        document.documentElement.appendChild(o); setTimeout(() => o.remove(), 700);
    }, true);
    document.addEventListener('mouseup', () => document.getElementById('pl-curseur')?.classList.remove('appui'), true);
    try { window.__plCurseur = JSON.parse(sessionStorage.getItem('pl-curseur')); } catch {}
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', poser); else poser();
    const veiller = () => new MutationObserver(poser).observe(document.documentElement, { childList: true });
    if (document.documentElement) veiller(); else document.addEventListener('readystatechange', veiller, { once: true });
};

export async function preparer(contexte) {
    await contexte.addInitScript(CURSEUR);
}

export async function camera(page, dossier) {
    fs.rmSync(dossier, { recursive: true, force: true });
    fs.mkdirSync(path.join(dossier, 'images'), { recursive: true });
    const images = [], evenements = [];
    let n = 0, souris = [720, 450], cdp = null, decalage = 0, pauseDepuis = null;
    const debut = Date.now() / 1000;
    const vp = page.viewportSize();
    // Les temps excluent les pauses hors champ (préparation de données, changement d'onglet).
    const filmer = async (p) => {
        page = p;
        cdp = await page.context().newCDPSession(page);
        const session = cdp;
        session.on('Page.screencastFrame', async ({ data, metadata, sessionId }) => {
            await session.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
            if (pauseDepuis !== null || session !== cdp) return;
            const f = `${String(n++).padStart(5, '0')}.jpg`;
            fs.writeFileSync(path.join(dossier, 'images', f), Buffer.from(data, 'base64'));
            images.push({ f, t: metadata.timestamp - debut - decalage });
        });
        await session.send('Page.startScreencast', { format: 'jpeg', quality: 90, maxWidth: vp.width * 2, maxHeight: vp.height * 2, everyNthFrame: 1 });
    };
    await filmer(page);
    const maintenant = () => Date.now() / 1000 - debut - decalage;
    const glisser = async (x, y, duree = 650) => {
        const etapes = Math.max(8, Math.round(duree / 16));
        const [x0, y0] = souris;
        for (let i = 1; i <= etapes; i++) {
            const k = i / etapes, e = k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2;
            await page.mouse.move(x0 + (x - x0) * e, y0 + (y - y0) * e);
            await page.waitForTimeout(duree / etapes);
        }
        souris = [x, y];
    };
    const boite = async (locator) => {
        await locator.scrollIntoViewIfNeeded({ timeout: 8000 }).catch(() => {});
        const r = await locator.boundingBox({ timeout: 8000 });
        if (!r) throw new Error('élément introuvable : ' + locator);
        return r;
    };
    return {
        maintenant,
        get page() { return page; },
        /** Coupe hors champ : rien n'est filmé, le temps du film ne s'écoule pas. */
        async pause() { pauseDepuis = Date.now() / 1000; await cdp.send('Page.stopScreencast').catch(() => {}); },
        async reprendre(nouvellePage = page) {
            decalage += Date.now() / 1000 - pauseDepuis;
            pauseDepuis = null;
            if (nouvellePage !== page) await filmer(nouvellePage);
            else await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 90, maxWidth: vp.width * 2, maxHeight: vp.height * 2, everyNthFrame: 1 });
            await page.mouse.move(souris[0], souris[1]);
        },
        /** Repère de montage : chapitre, réplique, ou cadrage (zone en fractions de l'écran). */
        noter: (type, extra = {}) => evenements.push({ type, t: maintenant(), ...extra }),
        /** Cadre la caméra du montage sur un élément (zoom ≈ facteur souhaité, 1 = plein écran). */
        async cadrer(locator, zoom = 1.6, marge = 40) {
            const r = await boite(locator);
            evenements.push({ type: 'cadre', t: maintenant(), zoom,
                x: (r.x + r.width / 2) / vp.width, y: (r.y + r.height / 2) / vp.height,
                w: (r.width + 2 * marge) / vp.width, h: (r.height + 2 * marge) / vp.height });
        },
        plein: () => evenements.push({ type: 'cadre', t: maintenant(), zoom: 1, x: 0.5, y: 0.5, w: 1, h: 1 }),
        glisser,
        async survoler(locator, duree = 650) { const r = await boite(locator); await glisser(r.x + r.width / 2, r.y + r.height / 2, duree); },
        async cliquer(locator, attente = 500, duree = 650) {
            const r = await boite(locator);
            await glisser(r.x + r.width / 2, r.y + r.height / 2, duree);
            await page.waitForTimeout(180);
            evenements.push({ type: 'clic', t: maintenant(), x: (r.x + r.width / 2) / vp.width, y: (r.y + r.height / 2) / vp.height });
            await page.mouse.down(); await page.waitForTimeout(70); await page.mouse.up();
            await page.waitForTimeout(attente);
        },
        async saisir(texte, delai = 75) { await page.keyboard.type(texte, { delay: delai }); },
        async defiler(dy, duree = 900) {
            const pas = Math.max(6, Math.round(duree / 40));
            for (let i = 0; i < pas; i++) { await page.mouse.wheel(0, dy / pas); await page.waitForTimeout(duree / pas); }
        },
        attendre: (ms) => page.waitForTimeout(ms),
        async arreter() {
            await page.waitForTimeout(500);
            await cdp.send('Page.stopScreencast').catch(() => {});
            const fin = maintenant();
            fs.writeFileSync(path.join(dossier, 'enregistrement.json'), JSON.stringify({ duree: fin, images, evenements, largeur: vp.width, hauteur: vp.height }, null, 1));
            return { images: images.length, duree: fin };
        },
    };
}
