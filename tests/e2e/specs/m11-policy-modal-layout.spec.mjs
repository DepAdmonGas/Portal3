import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';

const configurationPath = '/departamento-operativo/recursos-humanos/checador/configuracion';

test('M11 política: los campos del modal conservan filas completas y alineación responsiva', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);
  const evidenceRoot = path.join(candidateScreenshotsRoot, `m11-policy-modal-layout-${Date.now()}-${profile.key}`);
  ensurePrivateDirectory(evidenceRoot);

  const blockedWrites = [];
  const pageErrors = [];
  await page.route('**/*', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (['http:', 'https:'].includes(url.protocol) && url.hostname !== 'portal3.test') {
      await route.abort('blockedbyclient');
      return;
    }
    if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) {
      blockedWrites.push(`${request.method()} ${url.pathname}`);
      await route.abort('blockedbyclient');
      return;
    }
    await route.continue();
  });
  page.on('pageerror', error => pageErrors.push(error.message));

  const response = await page.goto(configurationPath, { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(200);
  const modal = page.locator('#attendance-policy-edit-modal');
  const openButton = page.getByRole('button', { name: 'Editar configuración', exact: true });
  await expect(openButton).toBeVisible();

  for (const width of [1440, 768, 375]) {
    await page.setViewportSize({ width, height: 950 });
    await openButton.click();
    await expect(modal).toBeVisible();
    await expect(modal.locator('.modal-dialog')).toHaveClass(/\bmodal-md\b/);

    const row = modal.locator('.modal-body > .row.g-3');
    const fields = [
      ['#attendance-policy-effective-from', 'Fecha de vigencia'],
      ['#attendance-policy-tolerance', 'Tolerancia (minutos)'],
      ['#attendance-policy-deadline', 'Plazo de incidencias (días naturales)'],
    ];
    const widths = [];
    let lastFieldBottom = 0;
    for (const [selector, labelText] of fields) {
      const input = modal.locator(selector);
      await expect(input).toBeVisible();
      await expect(modal.getByText(labelText, { exact: true })).toBeVisible();
      const columnClass = await input.evaluate(element => element.parentElement?.className || '');
      expect(columnClass.split(/\s+/)).toContain('col-12');
      const geometry = await input.evaluate(element => {
        const rect = element.getBoundingClientRect();
        return { width: rect.width, bottom: rect.bottom };
      });
      widths.push(geometry.width);
      lastFieldBottom = Math.max(lastFieldBottom, geometry.bottom);
    }
    expect(Math.max(...widths) - Math.min(...widths)).toBeLessThanOrEqual(1);
    const checkbox = modal.locator('#attendance-policy-confirm');
    await expect(checkbox).toBeVisible();
    const checkboxTop = await checkbox.evaluate(element => element.getBoundingClientRect().top);
    expect(checkboxTop).toBeGreaterThan(lastFieldBottom);
    const cancel = modal.getByRole('button', { name: 'Cancelar', exact: true });
    const save = modal.getByRole('button', { name: 'Guardar cambios', exact: true });
    await expect(cancel).toBeVisible();
    await expect(save).toBeVisible();
    const footerButtons = await Promise.all([cancel, save].map(button => button.boundingBox()));
    expect(footerButtons[0]).not.toBeNull();
    expect(footerButtons[1]).not.toBeNull();
    expect(Math.abs(footerButtons[0].y - footerButtons[1].y)).toBeLessThanOrEqual(1);

    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width + 1);
    await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-edit-policy-${width}.png`), fullPage: true });
    await cancel.click();
    await expect(modal).toBeHidden();
  }

  expect(blockedWrites).toEqual([]);
  expect(pageErrors).toEqual([]);
});
