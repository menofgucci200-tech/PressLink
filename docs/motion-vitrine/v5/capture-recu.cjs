// Capture du reçu « ticket à talon » (commande de démo locale) pour le film.
const { chromium } = require('playwright-core');
(async () => {
    const [out, commande] = process.argv.slice(2);
    const browser = await chromium.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
    const page = await browser.newPage({ viewport: { width: 1200, height: 1600 }, deviceScaleFactor: 2, colorScheme: 'light' });
    await page.goto('http://localhost:8000/login');
    await page.fill('input[type=text]', '0700000001');
    await page.fill('input[type=password]', 'password');
    await page.click('button[type=submit]');
    await page.waitForURL('**/tableau-de-bord');
    await page.goto(`http://localhost:8000/commandes/${commande}/recu`);
    await page.emulateMedia({ media: 'print' });
    await page.waitForTimeout(800);
    const feuille = page.locator('.sheet').first();
    await feuille.screenshot({ path: `${out}/recu-ticket.png`, omitBackground: true });
    await browser.close();
})();
