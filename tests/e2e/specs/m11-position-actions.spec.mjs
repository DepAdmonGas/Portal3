import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const configurationPath = '/departamento-operativo/recursos-humanos/checador/configuracion';

test('M11 Personal y puestos: crear y editar conserva acciones y permisos', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  const evidenceRoot = path.join(candidateScreenshotsRoot, `m11-position-actions-${Date.now()}-${profile.key}`);
  ensurePrivateDirectory(evidenceRoot);

  const writes = [];
  const externalRequests = [];
  const pageErrors = [];
  const consoleErrors = [];
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error' && !message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) consoleErrors.push(message.text());
  });
  await page.route('**/*', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (['http:', 'https:'].includes(url.protocol) && url.hostname !== 'portal3.test') {
      externalRequests.push(url.origin);
      await route.abort('blockedbyclient');
      return;
    }
    if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) {
      writes.push(`${request.method()} ${url.pathname}`);
      await route.abort('blockedbyclient');
      return;
    }
    await route.continue();
  });

  await page.goto(configurationPath, { waitUntil: 'domcontentloaded' });
  const section = page.locator('#people-positions');
  if (!facts.position_catalog_read) {
    await expect(section).toHaveCount(0);
    expect(writes).toEqual([]);
    expect(pageErrors).toEqual([]);
    expect(consoleErrors).toEqual([]);
    return;
  }
  await expect(section).toBeVisible();
  const canEdit = facts.position_catalog_edit === true;
  const createButton = section.getByRole('button', { name: 'Nuevo', exact: true });
  if (!canEdit) {
    await expect(createButton).toHaveCount(0);
    await expect(section.locator('#m11-position-table .dropdown')).toHaveCount(0);
    expect(writes).toEqual([]);
    expect(externalRequests.every(origin => origin === 'https://cdn.jsdelivr.net')).toBe(true);
    expect(pageErrors).toEqual([]);
    expect(consoleErrors).toEqual([]);
    return;
  }

  await expect(createButton).toBeVisible();
  await expect(createButton).toHaveClass(/\bbtn-light\b/);
  await expect(createButton).toHaveClass(/\bbtn-sm\b/);
  await createButton.click();
  const modal = page.locator('#m11-position-modal');
  await expect(modal).toBeVisible();
  await expect(modal.getByRole('heading', { name: 'Nuevo puesto', exact: true })).toBeVisible();
  await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-create-modal-1440.png`), fullPage: true });
  await modal.getByRole('button', { name: 'Cancelar' }).click();
  await expect(modal).toBeHidden();
  await page.setViewportSize({ width: 375, height: 900 });
  await createButton.click();
  await expect(modal.getByRole('heading', { name: 'Nuevo puesto', exact: true })).toBeVisible();
  await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-create-modal-375.png`), fullPage: true });
  await modal.getByRole('button', { name: 'Cancelar' }).click();
  await expect(modal).toBeHidden();
  await page.setViewportSize({ width: 1440, height: 900 });

  const firstRow = section.locator('#m11-position-table tbody tr').first();
  await expect(firstRow).toBeVisible();
  const positionName = (await firstRow.locator('td').first().innerText()).trim();
  const trigger = firstRow.locator('.dropdown > a[data-bs-toggle="dropdown"]');
  await expect(trigger).toHaveAttribute('aria-label', 'Opciones del puesto');
  await expect(trigger).toHaveAttribute('role', 'button');
  await expect(trigger).toHaveAttribute('tabindex', '0');
  await trigger.focus();
  await page.keyboard.press('Enter');
  const editAction = firstRow.getByRole('button', { name: 'Editar', exact: true });
  await expect(editAction).toBeVisible();
  await expect(trigger).toHaveAttribute('aria-expanded', 'true');
  await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-dropdown-1440.png`), fullPage: true });
  await editAction.focus();
  await page.keyboard.press('Enter');
  await expect(modal).toBeVisible();
  await expect(modal.getByRole('heading', { name: 'Editar puesto', exact: true })).toBeVisible();
  await expect(modal.locator('#m11-position-name')).toHaveValue(positionName);

  for (const width of [1440, 768, 375]) {
    await page.setViewportSize({ width, height: 900 });
    await expect(modal).toBeVisible();
    await expect(modal.locator('#m11-position-name')).toHaveValue(positionName);
    await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-edit-modal-${width}.png`), fullPage: true });
  }
  await modal.getByRole('button', { name: 'Cancelar' }).click();
  await expect(modal).toBeHidden();

  await page.setViewportSize({ width: 375, height: 900 });
  await trigger.click();
  await expect(editAction).toBeVisible();
  await editAction.click();
  await expect(modal.locator('#m11-position-name')).toHaveValue(positionName);
  await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-edit-modal-375.png`), fullPage: true });
  await modal.getByRole('button', { name: 'Cancelar' }).click();
  await expect(modal).toBeHidden();
  expect(writes).toEqual([]);
  expect(externalRequests.every(origin => origin === 'https://cdn.jsdelivr.net')).toBe(true);
  expect(pageErrors).toEqual([]);
  expect(consoleErrors).toEqual([]);
});
