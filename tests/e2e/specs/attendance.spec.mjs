import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { installLocalOriginGuard } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const attendancePath = '/departamento-operativo/recursos-humanos/checador/asistencia';
const qaWorkers = [
  { name: 'Alicia Montalvo', state: 'Posiciones completas', times: ['07:59', '17:03'], schedule: '08:00–17:00' },
  { name: 'Bruno Cervantes', state: 'Posiciones completas', times: ['07:53', '13:02', '14:01', '17:11'], schedule: '08:00–13:00; 14:00–17:00' },
  { name: 'Carla Montoya', state: 'Registro parcial', times: ['08:14'], schedule: '08:00–17:00' },
  { name: 'Diego Arriaga', state: 'Horario por revisar', times: ['09:12', '18:03'], schedule: '08:00–17:00' },
  { name: 'Elisa Navarro', state: 'Registro parcial', times: ['08:06', '13:09', '14:07'], schedule: '08:00–13:00; 14:00–17:00' },
];
const oct28Workers = [
  { name: 'Alicia Montalvo', status: 'Jornada completa', times: ['08:00', '17:00'] },
  { name: 'Bruno Cervantes', status: 'Jornada completa', times: ['07:55', '13:00', '14:00', '17:10'] },
  { name: 'Carla Montoya', status: 'Jornada incompleta', times: ['08:12'] },
  { name: 'Diego Arriaga', status: 'Requiere revisión', times: ['09:00', '18:05'] },
  { name: 'Elisa Navarro', status: 'Jornada incompleta', times: ['08:05', '13:10', '14:05'] },
];

async function submitAttendanceSearch(page) {
  const responsePromise = page.waitForResponse((response) => {
    const requestUrl = new URL(response.url());
    return requestUrl.origin === 'http://portal3.test'
      && requestUrl.pathname === attendancePath
      && response.request().method() === 'POST';
  });
  await page.locator('[data-checador-search-action="search"]').click();
  const response = await responsePromise;
  expect(response.status()).toBe(200);
  const payload = await response.json();
  expect(payload.success).toBe(true);
  await expect(page.locator('#checador-filter-modal')).not.toHaveClass(/\bshow\b/);
  await expect(page.locator('#checador-daily-attendance-table_info')).toContainText(/de 46 registros/);
}

test('Asistencia local read-only, QA 29/28, table actions and detail', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA autorizada para ${profile?.key ?? testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  expect(facts.incidence_read).toBe(true);
  ensurePrivateDirectory(candidateScreenshotsRoot);
  const pageErrors = [];
  const consoleErrors = [];
  const unexpectedHttp = [];
  const unexpectedWrites = [];
  const readOnlyAttendanceSearches = [];
  const blockedExternalOrigins = [];
  await installLocalOriginGuard(page, blockedExternalOrigins);
  page.on('pageerror', (error) => pageErrors.push(error.message));
  page.on('console', (message) => {
    if (message.type() !== 'error') return;
    const text = message.text();
    if (text.includes('net::ERR_BLOCKED_BY_CLIENT')) return;
    consoleErrors.push(text);
  });
  page.on('requestfailed', (request) => {
    const url = new URL(request.url());
    if (url.hostname === 'portal3.test') unexpectedHttp.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`);
  });
  page.on('response', (response) => {
    if (response.status() >= 400 && new URL(response.url()).hostname === 'portal3.test') unexpectedHttp.push(`${response.status()} ${response.request().method()} ${response.url()}`);
  });
  page.on('request', (request) => {
    const requestUrl = new URL(request.url());
    if (request.method() === 'POST' && requestUrl.pathname === attendancePath) {
      let payload = null;
      try { payload = request.postDataJSON(); } catch {}
      readOnlyAttendanceSearches.push({
        method: request.method(),
        contentType: request.headers()['content-type'] || '',
        requestedWith: request.headers()['x-requested-with'] || '',
        payloadKeys: payload && typeof payload === 'object' ? Object.keys(payload).sort() : [],
      });
      return;
    }
    if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method()) && !request.url().includes('/api/module-context/set')) {
      unexpectedWrites.push(`${request.method()} ${request.url()}`);
    }
  });

  await page.goto(attendancePath, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.fixed-profile .john-title span')).toHaveText(profile.rolePattern);
  const visibleIdentity = await page.locator('.fixed-profile .john-title h6').innerText();
  expect(visibleIdentity.normalize('NFKC').replace(/\s+/g, ' ').trim().toLocaleLowerCase('es-MX')
    === facts.display_name_prefix.normalize('NFKC').replace(/\s+/g, ' ').trim().toLocaleLowerCase('es-MX')).toBe(true);
  await expect(page.getByRole('heading', { name: 'Asistencia del día' })).toBeVisible();
  const stationSelector = page.locator('#module-station-selector-checador');
  if (await stationSelector.count()) {
    const optionTexts = await stationSelector.locator('option').allTextContents();
    for (const name of optionTexts.filter((text) => text.trim() !== '' && !/^Selecciona/i.test(text.trim()))) {
      expect(facts.localities.some((locality) => name.includes(locality.name)), `Selector contained an out-of-scope locality: ${name}`).toBe(true);
    }
    const interlomas = stationSelector.locator('option').filter({ hasText: /^Interlomas(?:\s|$)/i });
    await expect(interlomas).toHaveCount(1);
    const interlomasValue = await interlomas.getAttribute('value');
    if (interlomasValue && await stationSelector.inputValue() !== interlomasValue) {
      const navigation = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
      await stationSelector.selectOption(interlomasValue);
      await navigation;
    }
  }
  await expect(page.getByText('Interlomas', { exact: false }).first()).toBeVisible();

  const dateFilter = page.locator('#date');
  await page.locator('#checador-search-modal-toggle').click();
  await dateFilter.fill('2026-10-29');
  await submitAttendanceSearch(page);
  const table = page.locator('#checador-daily-attendance-table');
  await expect(table).toBeVisible();
  await expect(page.locator('#checador-daily-attendance-table_info')).toContainText(/de 46 registros/);
  const length = page.locator('#checador-daily-attendance-table_length select');
  if (await length.count()) await length.selectOption('100');
  await expect.poll(async () => page.locator('#checador-daily-attendance-table tbody tr').count()).toBe(46);
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-attendance-1440.png`), fullPage: true });

  let observedQaPunchCount = 0;
  for (const worker of qaWorkers) {
    const row = table.locator('tbody tr').filter({ has: page.getByRole('rowheader', { name: new RegExp(worker.name, 'i') }) });
    await expect(row).toHaveCount(1);
    await expect(row).toContainText(worker.state);
    for (const schedulePart of worker.schedule.split(/[;–]/).map((value) => value.trim()).filter(Boolean)) {
      await expect(row).toContainText(schedulePart);
    }
    for (const time of worker.times) await expect(row).toContainText(time);
    observedQaPunchCount += await row.locator('time').count();
    const filterInput = page.locator('#checador-daily-attendance-table_filter input');
    if (await filterInput.count()) await filterInput.fill(worker.name);
    await expect(row).toBeVisible();
    if (await filterInput.count()) await filterInput.fill('');
  }
  expect(observedQaPunchCount).toBe(12);
  await page.setViewportSize({ width: 375, height: 1000 });
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-attendance-qa-states-375.png`), fullPage: true });
  await page.setViewportSize({ width: 1440, height: 1000 });

  const alicia = table.locator('tbody tr').filter({ hasText: 'Alicia Montalvo' });
  const targetModal = await alicia.locator('[data-bs-target^="#daily-attendance-detail-"]').getAttribute('data-bs-target');
  expect(targetModal).toBeTruthy();
  const trigger = alicia.locator('[data-checador-attendance-actions]');
  await trigger.focus();
  await expect(trigger).toBeFocused();
  await trigger.press('Enter');
  const menu = alicia.locator('.dropdown-menu');
  await expect(menu).toBeVisible();
  const registerIncidenceAction = menu.getByRole('link', { name: 'Registrar incidencia' });
  const registerIncidenceActionCount = await registerIncidenceAction.count();
  expect(registerIncidenceActionCount).toBeLessThanOrEqual(1);
  if (!facts.incidence_create) expect(registerIncidenceActionCount).toBe(0);
  await expect(menu.locator('.dropdown-item')).toHaveCount(3 + registerIncidenceActionCount);
  await expect(menu.getByRole('button', { name: 'Ver detalle' })).toHaveCount(1);
  await expect(menu.getByRole('link', { name: 'Jornada' })).toHaveCount(1);
  await expect(menu.getByRole('link', { name: 'Incidencias' })).toHaveCount(1);
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-attendance-dropdown-1440.png`), fullPage: true });
  await page.setViewportSize({ width: 375, height: 1000 });
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-attendance-dropdown-375.png`), fullPage: true });
  await page.setViewportSize({ width: 1440, height: 1000 });
  await menu.getByRole('button', { name: /Ver detalle/ }).click();
  const detail = page.locator(targetModal);
  await expect(detail).toBeVisible();
  await expect(detail).toContainText('08:00');
  await expect(detail).toContainText('17:00');
  await expect(detail).toContainText('Pendiente de clasificación');
  await expect(detail).not.toContainText(qaWorkers[0].state);
  for (const time of qaWorkers[0].times) await expect(detail).toContainText(time);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-attendance-detail-1440.png`), fullPage: true });
  await page.setViewportSize({ width: 375, height: 1000 });
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-attendance-detail-375.png`), fullPage: true });
  await detail.locator('[data-bs-dismiss="modal"]').first().click();

  for (const width of [375, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 1000 });
    await expect(table).toBeVisible();
    if (width === 375) await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-attendance-375.png`), fullPage: true });
  }

  await page.locator('#checador-search-modal-toggle').click();
  await page.locator('#date').fill('2026-10-28');
  await submitAttendanceSearch(page);
  const lengthAfterDateChange = page.locator('#checador-daily-attendance-table_length select');
  if (await lengthAfterDateChange.count()) await lengthAfterDateChange.selectOption('100');
  await expect.poll(async () => table.locator('tbody tr').count()).toBe(46);
  for (const worker of oct28Workers) {
    const row = table.locator('tbody tr').filter({ hasText: worker.name });
    await expect(row).toHaveCount(1);
    await expect(row).toContainText(worker.status);
    for (const time of worker.times) await expect(row).toContainText(time);
  }
  await expect(table.locator('tbody tr').filter({ hasText: 'Alicia Montalvo' })).not.toContainText('07:59');

  await page.goto('/departamento-operativo/recursos-humanos/checador/incidencias?date=2026-10-28', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#main-wrapper')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Incidencias', exact: true })).toHaveCount(1);
  await expect(page.getByRole('heading', { name: 'Incidencias e historial', exact: true })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Nueva incidencia' })).toHaveCount(facts.incidence_create ? 1 : 0);
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-incidences-1440.png`), fullPage: true });
  await page.setViewportSize({ width: 375, height: 1000 });
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-incidences-375.png`), fullPage: true });

  await expect(page).toHaveURL(/\/departamento-operativo\/recursos-humanos\/checador\/incidencias(?:\?|$)/);
  expect(readOnlyAttendanceSearches.length).toBeGreaterThan(0);
  expect(readOnlyAttendanceSearches.every((item) => item.contentType.startsWith('application/json')
    && item.requestedWith.toLocaleUpperCase('en-US') === 'XMLHTTPREQUEST'
    && JSON.stringify(item.payloadKeys) === JSON.stringify(['date', 'worker_id']))).toBe(true);
  expect(unexpectedWrites, `Unexpected mutating requests: ${unexpectedWrites.join('\n')}`).toEqual([]);
  expect(pageErrors, `JavaScript errors: ${pageErrors.join('\n')}`).toEqual([]);
  expect(consoleErrors, `Console errors: ${consoleErrors.join('\n')}`).toEqual([]);
  expect(unexpectedHttp, `Unexpected local HTTP errors: ${unexpectedHttp.join('\n')}`).toEqual([]);
  testInfo.annotations.push({ type: 'screenshots', description: `Candidate-only screenshots saved to ${candidateScreenshotsRoot}` });
  testInfo.annotations.push({
    type: 'network-policy',
    description: `All non-local HTTP(S) requests were aborted before network access; blocked origins: ${[...new Set(blockedExternalOrigins)].join(', ') || 'none'}`,
  });
});
