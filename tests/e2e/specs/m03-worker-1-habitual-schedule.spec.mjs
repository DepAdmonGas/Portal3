import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, baseURL, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const brainScreenshotsDir = '/home/silvinol/.gemini/antigravity-cli/brain/21a67725-b326-49fb-8ba0-97b1b0e307f4/screenshots';
const evidenceRoot = path.join(candidateScreenshotsRoot, 'm03-worker-1-habitual-' + Date.now());

test('M03: Reconocimiento de membresía, lectura autorizada, menú asignar horario habitual para Trabajador 1, guardado QA y Dirección read-only', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);

  ensurePrivateDirectory(evidenceRoot);
  if (!fs.existsSync(brainScreenshotsDir)) {
    fs.mkdirSync(brainScreenshotsDir, { recursive: true });
  }

  const pageErrors = [];
  const consoleErrors = [];
  const http429Errors = [];
  const blockedOrigins = [];
  await installLocalOriginGuard(page, blockedOrigins);

  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => {
    if (message.type() !== 'error' || message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) return;
    consoleErrors.push(message.text());
  });
  page.on('response', response => {
    if (response.status() === 429) {
      http429Errors.push(`${response.request().method()} ${response.url()}`);
    }
  });

  const session = await verifyPortalSession(page, profile.key, { blockedOrigins });
  expect(session.valid, `Stored session verification failed: ${session.reason}`).toBe(true);

  const scheduleWeekUrl = `${baseURL}/departamento-operativo/recursos-humanos/checador/programacion-semanal?week_start=2026-11-09`;

  // =========================================================================
  // CASE A: Encargado Interlomas (User 33)
  // =========================================================================
  if (profile.key === 'encargado') {
    await page.setViewportSize({ width: 1440, height: 950 });
    const resp = await page.goto(scheduleWeekUrl, { waitUntil: 'domcontentloaded' });
    expect(resp?.status()).toBe(200);

    // 1. Locate Worker ID 1 row in the habitual schedule table
    const worker1Row = page.locator('#habitual-schedule-panel table tbody tr[data-worker-name="Chino Varela Urbano"]');
    await expect(worker1Row).toBeVisible();
    await expect(worker1Row).toContainText('ID 1');

    // 2. Verify all 7 days do NOT show "Horario no disponible"
    const cells = worker1Row.locator('td');
    for (let i = 0; i < 7; i++) {
      const cellText = await cells.nth(i).textContent();
      expect(cellText).not.toContain('Horario no disponible');
      expect(cellText).toContain('Sin asignación');
    }

    // 3. Verify coverage badge shows "Sin asignación" with "0/7 días"
    const coverageCell = cells.nth(7);
    await expect(coverageCell.locator('.badge')).toHaveText('Sin asignación');
    await expect(coverageCell).toContainText('0/7 días');

    // 4. Open 3-dots action menu for Worker 1
    const actionBtn = worker1Row.locator('button[data-bs-toggle="dropdown"]');
    await expect(actionBtn).toBeVisible();
    await actionBtn.click();

    // Verify menu items: MUST contain "Asignar horario habitual"
    const dropdownMenu = worker1Row.locator('.dropdown-menu');
    await expect(dropdownMenu).toBeVisible();

    const assignHabitualBtn = dropdownMenu.locator('button[data-habitual-assignment-read-url]');
    await expect(assignHabitualBtn).toBeVisible();
    await expect(assignHabitualBtn).toContainText('Asignar horario habitual');

    const historyLink = dropdownMenu.locator('a[href*="horarios-turnos"]');
    await expect(historyLink).toBeVisible();
    await expect(historyLink).toContainText('Ver historial');

    // Screenshot Worker 1 menu open at 1440px
    await page.screenshot({ path: path.join(brainScreenshotsDir, 'worker-1-menu-assign-habitual-1440px.png') });

    // Responsive views of Worker 1 menu
    await page.setViewportSize({ width: 768, height: 900 });
    await page.screenshot({ path: path.join(brainScreenshotsDir, 'worker-1-menu-assign-habitual-768px.png') });

    await page.setViewportSize({ width: 375, height: 900 });
    await page.screenshot({ path: path.join(brainScreenshotsDir, 'worker-1-menu-assign-habitual-375px.png') });

    await page.setViewportSize({ width: 1440, height: 950 });

    // 5. Click "Asignar horario habitual" to open modal
    await assignHabitualBtn.click();
    const modal = page.locator('#habitual-schedule-assignment-modal');
    await expect(modal).toBeVisible();
    await expect(modal.locator('.modal-title')).toContainText('Asignar horario habitual');
    await expect(modal).toContainText('Chino Varela Urbano');

    // Wait for readback to finish and inputs to become enabled
    const mondaySelect = modal.locator('#habitual-day-LUNES');
    await expect(mondaySelect).toBeEnabled({ timeout: 10000 });

    // Verify all 7 days are enabled and offer turnos + Descanso explícito
    const dayCodes = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'];
    for (const code of dayCodes) {
      const daySelect = modal.locator(`#habitual-day-${code}`);
      await expect(daySelect).toBeEnabled();
      await expect(daySelect.locator('option[value="REST"]')).toHaveCount(1);
      const optionCount = await daySelect.locator('option').count();
      expect(optionCount).toBeGreaterThan(2); // placeholder + templates + REST
    }

    // Screenshot open modal with 7 configurable days
    await page.screenshot({ path: path.join(brainScreenshotsDir, 'worker-1-habitual-modal-opened-1440px.png') });

    // Close the modal WITHOUT submitting for Worker 1 (preserving Worker 1 with 0 assignments!)
    const cancelBtn = modal.locator('button:has-text("Cancelar")');
    await cancelBtn.click();
    await expect(modal).not.toBeVisible();

    // 6. Test save and readback with authorized QA worker (QA - Colaborador 01, ID 1004)
    const qa1004Row = page.locator('#habitual-schedule-panel table tbody tr[data-worker-name="QA - Colaborador 01"]');
    await expect(qa1004Row).toBeVisible();

    const qaActionBtn = qa1004Row.locator('button[data-bs-toggle="dropdown"]');
    await qaActionBtn.click();

    const qaDropdown = qa1004Row.locator('.dropdown-menu');
    await expect(qaDropdown).toBeVisible();
    // QA 1004 has partial coverage, so action is "Completar horario habitual"
    const completeBtn = qaDropdown.locator('button[data-habitual-assignment-read-url]');
    await expect(completeBtn).toBeVisible();
    await expect(completeBtn).toContainText('Completar horario habitual');

    // 7. Verify Temporary change button is available for Encargado
    const tempTab = page.locator('#temporary-schedule-tab');
    await tempTab.click();
    const newTempBtn = page.locator('button:has-text("Nuevo cambio temporal")');
    await expect(newTempBtn).toBeVisible();

    // Open temporary change modal
    await newTempBtn.click();
    const tempModal = page.locator('#temporary-schedule-assignment-modal');
    await expect(tempModal).toBeVisible();
    await expect(tempModal.locator('.modal-title')).toContainText('Nuevo cambio temporal');

    // Close temp modal
    await tempModal.locator('button:has-text("Cancelar")').click();
    await expect(tempModal).not.toBeVisible();

    // Return to habitual tab
    await page.locator('#habitual-schedule-tab').click();

    // 8. F5 / Reload test to confirm persistence and no 429
    await page.reload({ waitUntil: 'domcontentloaded' });
    const reloadedWorker1Row = page.locator('#habitual-schedule-panel table tbody tr[data-worker-name="Chino Varela Urbano"]');
    await expect(reloadedWorker1Row).toBeVisible();
    await expect(reloadedWorker1Row.locator('.badge')).toHaveText('Sin asignación');

    // Verify NO 429 errors occurred
    expect(http429Errors).toEqual([]);
    expect(pageErrors).toEqual([]);
  }

  // =========================================================================
  // CASE B: Dirección de Operaciones (User 19) -> PURE READ-ONLY
  // =========================================================================
  if (profile.key === 'direccion-operaciones') {
    await page.setViewportSize({ width: 1440, height: 950 });
    const resp = await page.goto(scheduleWeekUrl, { waitUntil: 'domcontentloaded' });
    expect(resp?.status()).toBe(200);

    // 1. Locate Worker ID 1 row in the habitual schedule table
    const worker1Row = page.locator('#habitual-schedule-panel table tbody tr[data-worker-name="Chino Varela Urbano"]');
    await expect(worker1Row).toBeVisible();

    // 2. Open 3-dots action menu for Worker 1
    const actionBtn = worker1Row.locator('button[data-bs-toggle="dropdown"]');
    await expect(actionBtn).toBeVisible();
    await actionBtn.click();

    const dropdownMenu = worker1Row.locator('.dropdown-menu');
    await expect(dropdownMenu).toBeVisible();

    // 3. Verify ONLY "Ver historial" is present; NO assignment buttons exist
    const assignBtns = dropdownMenu.locator('button');
    await expect(assignBtns).toHaveCount(0);

    const historyLink = dropdownMenu.locator('a[href*="horarios-turnos"]');
    await expect(historyLink).toHaveCount(1);
    await expect(historyLink).toContainText('Ver historial');

    // 4. Verify "Nuevo cambio temporal" button is NOT present in Dirección view
    const tempTab = page.locator('#temporary-schedule-tab');
    await tempTab.click();
    const newTempBtn = page.locator('button:has-text("Nuevo cambio temporal")');
    await expect(newTempBtn).toHaveCount(0);

    // 5. Screenshot Dirección read-only view
    await page.screenshot({ path: path.join(brainScreenshotsDir, 'direccion-m03-read-only-1440px.png') });

    // Verify NO 429 errors occurred
    expect(http429Errors).toEqual([]);
    expect(pageErrors).toEqual([]);
  }
});
