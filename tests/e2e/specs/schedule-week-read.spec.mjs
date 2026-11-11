import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const weekPath = '/departamento-operativo/recursos-humanos/checador/programacion-semanal';
const weekStart = '2026-11-02';
const evidenceRoot = path.join(candidateScreenshotsRoot, `m03-weekly-real-read-${Date.now()}`);

async function expectQa1006FiveShiftsAndTwoRests(row) {
  const dayValues = await row.locator('td').evaluateAll(cells => cells.slice(0, 7).map(cell => (cell.innerText || '').trim()));
  expect(dayValues.filter(value => /\b\d{2}:\d{2}(?::\d{2})?–\d{2}:\d{2}/.test(value))).toHaveLength(5);
  expect(dayValues.filter(value => value === 'Descanso')).toHaveLength(2);
}

test('Programación semanal: lectura real y alcance por perfil sin escrituras', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  ensurePrivateDirectory(evidenceRoot);

  const pageErrors = [];
  const consoleErrors = [];
  const localHttpErrors = [];
  const unexpectedWrites = [];
  const blockedOrigins = [];
  const contextChanges = [];
  const expectedDeniedConsole = [];
  let expectedScheduleDenial = false;
  let referenceRosterRowsAtNov2 = 0;
  await installLocalOriginGuard(page, blockedOrigins);
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => {
    if (message.type() !== 'error' || message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) return;
    if (expectedScheduleDenial && /failed to load resource.*403/i.test(message.text())) {
      expectedDeniedConsole.push(message.text());
      return;
    }
    consoleErrors.push(message.text());
  });
  page.on('response', response => {
    const url = new URL(response.url());
    if (profile.key === 'encargado' && response.status() === 403 && url.hostname === 'portal3.test' && url.pathname === weekPath) {
      expectedScheduleDenial = true;
      return;
    }
    if (url.hostname === 'portal3.test' && response.status() >= 400) {
      localHttpErrors.push(`${response.status()} ${response.request().method()} ${url.pathname}`);
    }
  });
  page.on('requestfailed', request => {
    if (new URL(request.url()).hostname === 'portal3.test') {
      localHttpErrors.push(`${request.method()} ${new URL(request.url()).pathname} ${request.failure()?.errorText || ''}`);
    }
  });
  page.on('request', request => {
    const url = new URL(request.url());
    if (request.method() === 'POST' && url.pathname === '/api/module-context/set') {
      contextChanges.push(request.method());
      return;
    }
    if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) unexpectedWrites.push(`${request.method()} ${url.pathname}`);
  });

  const session = await verifyPortalSession(page, profile.key, { blockedOrigins });
  expect(session.valid, `Stored session/profile verification failed: ${session.reason}`).toBe(true);
  const directoryResponse = await page.goto('/departamento-operativo/recursos-humanos/checador', { waitUntil: 'domcontentloaded' });
  expect(directoryResponse?.status()).toBe(200);
  const scheduleCardLink = page.locator(`a[href="${weekPath}"]`).filter({ hasText: 'Programación semanal' });
  await expect(scheduleCardLink).toHaveCount(1);
  await expect(scheduleCardLink).toHaveAttribute('href', weekPath);
  await scheduleCardLink.click();
  expect(new URL(page.url()).pathname).toBe(weekPath);

  const legacyRedirectPromise = page.waitForResponse(response => {
    const url = new URL(response.url());
    return url.pathname === '/departamento-operativo/recursos-humanos/checador'
      && url.searchParams.get('schedule_week') === '1';
  });
  const legacyResponse = await page.goto(`/departamento-operativo/recursos-humanos/checador?schedule_week=1&week_start=${weekStart}`, { waitUntil: 'domcontentloaded' });
  const legacyRedirect = await legacyRedirectPromise;
  expect(legacyRedirect.status()).toBe(303);
  expect(await legacyRedirect.headerValue('location')).toBe(`${weekPath}?week_start=${weekStart}`);
  expect(legacyResponse?.status()).toBe(200);
  expect(new URL(page.url()).pathname).toBe(weekPath);
  expect(new URL(page.url()).searchParams.get('schedule_week')).toBeNull();
  expect(new URL(page.url()).searchParams.get('week_start')).toBe(weekStart);
  const response = await page.goto(`${weekPath}?week_start=${weekStart}`, { waitUntil: 'domcontentloaded' });

  const moduleDropdown = page.locator('#container > header .dropdown');
  await expect(moduleDropdown).toHaveCount(1);
  const moduleDropdownButton = moduleDropdown.getByRole('button', { name: 'Opciones del módulo' });
  await expect(moduleDropdownButton).toHaveAttribute('data-bs-toggle', 'dropdown');
  const moduleDropdownMenu = moduleDropdown.locator('.dropdown-menu.dropdown-menu-end');
  await expect(moduleDropdownMenu.locator('a.dropdown-item')).toHaveCount(2);
  await moduleDropdownButton.click();
  await expect(moduleDropdownButton).toHaveAttribute('aria-expanded', 'true');
  await expect(moduleDropdownMenu).toBeVisible();
  const configurationLink = moduleDropdownMenu.getByRole('link', { name: 'Configuración' });
  const attendanceLink = moduleDropdownMenu.getByRole('link', { name: 'Asistencia del día' });
  await expect(configurationLink).toHaveAttribute('href', '/departamento-operativo/recursos-humanos/checador/configuracion');
  const attendanceUrl = new URL(await attendanceLink.getAttribute('href'), page.url());
  expect(attendanceUrl.pathname).toBe('/departamento-operativo/recursos-humanos/checador/asistencia');
  expect(attendanceUrl.searchParams.get('date')).toBe(weekStart);
  await page.keyboard.press('Escape');
  await expect(moduleDropdownButton).toHaveAttribute('aria-expanded', 'false');
  for (const width of [1440, 1024, 768, 375]) {
    await page.setViewportSize({ width, height: 1000 });
    await moduleDropdownButton.click();
    await expect(moduleDropdownButton).toHaveAttribute('aria-expanded', 'true');
    await expect(moduleDropdownMenu).toBeVisible();
    const menuGeometry = await moduleDropdownMenu.boundingBox();
    const viewport = page.viewportSize();
    expect(menuGeometry).not.toBeNull();
    expect(menuGeometry.x).toBeGreaterThanOrEqual(0);
    expect(menuGeometry.x + menuGeometry.width).toBeLessThanOrEqual(viewport.width + 1);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width + 1);
    await page.keyboard.press('Escape');
    await expect(moduleDropdownButton).toHaveAttribute('aria-expanded', 'false');
  }
  await page.setViewportSize({ width: 1440, height: 1000 });

  if (profile.key === 'encargado') {
    expect(response?.status()).toBe(200);
    await expect(page.locator('.fixed-profile .john-title span')).toHaveText(profile.rolePattern);
    await expect(page.getByRole('heading', { name: 'Programación semanal de turnos' })).toBeVisible();
    await expect(page.locator('[aria-label="breadcrumb"]')).toBeVisible();
    referenceRosterRowsAtNov2 = await page.locator('section[aria-labelledby="weekly-matrix-heading"] table tbody tr').count();
    expect(referenceRosterRowsAtNov2).toBe(46);
    const habitualTable = page.locator('section[aria-labelledby="habitual-schedule-heading"] table');
    const qa1004 = habitualTable.locator('tbody tr').filter({ hasText: '1004' });
    const qa1005 = habitualTable.locator('tbody tr').filter({ hasText: '1005' });
    const qa1006 = habitualTable.locator('tbody tr').filter({ hasText: '1006' });
    await expect(qa1004.locator('td').nth(7)).toContainText(/Parcial|Completa|No confirmada/);
    await expect(qa1005.locator('td').nth(7)).toContainText(/Parcial|Completa|No confirmada/);
    await expect(qa1006.locator('td').nth(7)).toContainText('No confirmada');
    const qa1006DayValues = await qa1006.locator('td').evaluateAll(cells => cells.slice(0, 7).map(cell => (cell.innerText || '').trim()));
    expect(qa1006DayValues.filter(value => /\b\d{2}:\d{2}(?::\d{2})?–\d{2}:\d{2}/.test(value))).toHaveLength(5);
    expect(qa1006DayValues.filter(value => value === 'Descanso')).toHaveLength(2);
    const qaCoverage = {};
    for (const workerId of [1004, 1005, 1006, 1007, 1008]) {
      const row = habitualTable.locator('tbody tr').filter({ hasText: String(workerId) });
      qaCoverage[workerId] = (await row.locator('td').nth(7).innerText()).replace(/\s+/g, ' ').trim();
    }
    console.log(`REAL_HABITUAL_COVERAGE ${JSON.stringify(qaCoverage)}`);
    testInfo.annotations.push({ type: 'real-habitual-coverage', description: JSON.stringify(qaCoverage) });
    const qa1004MenuTrigger = qa1004.getByRole('button', { name: /Acciones de/ });
    const qa1006MenuTrigger = qa1006.getByRole('button', { name: /Acciones de/ });
    await expect(qa1004MenuTrigger).toHaveCount(1);
    await expect(qa1006MenuTrigger).toHaveCount(1);
    await qa1004MenuTrigger.click();
    const qa1004Menu = qa1004.locator('.dropdown-menu');
    await expect(qa1004Menu.getByRole('button', { name: /Asignar horario habitual/ })).toHaveCount(0);
    await expect(qa1004Menu.getByRole('link', { name: /Ver historial/ })).toBeVisible();
    const historyHref = await qa1004Menu.getByRole('link', { name: /Ver historial/ }).getAttribute('href');
    const historyUrl = new URL(historyHref, page.url());
    expect(historyUrl.pathname).toBe('/departamento-operativo/recursos-humanos/checador/horarios-turnos');
    expect(historyUrl.searchParams.get('worker_id')).toBe('1004');
    expect(historyUrl.searchParams.get('date')).toBe(weekStart);
    await qa1004MenuTrigger.press('Escape');

    await qa1006MenuTrigger.click();
    const qa1006Menu = qa1006.locator('.dropdown-menu');
    await expect(qa1006Menu.getByRole('link', { name: /Ver historial/ })).toBeVisible();
    await expect(qa1006Menu.getByRole('button', { name: /Asignar horario habitual/ })).toHaveCount(0);
    await qa1006MenuTrigger.press('Escape');

    const unconfirmedRow = habitualTable.locator('tbody tr').filter({ hasText: 'No confirmada' }).first();
    if (await unconfirmedRow.count()) {
      const unconfirmedTrigger = unconfirmedRow.getByRole('button', { name: /Acciones de/ });
      await unconfirmedTrigger.click();
      const unconfirmedMenu = unconfirmedRow.locator('.dropdown-menu');
      await expect(unconfirmedMenu.getByRole('link', { name: /Ver historial/ })).toBeVisible();
      await expect(unconfirmedMenu.getByRole('button', { name: /Asignar horario habitual/ })).toHaveCount(0);
      await unconfirmedTrigger.press('Escape');
    } else {
      testInfo.annotations.push({ type: 'unconfirmed-membership-row', description: 'No se recibió una fila sin pertenencia confirmada en esta lectura autenticada; el control de esa condición se verifica en la regresión aislada.' });
    }
    for (const width of [1440, 1024, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      const habitualRows = habitualTable.locator('tbody tr');
      const rowCount = await habitualRows.count();
      const sampleIndexes = [...new Set([0, Math.floor((rowCount - 1) / 2), rowCount - 1])];
      for (const index of sampleIndexes) {
        const row = habitualRows.nth(index);
        const trigger = row.getByRole('button', { name: /Acciones de/ });
        await trigger.click();
        const menu = row.locator('.dropdown-menu.show');
        await expect(menu).toBeVisible();
        const clipping = await page.evaluate(menuElement => {
          const wrapper = menuElement.closest('.table-responsive');
          const menuRect = menuElement.getBoundingClientRect();
          const wrapperRect = wrapper?.getBoundingClientRect();
          return {
            menu: { left: menuRect.left, right: menuRect.right, top: menuRect.top, bottom: menuRect.bottom },
            wrapper: wrapperRect ? { left: wrapperRect.left, right: wrapperRect.right, top: wrapperRect.top, bottom: wrapperRect.bottom } : null,
          };
        }, await menu.elementHandle());
        expect(clipping.wrapper, 'Dropdown must remain within the responsive table viewport').not.toBeNull();
        expect(clipping.menu.left).toBeGreaterThanOrEqual(clipping.wrapper.left - 1);
        expect(clipping.menu.right).toBeLessThanOrEqual(clipping.wrapper.right + 1);
        expect(clipping.menu.top).toBeGreaterThanOrEqual(clipping.wrapper.top - 1);
        expect(clipping.menu.bottom).toBeLessThanOrEqual(clipping.wrapper.bottom + 1);
        if (index === sampleIndexes[0]) {
          await page.screenshot({ path: path.join(evidenceRoot, `encargado-habitual-dropdown-${width}.png`), fullPage: true });
        }
        await trigger.press('Escape');
      }
      const geometry = await page.evaluate(() => {
        const wrapper = document.querySelector('section[aria-labelledby="habitual-schedule-heading"] .table-responsive');
        return {
          viewport: window.innerWidth,
          documentWidth: document.documentElement.scrollWidth,
          tableClientWidth: wrapper?.clientWidth ?? 0,
          tableScrollWidth: wrapper?.scrollWidth ?? 0,
        };
      });
      expect(geometry.documentWidth).toBeLessThanOrEqual(width + 1);
      if (width === 375) expect(geometry.tableScrollWidth).toBeGreaterThan(geometry.tableClientWidth);
      await page.screenshot({ path: path.join(evidenceRoot, `encargado-schedule-week-${width}.png`), fullPage: true });
      await qa1004MenuTrigger.click();
      await expect(qa1004.locator('.dropdown-menu').getByRole('button', { name: /Asignar horario habitual/ })).toHaveCount(0);
      await qa1004MenuTrigger.press('Escape');
    }
    await qa1004MenuTrigger.click();
    await qa1004.locator('.dropdown-menu a', { hasText: 'Ver historial' }).click();
    await expect(page).toHaveURL(/\/checador\/horarios-turnos\?worker_id=1004&date=2026-11-02/);
    await page.goBack({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Programación semanal de turnos' })).toBeVisible();
  } else {
    expect(response?.status()).toBe(200);
    await expect(page.locator('.fixed-profile .john-title span')).toHaveText(profile.rolePattern);
    const visibleIdentity = await page.locator('.fixed-profile .john-title h6').innerText();
    expect(visibleIdentity.normalize('NFKC').replace(/\s+/g, ' ').trim().toLocaleLowerCase('es-MX')
      === facts.display_name_prefix.normalize('NFKC').replace(/\s+/g, ' ').trim().toLocaleLowerCase('es-MX')).toBe(true);
    await expect(page.getByRole('heading', { name: 'Programación semanal de turnos' })).toBeVisible();
    await expect(page.locator('[aria-label="breadcrumb"]')).toBeVisible();
    const habitualTable = page.locator('section[aria-labelledby="habitual-schedule-heading"] table');
    const habitualRows = habitualTable.locator('tbody tr');
    const directionRowCount = await habitualRows.count();
    await expect(habitualTable.locator('thead th').last()).toContainText('Acciones');
    await expect(habitualTable.locator('thead th').last().locator('i.ti-dots-vertical')).toBeVisible();
    await expect(habitualTable.locator('tbody button[aria-label^="Acciones de"]')).toHaveCount(directionRowCount);
    for (const index of [...new Set([0, Math.floor((directionRowCount - 1) / 2), directionRowCount - 1])]) {
      const row = habitualRows.nth(index);
      const trigger = row.getByRole('button', { name: /Acciones de/ });
      await trigger.click();
      const menu = row.locator('.dropdown-menu.show');
      await expect(menu.getByRole('link', { name: /Ver historial/ })).toBeVisible();
      await expect(menu.getByRole('button', { name: /Asignar horario habitual/ })).toHaveCount(0);
      await trigger.press('Escape');
    }

    const selector = page.locator('#module-station-selector-checador');
    if (await selector.count()) {
      const optionTexts = await selector.locator('option').allTextContents();
      for (const optionText of optionTexts.map(value => value.trim()).filter(value => value && !/^Selecciona/i.test(value))) {
        expect(facts.localities.some(locality => optionText.includes(locality.name)), `Out-of-scope locality option: ${optionText}`).toBe(true);
      }
      const interlomas = selector.locator('option').filter({ hasText: /^Interlomas(?:\s|$)/i });
      await expect(interlomas).toHaveCount(1);
      const interlomasValue = await interlomas.getAttribute('value');
      if (interlomasValue && await selector.inputValue() !== interlomasValue) {
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
          selector.selectOption(interlomasValue),
        ]);
      }
    }

    await expect(page.getByText(/Interlomas/).first()).toBeVisible();
    const coverage = page.getByText(/Cobertura parcial/).first();
    await expect(coverage).toBeVisible();
    await expect(page.getByText(/Pertenencia no confirmada/).first()).toBeVisible();
    await expect(page.getByText(/La API no informa total ni página siguiente para asignaciones/)).toBeVisible();
    await expect(page.getByText(/0 personas|0 trabajadores/i)).toHaveCount(0);

    const table = page.locator('section[aria-labelledby="weekly-matrix-heading"] .table-responsive table');
    await expect(table).toBeVisible();
    const rows = table.locator('tbody tr');
    const rowCount = await rows.count();
    expect(rowCount).toBe(46);
    referenceRosterRowsAtNov2 = rowCount;
    await expect(page.getByRole('status', { name: 'Origen y alcance de la consulta semanal' }))
      .toContainText('Padrón activo de referencia: 46 trabajadores');
    await expect(page.getByRole('status', { name: 'Origen y alcance de la consulta semanal' }))
      .toContainText('Membresía versionada en la semana: 5 trabajadores');
    await expect(page.getByRole('status', { name: 'Origen y alcance de la consulta semanal' }))
      .toContainText('Fechas sustentadas sólo por el padrón del día: 41');
    await expect(table.locator('tbody tr .badge', { hasText: 'Padrón actual' })).toHaveCount(46);
    const cellStates = await table.locator('tbody td').evaluateAll(cells => cells.map(cell => (cell.innerText || '').trim()));
    const observation = {
      week_start: weekStart,
      row_count: rowCount,
      visible_shift_cells: cellStates.filter(text => /\b\d{2}:\d{2}\b/.test(text)).length,
      unverified_membership_cells: cellStates.filter(text => text.includes('Pertenencia no confirmada')).length,
      visible_unassigned_cells: cellStates.filter(text => text.includes('Sin asignación visible')).length,
      unavailable_schedule_cells: cellStates.filter(text => text.includes('Horario no disponible')).length,
      explicit_rest_cells: cellStates.filter(text => text === 'Descanso').length,
    };
    console.log(`REAL_WEEKLY_READ ${JSON.stringify(observation)}`);
    testInfo.annotations.push({ type: 'real-weekly-read-observation', description: JSON.stringify(observation) });

    for (const width of [1440, 1024, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      const firstHabitualRow = habitualRows.first();
      const actionTrigger = firstHabitualRow.getByRole('button', { name: /Acciones de/ });
      await actionTrigger.click();
      const actionMenu = firstHabitualRow.locator('.dropdown-menu.show');
      await expect(actionMenu.getByRole('link', { name: /Ver historial/ })).toBeVisible();
      await expect(actionMenu.getByRole('button', { name: /Asignar horario habitual/ })).toHaveCount(0);
      await actionTrigger.press('Escape');
      const metrics = await page.evaluate(() => {
        const wrapper = document.querySelector('section[aria-labelledby="weekly-matrix-heading"] .table-responsive');
        return {
          viewport: window.innerWidth,
          documentWidth: document.documentElement.scrollWidth,
          tableClientWidth: wrapper?.clientWidth ?? 0,
          tableScrollWidth: wrapper?.scrollWidth ?? 0,
        };
      });
      expect(metrics.documentWidth).toBeLessThanOrEqual(width + 1);
      if (width === 375) expect(metrics.tableScrollWidth).toBeGreaterThan(metrics.tableClientWidth);
      await page.screenshot({ path: path.join(evidenceRoot, `direccion-schedule-week-${width}.png`), fullPage: true });
    }
  }

  // The current week and the following week are read-only projections of the
  // same recurring habitual rows. An in-range temporary row overrides only
  // its dates; the later week must resolve back to the habitual shift/rest.
  await page.setViewportSize({ width: 1440, height: 1000 });
  await expect(page.locator('section[aria-labelledby="habitual-schedule-heading"]')).toBeVisible();
  await expect(page.locator('section[aria-labelledby="weekly-matrix-heading"]')).toBeVisible();
  await expect(page.locator('section[aria-labelledby="habitual-schedule-heading"]')).toContainText('no se duplican cada semana');
  for (const workerId of [1004, 1005, 1006, 1007, 1008]) {
    await expect(page.locator('section[aria-labelledby="habitual-schedule-heading"] tbody tr').filter({ hasText: String(workerId) })).toHaveCount(1);
    await expect(page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: String(workerId) })).toHaveCount(1);
  }
  const habitualRowThisWeek = page.locator('section[aria-labelledby="habitual-schedule-heading"] tbody tr').filter({ hasText: '1005' });
  const effectiveRowThisWeek = page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: '1005' });
  await expect(habitualRowThisWeek).toHaveCount(1);
  await expect(effectiveRowThisWeek).toHaveCount(1);
  await expect(habitualRowThisWeek.locator('td').nth(2)).toContainText('Turno Local Matutino');
  await expect(habitualRowThisWeek.locator('td').nth(3)).toContainText('Descanso');
  await expect(effectiveRowThisWeek.locator('td').nth(2)).toContainText('Temporal');
  const qa1006EffectiveRow = page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: '1006' });
  await expect(qa1006EffectiveRow.locator('td').first()).toContainText('Turno Local Matutino');
  await expect(qa1006EffectiveRow).not.toContainText('Sin asignación visible');
  await expectQa1006FiveShiftsAndTwoRests(qa1006EffectiveRow);

  const nextWeekResponse = await page.goto(`${weekPath}?week_start=2026-11-09`, { waitUntil: 'domcontentloaded' });
  expect(nextWeekResponse?.status()).toBe(200);
  const nextWeekRows = page.locator('section[aria-labelledby="weekly-matrix-heading"] table tbody tr');
  await expect(nextWeekRows).toHaveCount(referenceRosterRowsAtNov2);
  await expect(page.getByRole('status', { name: 'Origen y alcance de la consulta semanal' }))
    .toContainText(`Padrón activo de referencia: ${referenceRosterRowsAtNov2} trabajadores`);
  await expect(page.getByRole('status', { name: 'Origen y alcance de la consulta semanal' }))
    .toContainText('Fechas sustentadas sólo por el padrón del día: 0');
  await expect(page.getByText('Pertenencia no confirmada').first()).toBeVisible();
  const habitualRowNextWeek = page.locator('section[aria-labelledby="habitual-schedule-heading"] tbody tr').filter({ hasText: '1005' });
  const effectiveRowNextWeek = page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: '1005' });
  await expect(habitualRowNextWeek).toHaveCount(1);
  await expect(effectiveRowNextWeek).toHaveCount(1);
  for (const workerId of [1004, 1005, 1006, 1007, 1008]) {
    await expect(page.locator('section[aria-labelledby="habitual-schedule-heading"] tbody tr').filter({ hasText: String(workerId) })).toHaveCount(1);
    await expect(page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: String(workerId) })).toHaveCount(1);
  }
  await expect(habitualRowNextWeek.locator('td').nth(2)).toContainText('Turno Local Matutino');
  await expect(effectiveRowNextWeek.locator('td').nth(2)).toContainText('Turno Local Matutino');
  await expect(effectiveRowNextWeek.locator('td').nth(2).locator('.badge.text-bg-warning')).toHaveCount(0);
  await expect(effectiveRowNextWeek.locator('td').nth(3)).toContainText('Descanso');
  const qa1006NextWeek = page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: '1006' });
  await expect(qa1006NextWeek.locator('td').first()).toContainText('Turno Local Matutino');
  await expectQa1006FiveShiftsAndTwoRests(qa1006NextWeek);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(page.locator('section[aria-labelledby="weekly-matrix-heading"] table tbody tr'))
    .toHaveCount(referenceRosterRowsAtNov2);
  await expect(page.getByRole('status', { name: 'Origen y alcance de la consulta semanal' }))
    .toContainText('Fechas sustentadas sólo por el padrón del día: 0');
  await expect(page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: '1005' }).locator('td').nth(2)).toContainText('Turno Local Matutino');

  const weekOfNov16 = await page.goto(`${weekPath}?week_start=2026-11-16`, { waitUntil: 'domcontentloaded' });
  expect(weekOfNov16?.status()).toBe(200);
  await expect(page.locator('section[aria-labelledby="weekly-matrix-heading"] table tbody tr'))
    .toHaveCount(referenceRosterRowsAtNov2);
  for (const workerId of [1004, 1005, 1006, 1007, 1008]) {
    await expect(page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: String(workerId) })).toHaveCount(1);
  }
  const qa1006Nov16 = page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: '1006' });
  await expect(qa1006Nov16.locator('td').first()).toContainText('Turno Local Matutino');
  await expectQa1006FiveShiftsAndTwoRests(qa1006Nov16);

  expect(unexpectedWrites, `Unexpected browser writes: ${unexpectedWrites.join('\n')}`).toEqual([]);
  expect(pageErrors, `JavaScript errors: ${pageErrors.join('\n')}`).toEqual([]);
  if (profile.key === 'encargado') {
    expect(expectedScheduleDenial).toBe(false);
    expect(expectedDeniedConsole).toHaveLength(0);
  }
  expect(consoleErrors, `Console errors: ${consoleErrors.join('\n')}`).toEqual([]);
  expect(localHttpErrors, `Local HTTP errors: ${localHttpErrors.join('\n')}`).toEqual([]);
  testInfo.annotations.push({ type: 'context-selection', description: `Authorized station selection posts: ${contextChanges.length}` });
  testInfo.annotations.push({ type: 'screenshots', description: `Candidate evidence stored in ${evidenceRoot}` });
  testInfo.annotations.push({ type: 'external-network-policy', description: `Blocked origins: ${[...new Set(blockedOrigins)].join(', ') || 'none'}` });
});
