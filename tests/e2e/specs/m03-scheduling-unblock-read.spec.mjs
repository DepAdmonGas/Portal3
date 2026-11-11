import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const weekPath = '/departamento-operativo/recursos-humanos/checador/programacion-semanal';
const weekStart = '2026-11-02';
const evidenceRoot = path.join(candidateScreenshotsRoot, `m03-scheduling-unblock-${Date.now()}`);

test('M03 programación unblock v2: lectura autenticada sin escrituras ni formularios fuera de ACL', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  ensurePrivateDirectory(evidenceRoot);

  const pageErrors = [];
  const consoleErrors = [];
  const localHttpErrors = [];
  const unexpectedWrites = [];
  const contextChanges = [];
  const blockedOrigins = [];
  const expectedDeniedConsole = [];
  await installLocalOriginGuard(page, blockedOrigins);
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => {
    if (message.type() !== 'error' || message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) return;
    if (profile.key === 'encargado' && /failed to load resource.*403/i.test(message.text())) {
      expectedDeniedConsole.push(message.text());
      return;
    }
    consoleErrors.push(message.text());
  });
  page.on('response', response => {
    const url = new URL(response.url());
    if (url.hostname === 'portal3.test' && response.status() >= 400) {
      localHttpErrors.push(`${response.status()} ${response.request().method()} ${url.pathname}`);
    }
  });
  page.on('requestfailed', request => {
    const url = new URL(request.url());
    if (url.hostname === 'portal3.test') localHttpErrors.push(`${request.method()} ${url.pathname} ${request.failure()?.errorText || ''}`);
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
  expect(facts.schedule_read).toBe(true);
  expect(facts.schedule_edit).toBe(profile.key === 'encargado');
  expect(facts.template_admin).toBe(false);
  const response = await page.goto(`${weekPath}?week_start=${weekStart}`, { waitUntil: 'domcontentloaded' });

  if (profile.key === 'encargado') {
    expect(response?.status()).toBe(200);
  } else {
    expect(response?.status()).toBe(200);
  }
  await expect(page.locator('.fixed-profile .john-title span')).toHaveText(profile.rolePattern);
  await expect(page.getByRole('heading', { name: 'Programación semanal de turnos' })).toBeVisible();
  await expect(page.locator('[aria-label="breadcrumb"]')).toBeVisible();
  await expect(page.getByText(/Interlomas/).first()).toBeVisible();
  await expect(page.locator('section[aria-labelledby="weekly-matrix-heading"] table tbody tr')).toHaveCount(47);
  await page.getByRole('button', { name: 'Opciones del módulo' }).click();
  const templateLink = page.getByRole('link', {
    name: profile.key === 'encargado' ? /Administrar turnos/i : /Consultar Horarios y turnos/i,
  });
  await expect(templateLink).toBeVisible();
  expect(new URL(await templateLink.getAttribute('href'), 'http://portal3.test').pathname)
    .toBe('/departamento-operativo/recursos-humanos/checador/horarios-turnos');
  await page.keyboard.press('Escape');
  await expect(page.locator('section[aria-labelledby="shift-template-catalog-heading"]')).toHaveCount(0);
  await expect(page.locator('form[action$="/programacion-semanal/plantillas"]')).toHaveCount(0);
  const assignmentForms = page.locator('form[action$="/programacion-semanal/asignar"]');
  await expect(assignmentForms)
    .toHaveCount(profile.key === 'encargado' && facts.schedule_write_gate ? 2 : 0);
  if (profile.key === 'encargado' && facts.schedule_write_gate) {
    await expect(assignmentForms.locator('input[name="tipo_asignacion"][value="HABITUAL"]')).toHaveCount(1);
    await expect(assignmentForms.locator('input[name="tipo_asignacion"][value="TEMPORAL"]')).toHaveCount(1);
  }

  if (profile.key === 'encargado' && facts.schedule_write_gate) {
    const selector = page.locator('[data-schedule-worker-select]');
    const assignmentForm = page.locator('form[action$="/programacion-semanal/asignar"]:has(#temporary-assignment-template)');
    const temporaryModal = page.locator('#temporary-schedule-assignment-modal');
    const templateSelector = assignmentForm.locator('#temporary-assignment-template');
    await page.locator('#temporary-schedule-tab').click();
    await expect(page.locator('#temporary-schedule-panel')).toHaveClass(/show/);
    await page.locator('#temporary-schedule-panel > .card .card-header [data-bs-target="#temporary-schedule-assignment-modal"]').click();
    await expect(temporaryModal).toBeVisible();
    const templateOptions = templateSelector.locator('option:not([value=""]):not([value="REST"])');
    await expect(templateOptions).not.toHaveCount(0);
    const templateOptionValues = await templateSelector.locator('option:not([value=""]):not([value="REST"])').evaluateAll(options => options.map(option => option.value));
    expect(new Set(templateOptionValues).size).toBe(templateOptionValues.length);
    expect(templateOptionValues.every(value => /^[1-9]\d*$/.test(value))).toBe(true);
    await expect(templateSelector.locator('option[value="REST"]')).toHaveText('Descanso explícito');
    const overnightOption = templateSelector.locator('option').filter({ hasText: '22:00–06:00 (+1 día)' }).first();
    const overnightValue = await overnightOption.getAttribute('value');
    expect(overnightValue).toBeTruthy();
    await templateSelector.selectOption(overnightValue);
    await expect(templateSelector).toHaveValue(overnightValue);
    await expect(templateSelector.locator(`option[value="${overnightValue}"]`)).toContainText('22:00–06:00 (+1 día)');
    expect(await assignmentForm.evaluate(form => form.checkValidity())).toBe(false);
    await expect(selector.locator('option[value="1004"]')).toBeDisabled();
    await page.locator('[data-schedule-target-date]').selectOption('2026-11-03');
    await expect(selector.locator('option[value="1004"]')).toBeEnabled();
    await expect(page.locator('[data-schedule-worker-status]')).toContainText('La elegibilidad se verifica para el día seleccionado.');
    await assignmentForm.getByRole('button', { name: 'Cancelar' }).click();
    await expect(temporaryModal).toBeHidden();
  }

  for (const width of [1440, 1024, 768, 375]) {
    await page.setViewportSize({ width, height: 1000 });
    const geometry = await page.evaluate(() => {
      const wrapper = document.querySelector('.tab-pane.active .table-responsive');
      return {
        viewport: window.innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        tableClientWidth: wrapper?.clientWidth ?? 0,
        tableScrollWidth: wrapper?.scrollWidth ?? 0,
      };
    });
    expect(geometry.documentWidth).toBeLessThanOrEqual(width + 1);
    if (width === 375) expect(geometry.tableScrollWidth).toBeGreaterThan(geometry.tableClientWidth);
    await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-m03-schedule-${width}.png`), fullPage: true });
  }

  expect(unexpectedWrites, `Unexpected browser writes: ${unexpectedWrites.join('\n')}`).toEqual([]);
  expect(pageErrors, `JavaScript errors: ${pageErrors.join('\n')}`).toEqual([]);
  expect(consoleErrors, `Console errors: ${consoleErrors.join('\n')}`).toEqual([]);
  expect(localHttpErrors, `Unexpected local HTTP errors: ${localHttpErrors.join('\n')}`).toEqual([]);
  if (profile.key === 'encargado') expect(expectedDeniedConsole.length).toBeLessThanOrEqual(1);
  testInfo.annotations.push({ type: 'candidate-screenshots', description: evidenceRoot });
  testInfo.annotations.push({ type: 'context-selection', description: `Authorized module-context selections: ${contextChanges.length}` });
  testInfo.annotations.push({ type: 'external-origin-guard', description: [...new Set(blockedOrigins)].join(', ') || 'external origins blocked or unused' });
});
