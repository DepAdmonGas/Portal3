import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { readProfileFacts, normalizedIdentity } from '../scripts/profile-facts.mjs';

const configurationPath = '/departamento-operativo/recursos-humanos/checador/configuracion';
const evidenceRoot = path.join(candidateScreenshotsRoot, `m11-attendance-policy-read-${Date.now()}`);

test('Configuración M11: lectura local sin escrituras y permisos separados', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  ensurePrivateDirectory(evidenceRoot);
  expect(facts.attendance_policy_read).toBe(true);
  expect(facts.attendance_policy_edit).toBe(true);
  expect(facts.reader_profile_read).toBe(true);
  expect(facts.m11_local_write_gate).toBe(true);
  if (profile.expectedPositionId === 6) {
    expect(facts.reader_profile_edit).toBe(true);
    expect(facts.position_catalog_read).toBe(false);
    expect(facts.position_catalog_edit).toBe(false);
  } else {
    expect(facts.reader_profile_edit).toBe(false);
    expect(facts.position_catalog_read).toBe(true);
    expect(facts.position_catalog_edit).toBe(true);
  }

  const pageErrors = [];
  const consoleErrors = [];
  const unexpectedWrites = [];
  const blockedOrigins = [];
  await page.route('**/*', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (['http:', 'https:'].includes(url.protocol) && url.hostname !== 'portal3.test') {
      blockedOrigins.push(url.origin);
      await route.abort('blockedbyclient');
      return;
    }
    if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) {
      unexpectedWrites.push(`${request.method()} ${url.pathname}`);
      await route.abort('blockedbyclient');
      return;
    }
    await route.continue();
  });
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error' && !message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) consoleErrors.push(message.text());
  });
  const response = await page.goto(configurationPath, { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(200);
  await expect(page.locator('.fixed-profile .john-title span')).toHaveText(profile.rolePattern);
  const visibleIdentity = await page.locator('.fixed-profile .john-title h6').innerText();
  expect(normalizedIdentity(visibleIdentity)).toBe(normalizedIdentity(facts.display_name_prefix));
  await expect(page.getByRole('heading', { name: 'Configuración de Checador', exact: true })).toHaveCount(1);
  await expect(page.locator('[aria-label="breadcrumb"]')).toBeVisible();
  const configuration = page.locator('#container');
  await expect(configuration.getByText('Consulta de turnos, reglas y catálogos del centro autorizado.', { exact: true })).toHaveCount(0);
  await expect(configuration.getByText('Centro autorizado:', { exact: true })).toHaveCount(0);
  await expect(configuration.getByText('Localidad canónica:', { exact: true })).toHaveCount(0);
  await expect(configuration.getByRole('link', { name: 'Asistencia del día', exact: true })).toHaveCount(0);
  await expect(configuration.getByRole('link', { name: 'Programación semanal', exact: true })).toHaveCount(0);
  await expect(configuration.locator('#configuration-navigation')).toHaveCount(0);
  await expect(configuration.locator('#schedule-configuration')).toHaveCount(0);
  await expect(configuration.locator('#attendance-rules')).toHaveCount(0);
  await expect(configuration.getByText('Horario efectivo e historial', { exact: true })).toHaveCount(0);
  await expect(configuration.getByText('Registros de horarios anteriores', { exact: true })).toHaveCount(0);
  await expect(configuration.getByText('Tolerancia registrada', { exact: true })).toHaveCount(0);
  await expect(configuration.getByText('Plazo de aclaración', { exact: true })).toHaveCount(0);
  const scheduleLink = configuration.getByRole('link', { name: 'Horarios y turnos', exact: true });
  if (facts.schedule_read) {
    await expect(scheduleLink).toHaveCount(1);
    await expect(scheduleLink).toHaveAttribute('href', '/departamento-operativo/recursos-humanos/checador/horarios-turnos');
  } else {
    await expect(scheduleLink).toHaveCount(0);
  }
  const policyCard = page.locator('#attendance-policy-v2');
  const policyForm = policyCard.locator('form[action$="/configuracion/politicas-asistencia"]');
  await expect(policyCard).toBeVisible();
  await expect(policyCard.getByRole('heading', { name: 'Configuración de asistencia', exact: true })).toBeVisible();
  await expect(policyCard).toContainText('Retardos e incidencias');
  const initialCurrent = JSON.parse(await policyCard.getAttribute('data-current-policy'));
  const initialNext = JSON.parse(await policyCard.getAttribute('data-next-policy'));
  const initialHistory = JSON.parse(await policyCard.getAttribute('data-history'));
  expect(await policyCard.getAttribute('data-history-complete')).toBe('true');
  if (facts.localities.some(({ id }) => id === 1)) {
    expect(initialHistory.map(item => item.policy_version)).toEqual(expect.arrayContaining([1, 2, 3]));
    for (const policy of initialHistory.filter(item => [1, 2].includes(item.policy_version))) {
      expect(policy.retardo_tolerance_minutes).toBe(30);
      expect(policy.incidence_deadline_days).toBe(6);
    }
  }
  if (initialCurrent && initialCurrent.id) await expect(policyCard.getByText('Configuración vigente', { exact: true })).toBeVisible();
  else await expect(policyCard.getByText('Configuración vigente', { exact: true })).toHaveCount(0);
  if (initialNext && initialNext.id && initialNext.effective_from_local_date > (await policyCard.getAttribute('data-current-date'))) {
    await expect(policyCard.getByText('Próxima configuración', { exact: true })).toBeVisible();
    await expect(policyCard).toContainText(initialNext.effective_from_local_date);
  } else await expect(policyCard.getByText('Próxima configuración', { exact: true })).toHaveCount(0);
  await expect(policyCard.getByRole('button', { name: 'Editar configuración', exact: false })).toBeVisible();
  await expect(policyCard.getByRole('button', { name: 'Ver historial', exact: false })).toBeVisible();
  await expect(policyCard).not.toContainText(/legacy|versión V2|API V2|versionado/i);
  const historyModal = policyCard.locator('#attendance-policy-history-modal');
  await policyCard.getByRole('button', { name: 'Ver historial', exact: false }).click();
  await expect(historyModal).toBeVisible();
  const historyRows = historyModal.locator('#attendance-policy-history-table tbody tr');
  await expect(historyRows).toHaveCount(initialHistory.filter((item) => item && item.id).length);
  for (const policy of initialHistory.filter((item) => item && item.id)) {
    const row = historyRows.filter({ hasText: policy.effective_from_local_date });
    await expect(row).toContainText(`${policy.retardo_tolerance_minutes} min`);
    await expect(row).toContainText(`${policy.incidence_deadline_days} días`);
  }
  await historyModal.getByRole('button', { name: 'Cerrar', exact: true }).last().click();
  await expect(historyModal).toBeHidden();
  await expect(policyForm).toHaveAttribute('x-data', 'attendancePolicyEditor()');
  await expect(policyForm.locator('input[name="retardo_tolerance_minutes"]')).toHaveAttribute('max', '120');
  await expect(policyForm.locator('input[name="incidence_deadline_days"]')).toHaveAttribute('max', '30');
  const frontendVersions = await page.evaluate(() => ({ alpine: window.Alpine?.version || null, axios: window.axios?.VERSION || null }));
  expect(frontendVersions.alpine).toBeTruthy();
  expect(frontendVersions.axios).toBeTruthy();
  const positionCard = page.locator('#people-positions');
  await expect(positionCard).toHaveCount(profile.expectedPositionId === 13 ? 1 : 0);
  await expect(page.locator('#reader-access')).toBeVisible();

  const stationSelector = page.locator('#module-station-selector-checador');
  if (await stationSelector.count()) await expect(stationSelector).toBeVisible();
  const hasSelectedLocality = !(await stationSelector.count()) || (await stationSelector.inputValue()) !== '';
  const expectedEditorForm = facts.attendance_policy_edit && facts.attendance_policy_write_gate && hasSelectedLocality;
  await expect(policyForm).toHaveCount(expectedEditorForm ? 1 : 0);
  const positionModal = page.locator('#m11-position-modal');
  if (profile.expectedPositionId === 13 && facts.position_catalog_edit && facts.m11_local_write_gate) {
    await expect(positionCard.getByRole('button', { name: 'Nuevo', exact: false })).toBeVisible();
    await expect(positionModal).toHaveCount(1);
    const positionTable = positionCard.locator('#m11-position-table');
    const positionRows = positionTable.locator('tbody tr');
    await expect(positionTable).toBeVisible();
    expect(await positionRows.count()).toBeGreaterThan(0);

    const search = positionCard.locator('#m11-position-table_filter input');
    const firstPositionRow = positionRows.first();
    const firstPositionName = (await firstPositionRow.locator('td').first().innerText()).trim();
    expect(firstPositionName).not.toBe('');
    await search.fill(firstPositionName);
    await expect(positionRows).toHaveCount(1);
    await expect(positionRows.first()).toContainText(firstPositionName);
    await search.fill('');

    await positionCard.getByRole('button', { name: 'Nuevo', exact: false }).click();
    await expect(positionModal).toBeVisible();
    await expect(positionModal).toContainText('Nuevo puesto');
    await expect(positionModal.locator('#m11-position-name')).toHaveValue('');
    for (const width of [1440, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      const modalBox = await positionModal.locator('.modal-dialog').boundingBox();
      expect(modalBox?.width).toBeLessThanOrEqual(width);
      await page.screenshot({ path: path.join(evidenceRoot, `direccion-position-create-modal-${width}.png`), fullPage: true });
    }
    await positionModal.getByRole('button', { name: 'Cancelar', exact: false }).click();
    await expect(positionModal).toBeHidden();

    await page.setViewportSize({ width: 1440, height: 1000 });
    const editRow = positionRows.first();
    const editName = (await editRow.locator('td').first().innerText()).trim();
    await editRow.locator('[aria-label="Opciones del puesto"]').click();
    await expect(editRow.locator('.dropdown-menu')).toBeVisible();
    await editRow.locator('.dropdown-menu .m11-position-edit').click();
    await expect(positionModal).toBeVisible();
    await expect(positionModal).toContainText('Editar puesto');
    await expect(positionModal.locator('#m11-position-name')).toHaveValue(editName);
    await positionModal.getByRole('button', { name: 'Cancelar', exact: false }).click();
    await expect(positionModal).toBeHidden();
  } else {
    await expect(positionCard).toHaveCount(0);
    await expect(positionModal).toHaveCount(0);
  }
  const readerCard = page.locator('#reader-access');
  const readerEditForms = readerCard.locator('form[action$="/perfiles-lector/actualizar"]');
  const secretInputs = readerCard.locator('input[type="password"]');
  if (profile.expectedPositionId === 13) {
    await expect(readerEditForms).toHaveCount(0);
    await expect(secretInputs).toHaveCount(0);
  } else {
    for (const input of await secretInputs.all()) await expect(input).toHaveValue('');
    await expect(secretInputs).toHaveCount((await readerEditForms.count()) * 2);
  }
  if (hasSelectedLocality && !facts.attendance_policy_read) {
    await expect(policyCard.getByText(/permiso específico de lectura/i)).toBeVisible();
  }

  for (const width of [1440, 1024, 768, 375]) {
    await page.setViewportSize({ width, height: 1000 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width + 1);
    await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-configuration-${width}.png`), fullPage: true });
  }

  expect(unexpectedWrites, `Unexpected browser writes: ${unexpectedWrites.join('\n')}`).toEqual([]);
  expect(pageErrors, `JavaScript errors: ${pageErrors.join('\n')}`).toEqual([]);
  expect(consoleErrors, `Console errors: ${consoleErrors.join('\n')}`).toEqual([]);
  testInfo.annotations.push({ type: 'candidate-screenshots', description: evidenceRoot });
  testInfo.annotations.push({ type: 'external-network-policy', description: [...new Set(blockedOrigins)].join(', ') || 'external origins blocked or unused' });
});
