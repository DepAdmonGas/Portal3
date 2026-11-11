import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const route = '/departamento-operativo/recursos-humanos/checador/horarios-turnos';
const evidenceRoot = path.join(candidateScreenshotsRoot, `m04-template-catalog-${Date.now()}`);

test('M04: lectura de catálogo V2 por perfil sin escrituras', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.schedule_read).toBe(true);
  expect(facts.template_admin).toBe(false);
  ensurePrivateDirectory(evidenceRoot);

  const writes = [];
  const pageErrors = [];
  const badResponses = [];
  await installLocalOriginGuard(page, []);
  page.on('request', request => {
    const url = new URL(request.url());
    if (url.hostname === 'portal3.test' && !['GET', 'HEAD', 'OPTIONS'].includes(request.method())) writes.push(`${request.method()} ${url.pathname}`);
  });
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('response', response => {
    const url = new URL(response.url());
    if (url.hostname === 'portal3.test' && response.status() >= 400) badResponses.push(`${response.status()} ${response.request().method()} ${url.pathname}`);
  });

  const session = await verifyPortalSession(page, profile.key);
  expect(session.valid, `Profile rejected: ${session.reason}`).toBe(true);

  const dashboardRoute = '/departamento-operativo/recursos-humanos/checador';
  const dashboardResponse = await page.goto(dashboardRoute, { waitUntil: 'domcontentloaded' });
  expect(dashboardResponse?.status()).toBe(200);
  const scheduleCard = page.locator('a').filter({ has: page.getByRole('heading', { name: 'Horarios y turnos', exact: true }) });
  await expect(scheduleCard).toHaveCount(1);
  await expect(scheduleCard).toHaveAttribute('href', route);
  await scheduleCard.click();
  await expect(page).toHaveURL(new RegExp(`${route.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&')}$`));
  await expect(page.locator('#m04-template-catalog')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Horarios y turnos', exact: true })).toBeVisible();
  await expect(page.locator('[aria-label="breadcrumb"]')).toContainText('Horarios y turnos');
  await expect(page.locator('[aria-label="breadcrumb"]')).not.toContainText('Vigencias de horarios');
  const expectedCount = 15;
  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(expectedCount);
  await page.goBack({ waitUntil: 'domcontentloaded' });
  await expect(page).toHaveURL(new RegExp(`${dashboardRoute}$`));
  await page.goForward({ waitUntil: 'domcontentloaded' });
  await expect(page).toHaveURL(new RegExp(`${route.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&')}$`));

  const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(200);
  await expect(page).toHaveURL(new RegExp(`${route.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&')}$`));

  const legacyRedirects = [];
  const captureLegacyRedirect = responseItem => {
    const responseUrl = new URL(responseItem.url());
    if (responseUrl.pathname === dashboardRoute && responseUrl.searchParams.get('schedule_history') === '1') {
      legacyRedirects.push({ status: responseItem.status(), location: responseItem.headers()['location'] ?? '' });
    }
  };
  page.on('response', captureLegacyRedirect);
  const legacyResponse = await page.goto(`${dashboardRoute}?schedule_history=1&date=2026-11-02&worker_id=1004`, { waitUntil: 'domcontentloaded' });
  const redirectedUrl = new URL(page.url());
  expect(redirectedUrl.pathname).toBe(route);
  expect(redirectedUrl.searchParams.get('date')).toBe('2026-11-02');
  expect(redirectedUrl.searchParams.get('worker_id')).toBe('1004');
  expect(legacyRedirects.some(({ status, location }) => [301, 302, 303, 307, 308].includes(status)
    && location.includes('/horarios-turnos'))).toBe(true);
  page.off('response', captureLegacyRedirect);
  expect(legacyResponse?.status()).toBe(200);
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(expectedCount);
  await expect(page.locator('#m04-template-catalog')).toBeVisible();
  await expect(page.locator('#m04-template-table')).toBeVisible();
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(expectedCount);
  await expect(page.locator('#m04-template-catalog')).toHaveAttribute('data-workplace-id', '1');
  if (profile.key === 'direccion-operaciones') {
    await expect(page.locator('#m04-btn-new-template')).toHaveCount(0);
  }
  await expect(page.getByRole('heading', { name: 'Consultar vigencia' })).toHaveCount(0);
  await expect(page.getByLabel('Trabajador canónico')).toHaveCount(0);
  await expect(page.getByLabel('Fecha local consultada')).toHaveCount(0);
  const expectedHeaders = ['Turno', 'Entrada', 'Inicio de descanso', 'Fin de descanso', 'Salida final', 'Estado'];
  if (profile.key === 'encargado' && facts.station_template_admin) expectedHeaders.push('Acciones');
  await expect(page.locator('#m04-template-table thead th')).toHaveText(expectedHeaders);
  const catalogText = await page.locator('#m04-template-table').innerText();
  expect(catalogText).not.toMatch(/LEGACY_[A-Z0-9_]+/i);
  expect(catalogText).not.toMatch(/\bv\d+\b/i);
  expect(catalogText).not.toContain('Legacy Interlomas');
  expect(catalogText).not.toMatch(/\b(?:ID|identificador)\s*[:#]?\s*\d+/i);
  expect(catalogText).not.toMatch(/Catálogo V2 de solo lectura|Catálogo recibido|plantillas recibidas|contrato API/i);
  const catalogCells = await page.locator('#m04-template-table tbody tr').evaluateAll(rows => rows.map(row => [...row.cells].map(cell => cell.innerText.trim())));
  expect(catalogCells).toHaveLength(expectedCount);
  expect(catalogCells.every(cells => cells.length === expectedHeaders.length)).toBe(true);
  if (profile.key === 'encargado' && facts.station_template_admin) {
    const rowsWithEditActions = await page.locator('#m04-template-table tbody tr').filter({ has: page.locator('[data-m04-edit-template]') }).count();
    expect(rowsWithEditActions).toBe(3);
  }
  const baseCatalogRows = [
    ['ASISTENTE / INTENDENCIA', '09:00', '—', '—', '18:00', 'Activa'],
    ['ASISTENTE SABADO', '09:00', '—', '—', '15:00', 'Activa'],
    ['BANDERERO DE APOYO 1', '08:00', '—', '—', '14:00', 'Activa'],
    ['BANDERERO FULL', '08:00', '—', '—', '19:00', 'Activa'],
    ['ENCARGADO 2', '14:00', '—', '—', '22:00', 'Activa'],
    ['ENCARGADO 3', '22:00', '—', '—', '06:00 (+1 día)', 'Activa'],
    ['FACTURISTA 1', '07:50', '—', '—', '14:00', 'Activa'],
    ['FACTURISTA 2', '13:50', '—', '—', '20:00', 'Activa'],
    ['INTENDENCIA SABADO', '09:00', '—', '—', '15:30', 'Activa'],
    ['TURNO 1 / ENCARGADO 1', '06:00', '—', '—', '14:00', 'Activa'],
    ['TURNO 2 / BANDERERO DE APOYO 2', '14:00', '—', '—', '20:00', 'Activa'],
    ['TURNO 3', '20:00', '—', '—', '06:00 (+1 día)', 'Activa'],
  ];
  const globalNames = new Set(baseCatalogRows.map(([name]) => name));
  const globalRows = catalogCells.filter(([name]) => globalNames.has(name));
  expect(globalRows).toHaveLength(12);
  expect(globalRows.map(cells => cells.slice(0, 6)).sort((left, right) => left.join('|').localeCompare(right.join('|'))))
    .toEqual([...baseCatalogRows].sort((left, right) => left.join('|').localeCompare(right.join('|'))));
  const localRows = catalogCells.filter(([name]) => ['Turno Local Matutino', 'Turno 4', 'Turno 4 · Edición QA 20261104', 'Turno 5'].includes(name));
  const turn4Rows = localRows.filter(([name]) => name === 'Turno 4' || name === 'Turno 4 · Edición QA 20261104');
  expect(turn4Rows).toHaveLength(1);
  expect(turn4Rows[0].slice(1, 6)).toEqual(['14:00', '18:00', '19:00', '22:00', 'Activa']);
  expect(localRows.filter(([name]) => name !== 'Turno 4' && name !== 'Turno 4 · Edición QA 20261104').map(cells => cells.slice(0, 6))
    .sort((left, right) => left.join('|').localeCompare(right.join('|')))).toEqual([
      ['Turno 5', '17:00', '21:00', '22:00', '02:00 (+1 día)', 'Activa'],
      ['Turno Local Matutino', '07:00', '—', '—', '15:00', 'Activa'],
    ].sort((left, right) => left.join('|').localeCompare(right.join('|'))));
  expect(catalogCells).toHaveLength(15);
  const overnightRows = catalogCells.filter(cells => /\+1 día/.test(cells[4]));
  expect(overnightRows).toHaveLength(3);
  expect(overnightRows.map(cells => cells[1]).sort()).toEqual(['17:00', '20:00', '22:00']);
  expect(overnightRows.filter(cells => cells[4].startsWith('06:00'))).toHaveLength(2);
  expect(overnightRows.filter(cells => cells[4].startsWith('02:00'))).toHaveLength(1);
  const workplaceSelector = page.locator('#module-station-selector-checador');
  if (await workplaceSelector.count()) {
    const options = (await workplaceSelector.locator('option').allTextContents()).map(text => text.trim()).filter(text => text && !/^Selecciona/i.test(text));
    for (const option of options) {
      expect(facts.localities.some(locality => option.includes(locality.name)), `Out-of-scope M04 locality option: ${option}`).toBe(true);
    }
  }

  const pageLength = page.locator('#m04-template-table_length select');
  await expect(pageLength).toBeVisible();
  await pageLength.selectOption('10');
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(10);
  await page.locator('#m04-template-table_next').click();
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(expectedCount - 10);
  await expect(page.locator('#m04-template-table_info')).toContainText(`11 a ${expectedCount} de ${expectedCount}`);
  await page.locator('#m04-template-table_previous').click();
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(10);
  await pageLength.selectOption('25');
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(expectedCount);

  const rows = await page.locator('#m04-template-table tbody tr').allInnerTexts();
  const observation = {
    profile: profile.key,
    rows: rows.length,
    restConfiguredRows: catalogCells.filter(cells => cells[2] !== '—' || cells[3] !== '—').length,
    overnightRows: catalogCells.filter(cells => cells[4].includes('+1 día')).length,
    inactiveRows: rows.filter(text => text.includes('Inactiva')).length,
  };
  console.log(`M04_TEMPLATE_CATALOG ${JSON.stringify(observation)}`);
  testInfo.annotations.push({ type: 'real-template-catalog-read', description: JSON.stringify(observation) });

  const search = page.locator('div.dataTables_filter input');
  await expect(search).toBeVisible();
  const firstReadableName = (await page.locator('#m04-template-table tbody th[scope="row"]').first().innerText()).trim();
  expect(firstReadableName).toBeTruthy();
  await search.fill(firstReadableName);
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(1);
  await search.fill('');
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(expectedCount);

  for (const width of [1440, 1024, 768, 375]) {
    await page.setViewportSize({ width, height: 1000 });
    const geometry = await page.evaluate(() => ({
      viewport: innerWidth,
      documentWidth: document.documentElement.scrollWidth,
      wrapperWidth: document.querySelector('#m04-template-catalog .table-responsive')?.clientWidth ?? 0,
      tableWidth: document.querySelector('#m04-template-table')?.scrollWidth ?? 0,
    }));
    expect(geometry.documentWidth).toBeLessThanOrEqual(width + 1);
    if (width === 375) {
      expect(geometry.tableWidth).toBeGreaterThan(geometry.wrapperWidth);
      const lastColumnReachable = await page.evaluate(() => {
        const wrapper = document.querySelector('#m04-template-catalog .table-responsive');
        const table = document.querySelector('#m04-template-table');
        if (!wrapper || !table) return false;
        wrapper.scrollLeft = wrapper.scrollWidth;
        const wrapperRect = wrapper.getBoundingClientRect();
        const lastColumnRect = table.tHead.rows[0].cells[5].getBoundingClientRect();
        return wrapper.scrollLeft > 0
          && lastColumnRect.left >= wrapperRect.left - 1
          && lastColumnRect.right <= wrapperRect.right + 1;
      });
      expect(lastColumnReachable).toBe(true);
      await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-375-last-columns.png`), fullPage: true });
      await page.locator('#m04-template-catalog .table-responsive').evaluate(wrapper => { wrapper.scrollLeft = 0; });
    }
    await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-${width}.png`), fullPage: true });
  }

  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(expectedCount);
  expect(writes).toEqual([]);
  expect(pageErrors).toEqual([]);
  expect(badResponses).toEqual([]);
  testInfo.annotations.push({ type: 'screenshots', description: evidenceRoot });
});
