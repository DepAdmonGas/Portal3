import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, baseURL, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const route = '/departamento-operativo/recursos-humanos/checador/horarios-turnos';
const editUrl = '/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas-locales/versionar';
const evidenceRoot = path.join(candidateScreenshotsRoot, 'm04-local-turn-versioned-edit-' + Date.now());
const qaTemplateKey = 'LOC_1_T_4_14002200';
const qaName = 'Turno 4 · Edición QA 20261104';

test('M04: edición local versionada en Encargado; Dirección permanece en lectura', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), 'BLOCKED: falta sesión QA para ' + testInfo.project.name);
  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  ensurePrivateDirectory(evidenceRoot);
  const blockedOrigins = [];
  const pageErrors = [];
  const postedPaths = [];
  await installLocalOriginGuard(page, blockedOrigins);
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('request', request => {
    const url = new URL(request.url());
    if (url.hostname === 'portal3.test' && request.method() === 'POST') postedPaths.push(url.pathname);
  });
  const session = await verifyPortalSession(page, profile.key, { blockedOrigins });
  expect(session.valid, 'Stored session rejected: ' + session.reason).toBe(true);
  await page.setViewportSize({ width: 1440, height: 900 });
  const response = await page.goto(baseURL + route, { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(200);
  const card = page.locator('#m04-template-catalog');
  await expect(card).toBeVisible();
  await expect(card).toHaveAttribute('data-workplace-id', '1');
  const localRow = page.locator('#m04-template-table tbody tr[data-template-key="' + qaTemplateKey + '"]');
  const globalKey = await page.locator('#m04-template-table tbody tr').evaluateAll(rows =>
    rows.find(row => !row.querySelector('[data-m04-edit-template]'))?.dataset.templateKey || '');
  expect(globalKey).toBeTruthy();
  const globalRow = page.locator('#m04-template-table tbody tr[data-template-key="' + globalKey + '"]');
  await expect(localRow).toHaveCount(1);

  if (profile.key === 'encargado') {
    expect(facts.station_template_admin).toBe(true);
    expect(facts.template_admin).toBe(false);
    await expect(localRow.locator('[data-m04-edit-template]')).toHaveCount(1);
    await expect(globalRow.locator('[data-m04-edit-template]')).toHaveCount(0);
    const editAction = localRow.locator('[data-m04-edit-template]');
    const editPayload = JSON.parse(await editAction.getAttribute('data-m04-edit-template'));
    if (editPayload.version === 2 && editPayload.name === qaName) {
      await expect(localRow.locator('th[scope="row"]')).toHaveText(qaName);
      // Simulate a browser retaining version 1 after the first request
      // persisted version 2 but its response was lost. Exercise reconciliation
      // through the actual modal/Axios path without another canonical API write.
      await editAction.evaluate((element, stalePayload) => {
        element.setAttribute('data-m04-edit-template', JSON.stringify(stalePayload));
      }, { ...editPayload, version: 1 });
      await localRow.locator('.dropdown.dropstart > a[data-bs-toggle="dropdown"]').click();
      await editAction.click();
      const reconcileModal = page.locator('#m04-edit-template-modal');
      await expect(reconcileModal).toBeVisible();
      await expect(reconcileModal.locator('#m04-edit-turn-name')).toHaveValue(qaName);
      const [reconciliationResponse] = await Promise.all([
        page.waitForResponse(item => new URL(item.url()).pathname === editUrl && item.request().method() === 'POST'),
        reconcileModal.getByRole('button', { name: 'Guardar cambios' }).click(),
      ]);
      expect(reconciliationResponse.status()).toBe(200);
      const reconciled = await reconciliationResponse.json();
      expect(reconciled.ok).toBe(true);
      expect(reconciled.template.version).toBe(2);
      expect(reconciled.template.nombre).toBe(qaName);
      await expect(reconcileModal).not.toBeVisible();
      await expect(localRow.locator('th[scope="row"]')).toHaveText(qaName);
      await expect(localRow.locator('td').nth(0)).toHaveText('14:00');
      await expect(localRow.locator('td').nth(3)).toHaveText('22:00');

      const csrfToken = await card.getAttribute('data-csrf-token');
      const reconciliationDenials = await page.evaluate(async ({ csrf, globalKey }) => {
        const send = async payload => {
          try {
            const response = await window.axios.post('/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas-locales/versionar', payload, {
              headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            return response.status;
          } catch (error) { return error?.response?.status || 0; }
        };
        const segment = [{ ordinal: 1, tipo_segmento: 'WORK', hora_inicio_local: '08:00:00', hora_fin_local: '16:00:00', dia_offset_inicio: 0, dia_offset_fin: 0 }];
        return {
          foreign: await send({ template_key: 'LOC_2_FOREIGN', expected_version: 1, template_name: 'No autorizado', workplace_id: 2, segments: segment }),
          global: await send({ template_key: globalKey, expected_version: 1, template_name: 'Global no editable', workplace_id: 1, segments: segment }),
        };
      }, { csrf: csrfToken, globalKey });
      expect(reconciliationDenials).toEqual({ foreign: 403, global: 403 });
      for (const width of [1440, 768, 375]) {
        await page.setViewportSize({ width, height: 900 });
        await localRow.locator('.dropdown.dropstart > a[data-bs-toggle="dropdown"]').click();
        await localRow.locator('[data-m04-edit-template]').click();
        const repeatModal = page.locator('#m04-edit-template-modal');
        await expect(repeatModal).toBeVisible();
        await expect(repeatModal.locator('#m04-edit-turn-name')).toHaveValue(qaName);
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width + 1);
        await repeatModal.screenshot({ path: path.join(evidenceRoot, 'encargado-repeat-modal-' + width + '.png') });
        await repeatModal.getByRole('button', { name: 'Cancelar' }).click();
      }
      await page.reload({ waitUntil: 'domcontentloaded' });
      const reloadedRow = page.locator('#m04-template-table tbody tr[data-template-key="' + qaTemplateKey + '"]');
      await expect(reloadedRow.locator('th[scope="row"]')).toHaveText(qaName);
      await expect(reloadedRow.locator('td').nth(0)).toHaveText('14:00');
      await expect(reloadedRow.locator('td').nth(3)).toHaveText('22:00');
      expect(postedPaths.filter(item => item === editUrl)).toHaveLength(3);
      testInfo.annotations.push({ type: 'screenshots', description: evidenceRoot });
      return;
    }
    expect(editPayload.version).toBe(1);
    expect(editPayload.name).toBe('Turno 4');
    await localRow.locator('.dropdown.dropstart > a[data-bs-toggle="dropdown"]').click();
    await editAction.click();
    const modal = page.locator('#m04-edit-template-modal');
    await expect(modal).toBeVisible();
    await expect(modal.locator('#m04-edit-turn-name')).toHaveValue('Turno 4');
    await expect(modal.locator('#m04-edit-turn-start')).toHaveValue('14:00');
    await expect(modal.locator('#m04-edit-turn-end')).toHaveValue('22:00');
    await expect(modal.locator('#m04-edit-turn-break-start')).toHaveValue('18:00');
    await expect(modal.locator('#m04-edit-turn-break-end')).toHaveValue('19:00');
    await modal.screenshot({ path: path.join(evidenceRoot, 'encargado-modal-prefilled-1440.png') });
    await modal.locator('#m04-edit-turn-name').fill(qaName);
    const [saveResponse] = await Promise.all([
      page.waitForResponse(item => new URL(item.url()).pathname === editUrl && item.request().method() === 'POST'),
      modal.getByRole('button', { name: 'Guardar cambios' }).click(),
    ]);
    expect(saveResponse.status()).toBe(200);
    const result = await saveResponse.json();
    expect(result.ok).toBe(true);
    expect(result.template.clave).toBe(qaTemplateKey);
    expect(result.template.scope).toBe('STATION');
    expect(result.template.id_estacion).toBe(1);
    expect(result.template.version).toBe(2);
    expect(result.template.nombre).toBe(qaName);
    await expect(modal).not.toBeVisible();
    await expect(localRow.locator('th[scope="row"]')).toHaveText(qaName);
    await expect(localRow.locator('td').nth(0)).toHaveText('14:00');
    await expect(localRow.locator('td').nth(1)).toHaveText('18:00');
    await expect(localRow.locator('td').nth(2)).toHaveText('19:00');
    await expect(localRow.locator('td').nth(3)).toHaveText('22:00');
    await expect(localRow.locator('[data-m04-edit-template]')).toHaveAttribute('data-m04-edit-template', /"version":2/);

    const csrf = await card.getAttribute('data-csrf-token');
    const negativeStatuses = await page.evaluate(async ({ csrfToken, globalKey }) => {
      const send = async payload => {
        try {
          const response = await window.axios.post('/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas-locales/versionar', payload, {
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
          });
          return response.status;
        } catch (error) { return error?.response?.status || 0; }
      };
      const outOfStation = await send({ template_key: 'LOC_2_FOREIGN', expected_version: 1, template_name: 'Fuera de alcance', workplace_id: 2,
        segments: [{ ordinal: 1, tipo_segmento: 'WORK', hora_inicio_local: '08:00:00', hora_fin_local: '16:00:00', dia_offset_inicio: 0, dia_offset_fin: 0 }] });
      const global = await send({ template_key: globalKey, expected_version: 1, template_name: 'Global no editable', workplace_id: 1,
        segments: [{ ordinal: 1, tipo_segmento: 'WORK', hora_inicio_local: '08:00:00', hora_fin_local: '16:00:00', dia_offset_inicio: 0, dia_offset_fin: 0 }] });
      return { outOfStation, global };
    }, { csrfToken: csrf, globalKey });
    expect(negativeStatuses).toEqual({ outOfStation: 403, global: 403 });

    await page.reload({ waitUntil: 'domcontentloaded' });
    const persistedRow = page.locator('#m04-template-table tbody tr[data-template-key="' + qaTemplateKey + '"]');
    await expect(persistedRow.locator('th[scope="row"]')).toHaveText(qaName);
    await expect(persistedRow).toContainText('14:00');
    await expect(persistedRow).toContainText('22:00');
    for (const width of [1440, 768, 375]) {
      await page.setViewportSize({ width, height: 900 });
      const geometry = await page.evaluate(() => ({ viewport: innerWidth, document: document.documentElement.scrollWidth }));
      expect(geometry.document).toBeLessThanOrEqual(width + 1);
      await persistedRow.locator('.dropdown.dropstart > a[data-bs-toggle="dropdown"]').click();
      await persistedRow.locator('[data-m04-edit-template]').click();
      const responsiveModal = page.locator('#m04-edit-template-modal');
      await expect(responsiveModal).toBeVisible();
      await expect(responsiveModal.locator('#m04-edit-turn-name')).toHaveValue(qaName);
      await responsiveModal.screenshot({ path: path.join(evidenceRoot, 'encargado-modal-' + width + '.png') });
      await responsiveModal.getByRole('button', { name: 'Cancelar' }).click();
      await page.screenshot({ path: path.join(evidenceRoot, 'encargado-' + width + '.png'), fullPage: true });
    }
    expect(postedPaths.filter(item => item === editUrl)).toHaveLength(3);
  } else if (profile.key === 'direccion-operaciones') {
    expect(facts.template_admin).toBe(false);
    await expect(card.locator('#m04-btn-new-template')).toHaveCount(0);
    await expect(card.locator('#m04-edit-template-modal')).toHaveCount(0);
    await expect(localRow.locator('[data-m04-edit-template]')).toHaveCount(0);
    await expect(globalRow.locator('[data-m04-edit-template]')).toHaveCount(0);
    const csrf = await card.getAttribute('data-csrf-token');
    const deniedStatus = await page.evaluate(async csrfToken => {
      try {
        const response = await window.axios.post('/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas-locales/versionar', {
          template_key: 'LOC_1_T_4_14002200', expected_version: 2, template_name: 'No autorizado', workplace_id: 1,
          segments: [{ ordinal: 1, tipo_segmento: 'WORK', hora_inicio_local: '14:00:00', hora_fin_local: '22:00:00', dia_offset_inicio: 0, dia_offset_fin: 0 }],
        }, { headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
        return response.status;
      } catch (error) { return error?.response?.status || 0; }
    }, csrf);
    expect(deniedStatus).toBe(403);
    for (const width of [1440, 768, 375]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width + 1);
      await page.screenshot({ path: path.join(evidenceRoot, 'direccion-' + width + '.png'), fullPage: true });
    }
    expect(postedPaths.filter(item => item === editUrl)).toHaveLength(1);
  }
  expect(pageErrors).toEqual([]);
  testInfo.annotations.push({ type: 'external-origins-blocked', description: [...new Set(blockedOrigins)].join(', ') || 'none' });
  testInfo.annotations.push({ type: 'screenshots', description: evidenceRoot });
});
