import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles, baseURL } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const route = '/departamento-operativo/recursos-humanos/checador/horarios-turnos';
const evidenceRoot = path.join(candidateScreenshotsRoot, `m04-header-dropdown-${Date.now()}`);

test('M04 header dropdown: sustitución de botones por dropdown institucional, apertura, navegación y responsive', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  ensurePrivateDirectory(evidenceRoot);

  const pageErrors = [];
  const consoleErrors = [];
  const badResponses = [];
  await installLocalOriginGuard(page, []);

  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error' && !message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) {
      consoleErrors.push(message.text());
    }
  });
  page.on('response', response => {
    const url = new URL(response.url());
    if (url.hostname === 'portal3.test' && response.status() >= 400) {
      badResponses.push(`${response.status()} ${response.request().method()} ${url.pathname}`);
    }
  });

  const session = await verifyPortalSession(page, profile.key);
  expect(session.valid, `Profile rejected: ${session.reason}`).toBe(true);

  // 1. Navigate to M04 Horarios y turnos
  const response = await page.goto(`${baseURL}${route}`, { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(200);

  // 2. Verification of absence of standalone header buttons
  // There should be NO top standalone .btn-outline-primary or .btn-outline-secondary links in header
  await expect(page.locator('#container > div a.btn-outline-primary')).toHaveCount(0);
  await expect(page.locator('#container > div a.btn-outline-secondary')).toHaveCount(0);

  // 3. Dropdown presence and institutional styling
  const dropdownContainer = page.locator('#container > div.d-flex.justify-content-end .dropdown');
  await expect(dropdownContainer).toHaveCount(1);

  const dropdownButton = dropdownContainer.getByRole('button', { name: 'Opciones del módulo' });
  await expect(dropdownButton).toHaveCount(1);
  await expect(dropdownButton).toHaveAttribute('data-bs-toggle', 'dropdown');
  await expect(dropdownButton).toHaveAttribute('aria-label', 'Opciones del módulo');
  await expect(dropdownButton).toHaveClass(/border-0/);
  await expect(dropdownButton).toHaveClass(/bg-transparent/);
  await expect(dropdownButton).not.toHaveClass(/btn-light/);
  await expect(dropdownButton.locator('i.ti.ti-dots-vertical.fs-5[aria-hidden="true"]')).toHaveCount(1);

  const dropdownMenu = dropdownContainer.locator('ul.dropdown-menu.dropdown-menu-end');
  await expect(dropdownMenu).toHaveCount(1);

  const items = dropdownMenu.locator('li > a.dropdown-item');
  await expect(items).toHaveCount(2);

  // Item 1: Configuración
  const configItem = items.nth(0);
  await expect(configItem).toContainText('Configuración');
  await expect(configItem).toHaveAttribute('href', '/departamento-operativo/recursos-humanos/checador/configuracion');
  await expect(configItem.locator('i.ti.ti-settings.me-2')).toHaveCount(1);

  // Item 2: Asistencia del día
  const attendanceItem = items.nth(1);
  await expect(attendanceItem).toContainText('Asistencia del día');
  await expect(attendanceItem).toHaveAttribute('href', new RegExp('^/departamento-operativo/recursos-humanos/checador/asistencia'));
  await expect(attendanceItem.locator('i.ti.ti-calendar-check.me-2')).toHaveCount(1);

  // 4. Click interaction: open and close
  await expect(dropdownButton).toHaveAttribute('aria-expanded', 'false');
  await dropdownButton.click();
  await expect(dropdownButton).toHaveAttribute('aria-expanded', 'true');
  await expect(dropdownMenu).toBeVisible();

  // Check positioning: menu must not overflow viewport
  const menuBox = await dropdownMenu.boundingBox();
  const viewport = page.viewportSize();
  expect(menuBox).toBeTruthy();
  if (menuBox && viewport) {
    expect(menuBox.x).toBeGreaterThanOrEqual(0);
    expect(menuBox.x + menuBox.width).toBeLessThanOrEqual(viewport.width + 1);
  }

  // Close by pressing Escape
  await page.keyboard.press('Escape');
  await expect(dropdownButton).toHaveAttribute('aria-expanded', 'false');

  // Open again via keyboard (Space or Enter on focused button)
  await dropdownButton.focus();
  await page.keyboard.press('Enter');
  await expect(dropdownButton).toHaveAttribute('aria-expanded', 'true');
  await expect(dropdownMenu).toBeVisible();

  // Close by clicking outside (e.g. on page body)
  await page.locator('#m04-template-catalog-heading').click();
  await expect(dropdownButton).toHaveAttribute('aria-expanded', 'false');

  // 5. Responsive behavior across viewports: 1440, 768, 375 px
  for (const width of [1440, 1024, 768, 375]) {
    await page.setViewportSize({ width, height: 900 });

    // Open dropdown in this viewport
    await dropdownButton.click();
    await expect(dropdownButton).toHaveAttribute('aria-expanded', 'true');
    await expect(dropdownMenu).toBeVisible();

    const geometry = await page.evaluate(() => {
      const menu = document.querySelector('#container .dropdown-menu');
      const rect = menu?.getBoundingClientRect();
      return {
        docScrollWidth: document.documentElement.scrollWidth,
        viewportWidth: window.innerWidth,
        menuLeft: rect?.left ?? 0,
        menuRight: rect?.right ?? 0,
        menuWidth: rect?.width ?? 0,
      };
    });

    expect(geometry.docScrollWidth).toBeLessThanOrEqual(width + 1);
    expect(geometry.menuLeft).toBeGreaterThanOrEqual(-1);
    expect(geometry.menuRight).toBeLessThanOrEqual(geometry.viewportWidth + 1);

    await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-dropdown-open-${width}px.png`), fullPage: false });

    // Close
    await dropdownButton.click();
    await expect(dropdownButton).toHaveAttribute('aria-expanded', 'false');
  }

  // Reset to 1440px
  await page.setViewportSize({ width: 1440, height: 900 });

  // 6. Navigation to Configuración
  await dropdownButton.click();
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    configItem.click(),
  ]);
  expect(page.url()).toContain('/departamento-operativo/recursos-humanos/checador/configuracion');

  // 7. Navigation back to Horarios y turnos, then to Asistencia del día
  await page.goto(`${baseURL}${route}`, { waitUntil: 'domcontentloaded' });
  const reopenedButton = page.locator('#container > div.d-flex.justify-content-end .dropdown button[aria-label="Opciones del módulo"]');
  await reopenedButton.click();
  const reopenedAttendanceItem = page.locator('#container .dropdown-menu a.dropdown-item').filter({ hasText: 'Asistencia del día' });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    reopenedAttendanceItem.click(),
  ]);
  expect(page.url()).toContain('/departamento-operativo/recursos-humanos/checador/asistencia');

  expect(pageErrors, `JavaScript errors: ${pageErrors.join('\n')}`).toEqual([]);
  expect(consoleErrors, `Console errors: ${consoleErrors.join('\n')}`).toEqual([]);
  expect(badResponses, `Unexpected HTTP bad responses: ${badResponses.join('\n')}`).toEqual([]);
  testInfo.annotations.push({ type: 'candidate-screenshots', description: evidenceRoot });
});
