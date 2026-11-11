import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const configurationPath = '/departamento-operativo/recursos-humanos/checador/configuracion';

test('M11 Perfil del lector: lectura y edición respetan el perfil autenticado', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  const evidenceRoot = path.join(candidateScreenshotsRoot, `m11-reader-profile-${Date.now()}-${profile.key}`);
  ensurePrivateDirectory(evidenceRoot);
  const writes = [];
  const pageErrors = [];
  const browserApiRequests = [];
  let deviceInventoryResponse = null;
  await page.route('**/*', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (['http:', 'https:'].includes(url.protocol) && url.hostname !== 'portal3.test') {
      if (url.port === '8081' || url.pathname.startsWith('/api/v2/')) browserApiRequests.push(url.origin);
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
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('response', async response => {
    if (new URL(response.url()).pathname === '/departamento-operativo/recursos-humanos/checador/configuracion/dispositivos/data') {
      deviceInventoryResponse = { status: response.status(), body: await response.json().catch(() => null) };
    }
  });

  const response = await page.goto(configurationPath, { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(200);
  const positionsCard = page.locator('#people-positions');
  if (facts.position_catalog_read) {
    await expect(positionsCard).toBeVisible();
    await expect(positionsCard.locator('#m11-position-table')).toBeVisible();
  } else {
    await expect(positionsCard).toHaveCount(0);
  }
  const card = page.locator('#reader-access');
  await expect(card).toBeVisible();
  await expect(card.getByRole('heading', { name: 'Perfil del lector', exact: true })).toBeVisible();
  await expect(card.locator('.card-header .ti-device-desktop')).toBeVisible();
  await expect(card.getByRole('heading', { name: 'Lectores y accesos', exact: true })).toHaveCount(0);
  await expect(card.getByText('Acceso de lectores', { exact: true })).toHaveCount(0);
  await expect(card.getByText('No integrado', { exact: true })).toHaveCount(0);
  await expect(card).not.toContainText(/credenciales de dispositivo V2|perfil de aplicación anterior|Legacy/i);

  const devices = page.locator('#m11-devices');
  const assignments = page.locator('#m11-station-readers');
  const permissions = page.locator('#m11-effective-permissions');
  const history = page.locator('#m11-change-history');
  if (facts.device_inventory_read) {
    await expect(devices.getByRole('heading', { name: 'Administración de dispositivos', exact: true })).toBeVisible();
    await expect(devices.locator('#m11-devices-table')).toBeVisible();
    await expect(devices.locator('#m11-devices-status')).not.toContainText('Consultando inventario');
    await expect.poll(() => deviceInventoryResponse?.status).toBe(200);
    expect(deviceInventoryResponse.body.status).toBe('OK');
    expect(['COMPLETE', 'PARTIAL', 'UNKNOWN']).toContain(deviceInventoryResponse.body.completeness);
    expect(Array.isArray(deviceInventoryResponse.body.data)).toBe(true);
    const authorizedLocalities = new Set(facts.localities.map(item => Number(item.id)));
    for (const item of deviceInventoryResponse.body.data) {
      const workplaceId = item?.workplace?.id;
      if (workplaceId !== null && workplaceId !== undefined) {
        expect(authorizedLocalities.has(Number(workplaceId))).toBe(true);
      }
      expect(item).not.toHaveProperty('online');
      expect(item.device).not.toHaveProperty('credential_key');
      expect(item).not.toHaveProperty('payload_json');
    }
    if (profile.expectedPositionId === 6) {
      const expectedIds = profile.key === 'encargado-palo-solo' ? [2, 9] : [1];
      expect([...authorizedLocalities].sort()).toEqual(expectedIds.sort());
      expect(deviceInventoryResponse.body.data.every(item => item.workplace?.id === null || expectedIds.includes(Number(item.workplace.id)))).toBe(true);
    }
  } else {
    await expect(devices).toHaveCount(0);
  }
  await expect(assignments.getByRole('heading', { name: 'Estaciones y lectores asignados', exact: true })).toBeVisible();
  await expect(assignments).toContainText('Localidad canónica');
  await expect(assignments).toContainText('Estación física');
  await expect(permissions.getByRole('heading', { name: 'Permisos efectivos', exact: true })).toBeVisible();
  await expect(permissions).toContainText('Permiso efectivo');
  await expect(permissions).toContainText('Administración de dispositivos');
  await expect(history.getByRole('heading', { name: 'Historial de cambios', exact: true })).toBeVisible();
  await expect(history).not.toContainText(/contraseña actual|secreto|token/i);

  const readerItems = card.locator('[aria-label="Usuarios activos del lector"] > div');
  if (facts.reader_profile_read) {
    await expect(readerItems.first()).toBeVisible();
    const username = (await readerItems.first().locator('.fw-semibold').innerText()).trim();
    expect(username).not.toBe('');
    if (facts.reader_profile_edit) {
      const editButton = readerItems.first().getByRole('button', { name: 'Editar', exact: true });
      await expect(editButton).toBeVisible();
      await editButton.click();
      const modal = page.locator('#m11-reader-profile-modal');
      await expect(modal).toBeVisible();
      await expect(modal.locator('[name="_csrf_token"]')).toHaveValue(/.+/);
      await expect(modal.locator('[name="profile_id"]')).toHaveValue(/^[1-9]\d*$/);
      await expect(modal.locator('[name="username"]')).toHaveValue(username);
      await expect(modal.locator('[name="new_password"]')).toHaveValue('');
      await expect(modal.locator('[name="new_password_confirm"]')).toHaveValue('');
      await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-reader-profile-modal-1440.png`), fullPage: true });

      await modal.locator('[name="new_password"]').fill('prueba-sin-guardar');
      await modal.locator('[name="new_password_confirm"]').fill('valor-distinto');
      await modal.getByRole('button', { name: 'Guardar cambios', exact: true }).click();
      await expect(modal.getByRole('alert')).toContainText('no coincide');
      expect(writes).toEqual([]);
      await modal.getByRole('button', { name: 'Cancelar', exact: true }).click();
      await expect(modal).toBeHidden();
      await expect(page.locator('.modal-backdrop')).toHaveCount(0);
    } else {
      await expect(card.getByRole('button', { name: 'Editar', exact: true })).toHaveCount(0);
      await expect(card.locator('#m11-reader-profile-modal')).toHaveCount(0);
    }
  }

  for (const width of [1440, 768, 375]) {
    await page.setViewportSize({ width, height: 950 });
    if (width < 1200 && await page.locator('#main-wrapper').evaluate(element => element.classList.contains('show-sidebar'))) {
      await page.locator('.sidebartoggler').first().click();
      await expect(page.locator('#main-wrapper')).not.toHaveClass(/\bshow-sidebar\b/);
    }
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width + 1);
    await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-reader-profile-${width}.png`), fullPage: true });
  }
  expect(writes).toEqual([]);
  expect(browserApiRequests).toEqual([]);
  expect(pageErrors).toEqual([]);
});

test('M11 muestra la incertidumbre de asignación sin derivar conectividad (respuesta aislada)', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);
  const writes = [];
  const pageErrors = [];
  await page.route('**/*', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (['http:', 'https:'].includes(url.protocol) && url.hostname !== 'portal3.test') {
      await route.abort('blockedbyclient');
      return;
    }
    if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) {
      writes.push(`${request.method()} ${url.pathname}`);
      await route.abort('blockedbyclient');
      return;
    }
    if (url.pathname === '/departamento-operativo/recursos-humanos/checador/configuracion/dispositivos/data') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        json: {
          status: 'OK', completeness: 'PARTIAL', read_at_utc: '2026-11-03T20:00:00Z',
          partial_reasons: ['ASSIGNMENT_HISTORY_AMBIGUOUS'],
          data: [{
            device: { id: 9001, uuid: 'm11-isolated-opaque', name: 'Dispositivo de lectura aislada', persisted_status: 'ACTIVE' },
            physical_station: { id: 7, name: 'Interlomas', numlista: 2 },
            workplace: { id: 1, name: 'Interlomas', mapping_status: 'VERIFIED' },
            assignment: { status: 'UNKNOWN', valid_from_utc: null, valid_to_utc: null, source: 'DEVICE_STATION_HISTORY' },
            group: { id: null, name: null, mapping_status: 'UNAVAILABLE' },
            last_communication: { last_server_received_at_utc: null, source: 'ACCEPTED_DEVICE_TELEMETRY' },
          }],
        },
      });
      return;
    }
    await route.continue();
  });
  page.on('pageerror', error => pageErrors.push(error.message));

  const response = await page.goto(configurationPath, { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(200);
  const devices = page.locator('#m11-devices');
  await expect(devices).toBeVisible();
  await expect(devices.locator('#m11-devices-status')).toContainText('Inventario parcial');
  await expect(devices.locator('#m11-devices-table tbody')).toContainText('No confirmado');
  await expect(devices.locator('#m11-devices-table tbody')).toContainText('Sin recepción registrada');
  await expect(devices.locator('#m11-devices-table tbody')).not.toContainText(/en línea|fuera de línea|sin asignación confirmada/i);
  await page.setViewportSize({ width: 375, height: 900 });
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(376);
  await expect(devices.locator('#m11-devices-status')).toContainText('Inventario parcial');
  const evidenceRoot = path.join(candidateScreenshotsRoot, `m11-devices-partial-mock-${Date.now()}-${profile.key}`);
  ensurePrivateDirectory(evidenceRoot);
  await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-partial-375.png`), fullPage: true });
  expect(writes).toEqual([]);
  expect(pageErrors).toEqual([]);
});
