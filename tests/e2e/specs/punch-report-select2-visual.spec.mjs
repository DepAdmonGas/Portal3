import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, baseURL } from '../paths.mjs';

const reportPath = '/departamento-operativo/recursos-humanos/checador/reporte';

async function fieldGeometry(page) {
  return page.evaluate(() => {
    const container = document.querySelector('.punch-report-worker-field > .select2-container');
    const field = container?.querySelector('.select2-selection--single');
    const arrow = container?.querySelector('.select2-selection__arrow');
    const clear = container?.querySelector('.select2-selection__clear');
    const rect = (element) => {
      if (!element) return null;
      const box = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      return {
        left: box.left,
        right: box.right,
        top: box.top,
        bottom: box.bottom,
        width: box.width,
        height: box.height,
        position: style.position,
        lineHeight: style.lineHeight,
        border: style.border,
      };
    };
    const fieldBox = field?.getBoundingClientRect();
    const arrowBox = arrow?.getBoundingClientRect();
    const clearBox = clear?.getBoundingClientRect();
    const periodBox = document.querySelector('#punch-report-period')?.getBoundingClientRect();
    const dropdownBox = document.querySelector('.select2-dropdown.punch-report-worker-dropdown')?.getBoundingClientRect();
    return {
      container: rect(container),
      field: rect(field),
      arrow: rect(arrow),
      clear: rect(clear),
      arrowInsideField: Boolean(fieldBox && arrowBox
        && arrowBox.left >= fieldBox.left && arrowBox.right <= fieldBox.right
        && arrowBox.top >= fieldBox.top && arrowBox.bottom <= fieldBox.bottom),
      arrowCentered: Boolean(fieldBox && arrowBox
        && Math.abs((arrowBox.top + arrowBox.bottom) / 2 - (fieldBox.top + fieldBox.bottom) / 2) <= 1),
      clearDoesNotOverlapArrow: !clearBox || !arrowBox
        || clearBox.right <= arrowBox.left || clearBox.left >= arrowBox.right,
      dropdown: rect(document.querySelector('.select2-dropdown.punch-report-worker-dropdown')),
      dropdownAligned: Boolean(fieldBox && dropdownBox
        && Math.abs(dropdownBox.left - fieldBox.left) <= 1
        && Math.abs(dropdownBox.width - fieldBox.width) <= 1),
      periodHeight: periodBox?.height ?? null,
      search: rect(document.querySelector('.select2-dropdown.punch-report-worker-dropdown .select2-search__field')),
      documentWidth: document.documentElement.scrollWidth,
      viewportWidth: window.innerWidth,
    };
  });
}

test('M02 Select2 de colaborador conserva alineación y controles responsive', async ({ page }) => {
  test.skip(!fs.existsSync(authStatePath('encargado')), 'BLOCKED: falta sesión QA autorizada de Encargado');
  fs.mkdirSync(candidateScreenshotsRoot, { recursive: true, mode: 0o700 });
  fs.chmodSync(candidateScreenshotsRoot, 0o700);

  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));
  await page.goto(`${baseURL}${reportPath}`, { waitUntil: 'domcontentloaded' });
  const select2 = page.locator('#select2-punch-report-worker-container');
  await expect(select2).toBeVisible();

  for (const width of [1440, 1024, 768, 375]) {
    await page.setViewportSize({ width, height: 950 });
    const closed = await fieldGeometry(page);
    expect(closed.arrowInsideField, `${width}px arrow inside field: ${JSON.stringify(closed)}`).toBe(true);
    expect(closed.arrowCentered, `${width}px arrow vertically centered`).toBe(true);
    expect(closed.field?.height, `${width}px field height`).toBe(closed.periodHeight);
    expect(closed.documentWidth, `${width}px no horizontal overflow`).toBeLessThanOrEqual(width);
    expect(closed.periodHeight, `${width}px Periodo height`).toBe(closed.field?.height);
    await page.screenshot({ path: path.join(candidateScreenshotsRoot, `encargado-m02-select2-closed-${width}.png`), fullPage: false });

    await select2.click();
    await expect(page.locator('.select2-dropdown.punch-report-worker-dropdown')).toBeVisible();
    const searchField = page.locator('.select2-dropdown.punch-report-worker-dropdown .select2-search__field');
    await searchField.focus();
    await expect(page.locator('.select2-container--open .select2-results__option').first()).toBeVisible();
    const open = await fieldGeometry(page);
    expect(open.arrowInsideField, `${width}px open arrow inside field`).toBe(true);
    expect(open.arrowCentered, `${width}px open arrow vertically centered`).toBe(true);
    expect(open.dropdown?.left).toBeGreaterThanOrEqual(0);
    expect(open.dropdown?.right).toBeLessThanOrEqual(width);
    expect(open.dropdownAligned, `${width}px dropdown aligned to field`).toBe(true);
    expect(open.search?.height).toBeGreaterThanOrEqual(36);
    expect(open.search?.border).toContain('1px');
    expect(open.documentWidth, `${width}px open dropdown no horizontal overflow`).toBeLessThanOrEqual(width);
    const focusShadow = await searchField.evaluate((element) => getComputedStyle(element).boxShadow);
    expect(focusShadow).not.toBe('none');
    await page.screenshot({ path: path.join(candidateScreenshotsRoot, `encargado-m02-select2-open-${width}.png`), fullPage: false });
    await page.keyboard.press('Escape');
  }

  const workerOptions = await page.locator('.select2-container--open .select2-results__option').count().catch(() => 0);
  if (workerOptions === 0) {
    await page.setViewportSize({ width: 1440, height: 950 });
    await select2.click();
  }
  const firstWorker = page.locator('.select2-container--open .select2-results__option').filter({ hasNotText: 'Todos los colaboradores' }).first();
  await expect(firstWorker).toBeVisible();
  const workerName = (await firstWorker.innerText()).trim();
  await firstWorker.click();
  const selected = await fieldGeometry(page);
  expect(selected.arrowInsideField).toBe(true);
  expect(selected.arrowCentered).toBe(true);
  expect(selected.clear).not.toBeNull();
  expect(selected.clearDoesNotOverlapArrow).toBe(true);
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, 'encargado-m02-select2-selected-1440.png'), fullPage: false });
  await page.locator('.punch-report-worker-field > .select2-container .select2-selection__clear').click();
  await expect(select2).toContainText('Todos los colaboradores');
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, 'encargado-m02-select2-cleared-1440.png'), fullPage: false });

  expect(workerName.trim()).not.toBe('');
  expect(pageErrors).toEqual([]);
});
