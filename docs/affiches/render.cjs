// Rend chaque affiche de src/ en PNG (≈ 290 dpi) et en PDF A3.
// Usage : node docs/affiches/render.cjs   (Playwright + Chromium requis)
const { chromium } = require('playwright');
const { readdirSync } = require('node:fs');
const { join } = require('node:path');
const { pathToFileURL } = require('node:url');

const here = __dirname;
const src = join(here, 'src');
const only = process.argv[2];
const pages = readdirSync(src).filter((f) => f.endsWith('.html') && (!only || f.includes(only)));

(async () => {
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1123, height: 1588 }, deviceScaleFactor: 3 });
for (const file of pages) {
    const page = await context.newPage();
    await page.goto(pathToFileURL(join(src, file)).href);
    await page.evaluate(() => document.fonts.ready);
    const name = file.replace(/\.html$/, '');
    await page.locator('.poster').screenshot({ path: join(here, `${name}.png`) });
    await page.pdf({ path: join(here, `${name}.pdf`), width: '297mm', height: '420mm', printBackground: true });
    console.log('✓', name);
    await page.close();
}
await browser.close();
})();
