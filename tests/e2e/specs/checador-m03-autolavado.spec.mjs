import { test, expect } from '@playwright/test';
import path from 'path';

const brainScreenshotsDir = '/home/silvinol/.gemini/antigravity-cli/brain/21a67725-b326-49fb-8ba0-97b1b0e307f4/screenshots';
const scheduleWeekUrl = '/departamento-operativo/recursos-humanos/checador/programacion-semanal?week_start=2026-11-09';

test.describe('M03: Programación semanal - Autolavado y Palo Solo', () => {
  test('Palo Solo -> Autolavado -> Palo Solo, aislamiento, turnos propios, asignación QA y Dirección read-only', async ({ page }, testInfo) => {
    test.setTimeout(60000);

    const projectName = testInfo.project.name;

    // Skip generic negative control
    if (projectName === 'encargado') {
      test.skip(true, 'Test is specific to Palo Solo Encargado and Dirección');
      return;
    }

    // =========================================================================
    // CASE A: Encargado Palo Solo (User 52)
    // =========================================================================
    if (projectName === 'encargado-palo-solo') {
      await page.setViewportSize({ width: 1440, height: 950 });
      let resp = await page.goto(scheduleWeekUrl, { waitUntil: 'domcontentloaded' });
      expect(resp?.status()).toBe(200);

      const selector = page.locator('#module-station-selector-checador');
      await expect(selector).toBeVisible();

      // Ensure we start on Palo Solo (estacion_2)
      if (await selector.inputValue() !== 'estacion_2') {
        const nav = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
        await selector.selectOption('estacion_2');
        await nav;
      }
      await expect(selector).toHaveValue('estacion_2');

      // 1. Palo Solo Verification
      await expect(page.locator('body')).toContainText('Palo Solo');
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'palo-solo-m03-initial-1440px.png') });

      // 2. Switch context to Autolavado (depto_9)
      const navToAutolavado = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
      await selector.selectOption('depto_9');
      await navToAutolavado;

      await expect(selector).toHaveValue('depto_9');

      // Verify warning alert is NOT shown
      await expect(page.locator('text=Módulo no disponible para esta localidad')).toHaveCount(0);
      await expect(page.locator('text=La programación semanal no está disponible para departamentos')).toHaveCount(0);

      // 3. Autolavado Workers Verification
      const habitualPanel = page.locator('#habitual-schedule-panel');
      await expect(habitualPanel).toBeVisible();

      // Autolavado workers must be present
      await expect(habitualPanel).toContainText('Lorenzo Lopez Gerardo');
      await expect(habitualPanel).toContainText('Ramirez Antonio Fredy Noe');
      await expect(habitualPanel).toContainText('QA M03 Alta E2E 20261104');

      // QA Worker 1009 verification
      const qa1009Row = page.locator('#habitual-schedule-panel table tbody tr[data-worker-name="QA M03 Alta E2E 20261104"]');
      await expect(qa1009Row).toBeVisible();

      // Open 3-dots action menu for QA Worker 1009
      const actionBtn = qa1009Row.locator('button[data-bs-toggle="dropdown"]');
      await expect(actionBtn).toBeVisible();
      await actionBtn.click();

      const dropdownMenu = qa1009Row.locator('.dropdown-menu');
      await expect(dropdownMenu).toBeVisible();

      const assignBtn = dropdownMenu.locator('button[data-habitual-assignment-read-url]');
      await expect(assignBtn).toBeVisible();

      const historyLink = dropdownMenu.locator('a[href*="horarios-turnos"]');
      await expect(historyLink).toBeVisible();
      await expect(historyLink).toContainText('Ver historial');

      // 4. Open Assignment Modal for QA Worker 1009
      await assignBtn.click();
      const modal = page.locator('#habitual-schedule-assignment-modal');
      await expect(modal).toBeVisible();
      await expect(modal.locator('.modal-title')).toContainText('Asignar horario habitual');

      // Wait for readback to finish
      const mondaySelect = modal.locator('#habitual-day-LUNES');
      const wednesdaySelect = modal.locator('#habitual-day-MIERCOLES');
      await expect(wednesdaySelect).toBeEnabled({ timeout: 10000 });

      // Monday has existing assignment, so it is preserved
      await expect(mondaySelect).toContainText('Asignación vigente');

      // Verify that template options belong strictly to Autolavado
      const optionTexts = await wednesdaySelect.locator('option').allTextContents();
      const allOptionText = optionTexts.join(' ');
      expect(allOptionText).toContain('Turno 1');
      expect(allOptionText).toContain('Turno 2');
      expect(allOptionText).toContain('Turno 4');
      expect(allOptionText).toContain('Descanso explícito');

      // Close modal
      await modal.locator('button:has-text("Cancelar")').click();
      await expect(modal).not.toBeVisible();

      // 5. Temporary schedule tab verification
      const tempTab = page.locator('#temporary-schedule-tab');
      await tempTab.click();

      const newTempBtn = page.locator('button:has-text("Nuevo cambio temporal")');
      await expect(newTempBtn).toBeVisible();

      // Open temporary change modal
      await newTempBtn.click();
      const tempModal = page.locator('#temporary-schedule-assignment-modal');
      await expect(tempModal).toBeVisible();

      const tempTemplateSelect = tempModal.locator('#temporary-assignment-template');
      const tempOptionTexts = await tempTemplateSelect.locator('option').allTextContents();
      const allTempText = tempOptionTexts.join(' ');
      expect(allTempText).toContain('Turno 1');
      expect(allTempText).toContain('Turno 2');
      expect(allTempText).toContain('Turno 4');
      expect(allTempText).toContain('Descanso explícito');

      // Close temp modal
      await tempModal.locator('button:has-text("Cancelar")').click();
      await expect(tempModal).not.toBeVisible();

      // Return to habitual tab
      await page.locator('#habitual-schedule-tab').click();

      // 6. Screenshots of Autolavado at 1440px, 1024px, 768px, 375px
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'autolavado-m03-1440px.png') });

      await page.setViewportSize({ width: 1024, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'autolavado-m03-1024px.png') });

      await page.setViewportSize({ width: 768, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'autolavado-m03-768px.png') });

      await page.setViewportSize({ width: 375, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'autolavado-m03-375px.png') });

      // Reset to 1440px
      await page.setViewportSize({ width: 1440, height: 950 });

      // 7. F5 / Reload test in Autolavado
      await page.reload({ waitUntil: 'domcontentloaded' });
      await expect(selector).toHaveValue('depto_9');
      await expect(page.locator('text=Módulo no disponible para esta localidad')).toHaveCount(0);
      await expect(page.locator('#habitual-schedule-panel')).toContainText('Lorenzo Lopez Gerardo');

      // 8. Switch back to Palo Solo (estacion_2)
      const navBackToPalo = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
      await selector.selectOption('estacion_2');
      await navBackToPalo;

      await expect(selector).toHaveValue('estacion_2');
      await expect(page.locator('#habitual-schedule-panel')).not.toContainText('Lorenzo Lopez Gerardo');
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'palo-solo-m03-restored-1440px.png') });
    }

    // =========================================================================
    // CASE B: Dirección de Operaciones (User 19) -> Read-only
    // =========================================================================
    if (projectName === 'direccion-operaciones') {
      await page.setViewportSize({ width: 1440, height: 950 });
      let resp = await page.goto(scheduleWeekUrl, { waitUntil: 'domcontentloaded' });
      expect(resp?.status()).toBe(200);

      const selector = page.locator('#module-station-selector-checador');
      await expect(selector).toBeVisible();

      // Switch context to Autolavado
      const nav = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
      await selector.selectOption('depto_9');
      await nav;

      await expect(selector).toHaveValue('depto_9');
      await expect(page.locator('text=Módulo no disponible para esta localidad')).toHaveCount(0);

      const habitualPanel = page.locator('#habitual-schedule-panel');
      await expect(habitualPanel).toBeVisible();
      await expect(habitualPanel).toContainText('QA M03 Alta E2E 20261104');

      // Dirección is strictly read-only: Worker 1009 3-dots menu must ONLY have "Ver historial"
      const qa1009Row = page.locator('#habitual-schedule-panel table tbody tr[data-worker-name="QA M03 Alta E2E 20261104"]');
      const actionBtn = qa1009Row.locator('button[data-bs-toggle="dropdown"]');
      await expect(actionBtn).toBeVisible();
      await actionBtn.click();

      const dropdownMenu = qa1009Row.locator('.dropdown-menu');
      await expect(dropdownMenu).toBeVisible();

      // NO assign/complete buttons in Dirección
      await expect(dropdownMenu.locator('button')).toHaveCount(0);
      const historyLink = dropdownMenu.locator('a[href*="horarios-turnos"]');
      await expect(historyLink).toHaveCount(1);
      await expect(historyLink).toContainText('Ver historial');

      // In temporary tab: NO "Nuevo cambio temporal" button
      const tempTab = page.locator('#temporary-schedule-tab');
      await tempTab.click();
      await expect(page.locator('button:has-text("Nuevo cambio temporal")')).toHaveCount(0);

      await page.screenshot({ path: path.join(brainScreenshotsDir, 'direccion-autolavado-m03-1440px.png') });
    }
  });
});
