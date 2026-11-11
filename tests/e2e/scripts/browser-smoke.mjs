import { chromium } from '@playwright/test';
import { baseURL } from '../paths.mjs';
import { installLocalOriginGuard } from './session-check.mjs';

const browser = await chromium.launch({ channel: 'chrome', headless: true });
try {
  const page = await browser.newPage();
  const blockedOrigins = [];
  await installLocalOriginGuard(page, blockedOrigins);
  const response = await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  if (!response || response.status() !== 200) throw new Error(`Portal3 login returned HTTP ${response?.status() ?? 'no response'}`);
  if (!await page.locator('form').count()) throw new Error('Portal3 login form was not found.');
  if (blockedOrigins.length > 0) throw new Error('Portal3 login attempted to load a non-local origin.');
  console.log(`CHROME_SMOKE=PASS HTTP=${response.status()} URL=${page.url()}`);
} finally {
  await browser.close();
}
