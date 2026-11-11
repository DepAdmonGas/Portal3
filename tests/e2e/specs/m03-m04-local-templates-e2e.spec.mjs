import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles, baseURL } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const weekPath = '/departamento-operativo/recursos-humanos/checador/programacion-semanal';
const weekStart = '2026-11-02';
const nextWeekStart = '2026-11-09';
const evidenceRoot = path.join(candidateScreenshotsRoot, `m03-m04-local-templates-${Date.now()}`);

test('M03/M04 E2E: plantilla local, asignación habitual, descanso explícito, excepción temporal, fallback automático y aislamiento', async ({ page, request }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  ensurePrivateDirectory(evidenceRoot);

  const pageErrors = [];
  const consoleErrors = [];
  const localHttpErrors = [];
  const blockedOrigins = [];
  await installLocalOriginGuard(page, blockedOrigins);

  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => {
    if (message.type() !== 'error' || message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) return;
    if (profile.key === 'encargado' && /403/i.test(message.text())) return;
    consoleErrors.push(message.text());
  });
  page.on('response', response => {
    const url = new URL(response.url());
    if (url.hostname === 'portal3.test' && response.status() >= 400 && !url.pathname.includes('/plantillas-locales-denied')) {
      localHttpErrors.push(`${response.status()} ${response.request().method()} ${url.pathname}`);
    }
  });

  const session = await verifyPortalSession(page, profile.key, { blockedOrigins });
  expect(session.valid, `Stored session verification failed: ${session.reason}`).toBe(true);

  if (profile.key === 'encargado') {
    expect(facts.station_template_admin).toBe(true);
    expect(facts.template_admin).toBe(false);
    expect(facts.schedule_edit).toBe(true);

    // 1. Navigate to schedule week
    const response = await page.goto(`${baseURL}${weekPath}?week_start=${weekStart}`, { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);

    // 2. Local template form must be present, global template form must NOT be present
    await expect(page.locator('form[action$="/programacion-semanal/plantillas"]')).toHaveCount(0);
    const localTemplateForm = page.locator('form[action$="/programacion-semanal/plantillas-locales"]');
    await expect(localTemplateForm).toHaveCount(1);
    await expect(page.locator('#shift-local-template-create-heading')).toContainText('Registrar plantilla local de estación');

    // 3. Create local template if not already present
    const templateCatalog = page.locator('section[aria-labelledby="shift-template-catalog-heading"]');
    const existingLocalTemplate = templateCatalog.locator('tbody tr').filter({ hasText: 'IL1_LOC_MAT_0715' });
    const localTemplateAlreadyExists = (await existingLocalTemplate.count()) > 0;

    if (!localTemplateAlreadyExists) {
      await page.locator('#local-template-key').fill('IL1_LOC_MAT_0715');
      await page.locator('#local-template-name').fill('Turno Local Matutino 07:00–15:00');
      await page.locator('#local-template-type').selectOption('REGULAR');
      await page.locator('#local-segment-type-0').selectOption('WORK');
      await page.locator('#local-segment-start-0').fill('07:00');
      await page.locator('#local-segment-start-offset-0').selectOption('0');
      await page.locator('#local-segment-end-0').fill('15:00');
      await page.locator('#local-segment-end-offset-0').selectOption('0');
      await localTemplateForm.locator('input[name="confirm_action"]').check();

      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        localTemplateForm.locator('button[type="submit"]').click(),
      ]);

      const alert = page.locator('.alert-success');
      await expect(alert).toBeVisible();
      await expect(alert).toContainText('confirmados mediante lectura posterior de la API');
    }

    // Verify local template appears in catalog table with badge "Estación"
    const localRow = page.locator('section[aria-labelledby="shift-template-catalog-heading"] tbody tr').filter({ hasText: 'IL1_LOC_MAT_0715' });
    await expect(localRow).toHaveCount(1);
    await expect(localRow.locator('.badge.text-bg-info')).toHaveText('Estación');
    await expect(localRow).toContainText('Turno Local Matutino 07:00–15:00');

    // 4. Assign HABITUAL shift to worker 1005 for Wednesday 2026-11-04
    const assignmentForm = page.locator('form[action$="/programacion-semanal/asignar"]');
    await expect(assignmentForm).toHaveCount(1);

    // Find the option value for local template
    const templateSelect = page.locator('#assignment-template');
    const localOption = templateSelect.locator('option').filter({ hasText: 'Turno Local Matutino 07:00–15:00' }).first();
    await expect(localOption).toHaveCount(1);
    const localTemplateId = await localOption.getAttribute('value');
    expect(localTemplateId).toBeTruthy();

    // Select HABITUAL, worker 1005, date 2026-11-04 (Wednesday)
    await page.locator('#assignment-kind').selectOption('HABITUAL');
    await page.locator('[data-schedule-target-date]').selectOption('2026-11-04');
    await page.locator('[data-schedule-worker-select]').selectOption('1005');
    await templateSelect.selectOption(localTemplateId);
    await assignmentForm.locator('input[name="confirm_action"]').check();

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      assignmentForm.locator('button[type="submit"]').click(),
    ]);

    await expect(page.locator('.alert-success')).toContainText('confirmada por lectura posterior de la API');

    // 5. Assign HABITUAL DAY_OFF (REST) to worker 1005 for Thursday 2026-11-05
    await page.locator('#assignment-kind').selectOption('HABITUAL');
    await page.locator('[data-schedule-target-date]').selectOption('2026-11-05');
    await page.locator('[data-schedule-worker-select]').selectOption('1005');
    await templateSelect.selectOption('REST');
    await assignmentForm.locator('input[name="confirm_action"]').check();

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      assignmentForm.locator('button[type="submit"]').click(),
    ]);

    await expect(page.locator('.alert-success')).toContainText('confirmada por lectura posterior de la API');

    // Verify in weekly matrix for worker 1005: Wednesday has local template, Thursday has Descanso
    const worker1005Row = page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: '1005' });
    await expect(worker1005Row).toHaveCount(1);
    // Wednesday cell (col index 3: worker, mon, tue, wed)
    await expect(worker1005Row.locator('td').nth(2)).toContainText('Turno Local Matutino 07:00–15:00');
    // Thursday cell (col index 4: wed, thu)
    await expect(worker1005Row.locator('td').nth(3)).toContainText('Descanso');

    // 6. Record TEMPORAL override on Wednesday 2026-11-04 with another shift
    const overnightOption = templateSelect.locator('option').filter({ hasText: '20:00–06:00 (+1 día)' }).first();
    const overnightId = await overnightOption.getAttribute('value');

    await page.locator('#assignment-kind').selectOption('TEMPORAL');
    await page.locator('[data-schedule-target-date]').selectOption('2026-11-04');
    await page.locator('[data-schedule-worker-select]').selectOption('1005');
    await templateSelect.selectOption(overnightId);
    await assignmentForm.locator('input[name="confirm_action"]').check();

    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      assignmentForm.locator('button[type="submit"]').click(),
    ]);

    await expect(page.locator('.alert-success')).toContainText('confirmada por lectura posterior de la API');

    // Verify that Wednesday cell now displays the TEMPORARY shift and the "Temporal" badge
    const worker1005UpdatedRow = page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: '1005' });
    const wedCell = worker1005UpdatedRow.locator('td').nth(2);
    await expect(wedCell).toContainText('TURNO 3');
    await expect(wedCell.locator('.badge.text-bg-warning')).toHaveText('Temporal');

    // 7. Verify automatic fallback to HABITUAL schedule on next week (2026-11-09)
    await page.goto(`${baseURL}${weekPath}?week_start=${nextWeekStart}`, { waitUntil: 'domcontentloaded' });
    const nextWeekWorkerRow = page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: '1005' });
    await expect(nextWeekWorkerRow).toHaveCount(1);
    // On next week's Wednesday (2026-11-11), it automatically resolves to HABITUAL local template!
    const nextWedCell = nextWeekWorkerRow.locator('td').nth(2);
    await expect(nextWedCell).toContainText('Turno Local Matutino 07:00–15:00');
    await expect(nextWedCell.locator('.badge.text-bg-warning')).toHaveCount(0); // NOT temporal
    // On next week's Thursday (2026-11-12), it automatically resolves to HABITUAL Descanso!
    const nextThuCell = nextWeekWorkerRow.locator('td').nth(3);
    await expect(nextThuCell).toContainText('Descanso');

    // 8. Test F5 / Persistence
    await page.reload({ waitUntil: 'domcontentloaded' });
    const reloadedWorkerRow = page.locator('section[aria-labelledby="weekly-matrix-heading"] tbody tr').filter({ hasText: '1005' });
    await expect(reloadedWorkerRow.locator('td').nth(2)).toContainText('Turno Local Matutino 07:00–15:00');
    await expect(reloadedWorkerRow.locator('td').nth(3)).toContainText('Descanso');

    // 9. Station isolation check: try to submit for a foreign station (e.g. workplace 2)
    const csrfToken = await page.locator('input[name="_csrf_token"]').first().getAttribute('value');
    const foreignStationResult = await page.evaluate(async ({ csrf, start }) => {
      const body = new URLSearchParams({
        _csrf_token: csrf,
        workplace_id: '2', // Palo Solo (foreign to Encargado)
        week_start: start,
        template_key: 'FOREIGN_STATION_TEST',
        template_name: 'Foreign Station Test',
        template_type: 'REGULAR',
        confirm_action: '1',
        'segments[0][tipo_segmento]': 'WORK',
        'segments[0][hora_inicio_local]': '08:00',
        'segments[0][dia_offset_inicio]': '0',
        'segments[0][hora_fin_local]': '16:00',
        'segments[0][dia_offset_fin]': '0',
      });
      const res = await fetch('/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas-locales', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });
      const text = await res.text();
      return { ok: res.ok, status: res.status, containsError: text.includes('Revisa el centro') };
    }, { csrf: csrfToken, start: weekStart });

    expect(foreignStationResult.containsError).toBe(true);
    await expect(page.locator('section[aria-labelledby="shift-template-catalog-heading"]').getByText('FOREIGN_STATION_TEST')).toHaveCount(0);

    // Responsive screenshots
    for (const width of [1440, 1024, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.screenshot({ path: path.join(evidenceRoot, `encargado-m03-m04-${width}.png`), fullPage: true });
    }
  } else if (profile.key === 'direccion-operaciones') {
    expect(facts.station_template_admin).toBe(false);
    expect(facts.template_admin).toBe(false);
    expect(facts.schedule_edit).toBe(false);

    const response = await page.goto(`${baseURL}${weekPath}?week_start=${weekStart}`, { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);

    // Dirección has read-only supervision access:
    // Matrix is visible
    await expect(page.locator('section[aria-labelledby="weekly-matrix-heading"]')).toBeVisible();
    // Template catalog is visible
    await expect(page.locator('section[aria-labelledby="shift-template-catalog-heading"]')).toBeVisible();
    // NO write forms are rendered
    await expect(page.locator('form[action$="/programacion-semanal/plantillas-locales"]')).toHaveCount(0);
    await expect(page.locator('form[action$="/programacion-semanal/plantillas"]')).toHaveCount(0);
    await expect(page.locator('form[action$="/programacion-semanal/asignar"]')).toHaveCount(0);

    // Responsive screenshots
    for (const width of [1440, 1024, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.screenshot({ path: path.join(evidenceRoot, `direccion-m03-m04-${width}.png`), fullPage: true });
    }
  }

  expect(pageErrors, `JavaScript errors: ${pageErrors.join('\n')}`).toEqual([]);
  expect(consoleErrors, `Console errors: ${consoleErrors.join('\n')}`).toEqual([]);
  testInfo.annotations.push({ type: 'candidate-screenshots', description: evidenceRoot });
});
