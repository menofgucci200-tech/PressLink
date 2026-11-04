// Captures de PressLink Pro (pressing de démo local, compte de test du seeder) pour les incrustations Flow.
const { chromium } = require('playwright-core');
(async () => {
    const out = process.argv[2];
    const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: 'light' });
    await page.addInitScript(() => localStorage.setItem('presslink-theme', 'light'));
    await page.goto('http://localhost:8000/login');
    await page.fill('input[type=text]', '0700000001');
    await page.fill('input[type=password]', 'password');
    await page.click('button[type=submit]');
    await page.waitForURL('**/tableau-de-bord');
    const ouvrir = page.getByText('Ouvrir exceptionnellement');
    if (await ouvrir.count()) { await ouvrir.first().click(); await page.waitForTimeout(1500); }
    // Masque le bandeau d'ouverture exceptionnelle (démo tournée la nuit).
    const propre = () => page.evaluate(() => {
        for (const el of document.querySelectorAll('body *')) {
            if (el.children.length === 0 && /Ouverture exceptionnelle/.test(el.textContent)) { (el.closest('[class*="border-b"], div') || el).style.display = 'none'; }
            if (el.children.length === 0 && el.textContent.trim() === 'Ouvert (exceptionnel)') { el.textContent = 'Ouvert'; }
        }
    });
    await page.goto('http://localhost:8000/tableau-de-bord'); await page.waitForTimeout(1200); await propre();
    await page.screenshot({ path: `${out}/pro-tableau-de-bord.png` });
    await page.goto('http://localhost:8000/commandes/nouvelle'); await page.waitForTimeout(1500); await propre();
    await page.screenshot({ path: `${out}/pro-nouvelle-commande.png` });
    await page.goto('http://localhost:8000/commandes'); await page.waitForTimeout(1200); await propre();
    await page.screenshot({ path: `${out}/pro-commandes.png` });
    await browser.close();
})();
