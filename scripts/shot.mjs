// Dev-only screenshot helper. Usage: node scripts/shot.mjs <path> <outfile> [width]
import { chromium } from 'playwright';
const [path = '/', out = 'shot.png', width = '1440', locale = ''] = process.argv.slice(2);
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const page = await browser.newPage({ viewport: { width: +width, height: 1000 } });
if (locale) await page.goto('http://127.0.0.1:8000/locale/' + locale, { waitUntil: 'networkidle', timeout: 30000 }).catch(() => {});
await page.goto('http://127.0.0.1:8000' + path, { waitUntil: 'networkidle', timeout: 30000 }).catch(() => {});
// Scroll through the page to trigger lazy-loaded images, then back to top.
await page.evaluate(async () => {
  const step = window.innerHeight;
  for (let y = 0; y < document.body.scrollHeight; y += step) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 150)); }
  window.scrollTo(0, 0);
});
await page.waitForTimeout(800);
await page.screenshot({ path: out, fullPage: true });
await browser.close();
console.log('shot ->', out);
