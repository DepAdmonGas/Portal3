import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles, baseURL } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const m04Path = '/departamento-operativo/recursos-humanos/checador/horarios-turnos';
const evidenceRoot = path.join(candidateScreenshotsRoot, 'm04-turn-creation-final');

test('M04: creación de turnos locales, modal institucional, Axios, readback, DataTables sin F5, persistencia y aislamiento ACL', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  ensurePrivateDirectory(evidenceRoot);

  const pageErrors = [];
  const consoleErrors = [];
  const blockedOrigins = [];
  await installLocalOriginGuard(page, blockedOrigins);

  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => {
    if (message.type() !== 'error' || message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) return;
    if (profile.key === 'encargado' && /403/i.test(message.text())) return;
    consoleErrors.push(message.text());
  });

  const session = await verifyPortalSession(page, profile.key, { blockedOrigins });
  expect(session.valid, `Stored session verification failed: ${session.reason}`).toBe(true);

  if (profile.key === 'encargado') {
    expect(facts.station_template_admin).toBe(true);

    // 1. Navigate to M04 Horarios y turnos
    await page.setViewportSize({ width: 1440, height: 900 });
    const response = await page.goto(`${baseURL}${m04Path}`, { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);

    const card = page.locator('#m04-template-catalog');
    await expect(card).toBeVisible();

    // 2. Button "Nuevo" must be present for Encargado
    const newButton = card.locator('#m04-btn-new-template');
    await expect(newButton).toBeVisible();
    await expect(newButton).toContainText('Nuevo');
    await expect(newButton.locator('i.ti-plus')).toHaveCount(1);

    // 3. Open Modal
    await newButton.click();
    const modal = page.locator('#m04-create-template-modal');
    await expect(modal).toBeVisible();
    await expect(modal.locator('.modal-title')).toContainText('Nuevo turno');

    // 4. Client-side Validations
    // 4a. Empty submit
    const saveButton = modal.locator('button[type="submit"]');
    await saveButton.click();
    await expect(modal.locator('.invalid-feedback').filter({ hasText: 'nombre del turno es obligatorio' })).toBeVisible();

    // 4b. Missing hours
    await modal.locator('#m04-turn-name').fill('Turno QA Validación');
    await saveButton.click();
    await expect(modal.locator('.invalid-feedback').filter({ hasText: 'hora de entrada es obligatoria' })).toBeVisible();

    // 4c. Incomplete break (only start)
    await modal.locator('#m04-turn-start').fill('08:00');
    await modal.locator('#m04-turn-end').fill('16:00');
    await modal.locator('#m04-turn-break-start').fill('12:00');
    await saveButton.click();
    await expect(modal.locator('.invalid-feedback').filter({ hasText: 'tanto el inicio como el fin del descanso' })).toBeVisible();

    // 4d. Break out of shift bounds
    await modal.locator('#m04-turn-break-end').fill('07:00');
    await saveButton.click();
    await expect(modal.locator('.invalid-feedback').filter({ hasText: 'dentro del turno' })).toBeVisible();

    // Clear test validation values
    await modal.locator('#m04-turn-break-start').fill('');
    await modal.locator('#m04-turn-break-end').fill('');

    // 5. Fill modal form
    await modal.locator('#m04-turn-name').fill('Turno 4');
    await modal.locator('#m04-turn-start').fill('14:00');
    await modal.locator('#m04-turn-end').fill('22:00');
    await modal.locator('#m04-turn-break-start').fill('18:00');
    await modal.locator('#m04-turn-break-end').fill('19:00');

    // Screenshot modal open
    await modal.screenshot({ path: path.join(evidenceRoot, 'encargado-modal-create-filled.png') });

    const table = page.locator('#m04-template-table');
    const existingTurn4 = table.locator('tbody tr').filter({ hasText: 'Turno 4' });
    const turn4AlreadyExists = (await existingTurn4.count()) > 0;

    if (!turn4AlreadyExists) {
      // Save via Axios
      const [saveResponse] = await Promise.all([
        page.waitForResponse(res => res.url().includes('/plantillas-locales') && res.request().method() === 'POST'),
        saveButton.click(),
      ]);

      expect(saveResponse.status()).toBe(200);
      const saveBody = await saveResponse.json();
      expect(saveBody.ok).toBe(true);
      expect(saveBody.template).toBeTruthy();
      expect(saveBody.template.nombre).toBe('Turno 4');

      // Modal closes automatically
      await expect(modal).not.toBeVisible();

      // Alert success visible
      const successAlert = card.locator('.alert-success');
      await expect(successAlert).toBeVisible();
      await expect(successAlert).toContainText('Turno 4');

      // DataTables updated WITHOUT page reload (F5)
      const newRow = table.locator('tbody tr').filter({ hasText: 'Turno 4' });
      await expect(newRow).toHaveCount(1);
      await expect(newRow.locator('th[scope="row"]')).toHaveText(/^Turno 4/);
      await expect(newRow.locator('td').nth(0)).toHaveText('14:00');
      await expect(newRow.locator('td').nth(1)).toHaveText('18:00');
      await expect(newRow.locator('td').nth(2)).toHaveText('19:00');
      await expect(newRow.locator('td').nth(3)).toHaveText('22:00');
      await expect(newRow.locator('td').nth(4)).toContainText('Activa');

      // 6. Persistence check via page reload (F5)
      await page.reload({ waitUntil: 'domcontentloaded' });
      const reloadedTable = page.locator('#m04-template-table');
      const persistedRow = reloadedTable.locator('tbody tr').filter({ hasText: 'Turno 4' });
      await expect(persistedRow).toHaveCount(1);
      await expect(persistedRow.locator('th[scope="row"]')).toHaveText(/^Turno 4/);
      await expect(persistedRow.locator('td').nth(0)).toHaveText('14:00');
      await expect(persistedRow.locator('td').nth(1)).toHaveText('18:00');
      await expect(persistedRow.locator('td').nth(2)).toHaveText('19:00');
      await expect(persistedRow.locator('td').nth(3)).toHaveText('22:00');
    } else {
      // Close modal
      await modal.getByRole('button', { name: 'Cancelar' }).click();
      await expect(modal).not.toBeVisible();

      // Verify Turno 4 is already in table with proper simplified columns
      const persistedRow = table.locator('tbody tr').filter({ hasText: 'Turno 4' });
      await expect(persistedRow).toHaveCount(1);
      await expect(persistedRow.locator('th[scope="row"]')).toHaveText(/^Turno 4/);
      await expect(persistedRow.locator('td').nth(0)).toHaveText('14:00');
      await expect(persistedRow.locator('td').nth(1)).toHaveText('18:00');
      await expect(persistedRow.locator('td').nth(2)).toHaveText('19:00');
      await expect(persistedRow.locator('td').nth(3)).toHaveText('22:00');
    }

    // 7. ACL Isolation Test: Attempt to create a template on Station 2 (should fail with 403)
    const csrfToken = await page.evaluate(() => {
      const card = document.getElementById('m04-template-catalog');
      return card?.dataset.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    });

    const unauthorizedWriteStatus = await page.evaluate(async (token) => {
      try {
        const res = await window.axios.post('/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas-locales', {
          template_name: 'Turno Invasivo Estacion 2',
          workplace_id: 2,
          confirm_action: '1',
          segments: [
            { ordinal: 1, tipo_segmento: 'WORK', hora_inicio_local: '08:00:00', dia_offset_inicio: 0, hora_fin_local: '16:00:00', dia_offset_fin: 0 }
          ]
        }, {
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
          }
        });
        return res.status;
      } catch (err) {
        return err?.response?.status || 0;
      }
    }, csrfToken);

    expect(unauthorizedWriteStatus).toBe(403);

    // 8. Responsive Viewport Check
    for (const width of [1440, 768, 375]) {
      await page.setViewportSize({ width, height: 800 });
      await page.locator('#m04-template-catalog').screenshot({
        path: path.join(evidenceRoot, `encargado-catalog-${width}px.png`),
      });
    }
  } else if (profile.key === 'direccion-operaciones') {
    // Dirección: must NOT see the "Nuevo" button or modal
    await page.setViewportSize({ width: 1440, height: 900 });
    const response = await page.goto(`${baseURL}${m04Path}`, { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);

    const card = page.locator('#m04-template-catalog');
    await expect(card).toBeVisible();

    // Verify "Nuevo" button is strictly absent
    const newButton = card.locator('#m04-btn-new-template');
    await expect(newButton).toHaveCount(0);

    // Verify modal is strictly absent
    const modal = page.locator('#m04-create-template-modal');
    await expect(modal).toHaveCount(0);

    // Responsive checks for Dirección
    for (const width of [1440, 768, 375]) {
      await page.setViewportSize({ width, height: 800 });
      await card.screenshot({
        path: path.join(evidenceRoot, `direccion-catalog-${width}px.png`),
      });
    }
  }

  expect(pageErrors).toEqual([]);
  expect(consoleErrors).toEqual([]);
});
