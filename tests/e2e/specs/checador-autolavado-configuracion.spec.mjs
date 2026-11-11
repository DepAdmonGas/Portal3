import { test, expect } from '@playwright/test';
import path from 'path';

const brainScreenshotsDir = '/home/silvinol/.gemini/antigravity-cli/brain/21a67725-b326-49fb-8ba0-97b1b0e307f4/screenshots';
const configuracionUrl = '/departamento-operativo/recursos-humanos/checador/configuracion';

test.describe('M11: Checador Configuración - Autolavado y Palo Solo', () => {
  test('Palo Solo -> Autolavado -> Palo Solo, aislamiento, lector compartido, dispositivos y Dirección read-only', async ({ page }, testInfo) => {
    test.setTimeout(60000);

    const projectName = testInfo.project.name;

    // =========================================================================
    // CASE A: Encargado Palo Solo (User 52)
    // =========================================================================
    if (projectName === 'encargado-palo-solo') {
      await page.setViewportSize({ width: 1440, height: 950 });
      let resp = await page.goto(configuracionUrl, { waitUntil: 'domcontentloaded' });
      expect(resp?.status()).toBe(200);

      const selector = page.locator('#module-station-selector-checador');
      await expect(selector).toBeVisible();

      // Ensure we are initially on Palo Solo (estacion_2)
      const currentValue = await selector.inputValue();
      if (currentValue !== 'estacion_2') {
        const nav = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
        await selector.selectOption('estacion_2');
        await nav;
      }

      // --- 1. Palo Solo Verification ---
      await expect(selector).toHaveValue('estacion_2');

      // Check attendance policy heading
      const policyHeading = page.locator('#attendance-policy-v2-heading');
      await expect(policyHeading).toBeVisible();

      // Ensure no permission denied error is shown
      await expect(page.locator('text=No tienes permiso para consultar la configuración de esta localidad')).toHaveCount(0);

      // Reader profile section in Palo Solo
      const readerSection = page.locator('#reader-access');
      await expect(readerSection).toBeVisible();
      await expect(readerSection).toContainText('Sensor6007');
      await expect(readerSection).toContainText('Usuario activo');
      // In Palo Solo, edit button is available for Encargado
      const readerEditBtn = readerSection.locator('button:has-text("Editar")');
      await expect(readerEditBtn).toBeVisible();

      // Physical station mapping table in Palo Solo
      const mappingSection = page.locator('#m11-station-readers');
      await expect(mappingSection).toBeVisible();
      await expect(mappingSection).toContainText('Palo Solo');

      // Take screenshot of Palo Solo Configuration at 1440px
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'palo-solo-config-1440px.png') });

      // --- 2. Switch context to Autolavado (depto_9) ---
      const navToAutolavado = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
      await selector.selectOption('depto_9');
      await navToAutolavado;

      // Verify context is now depto_9
      await expect(selector).toHaveValue('depto_9');

      // --- 3. Autolavado Verification ---
      // A) Configuración de asistencia: must NOT show permission denied!
      await expect(page.locator('text=No tienes permiso para consultar la configuración de esta localidad')).toHaveCount(0);
      await expect(policyHeading).toBeVisible();

      // Autolavado must show its own attendance policy (tolerance 15 min, deadline 5 days)
      await expect(page.locator('#attendance-policy-v2')).toContainText('15');
      await expect(page.locator('#attendance-policy-v2')).toContainText('5');

      // "Editar configuración" and "Ver historial" buttons must be visible
      const editPolicyBtn = page.locator('#attendance-policy-edit-button');
      await expect(editPolicyBtn).toBeVisible();
      const historyBtn = page.locator('button:has-text("Ver historial")');
      await expect(historyBtn).toBeVisible();

      // Open Historial Modal and verify independent history
      await historyBtn.click();
      const historyModal = page.locator('#attendance-policy-history-modal');
      await expect(historyModal).toBeVisible();
      await expect(historyModal).toContainText('15 min');
      await expect(historyModal).toContainText('5 días');

      // Close modal
      const closeHistoryBtn = historyModal.locator('button:has-text("Cerrar")');
      await closeHistoryBtn.click();
      await expect(historyModal).not.toBeVisible();

      // B) Perfil del lector in Autolavado:
      // Must show shared reader reference: "Lector compartido (Palo Solo)"
      await expect(readerSection).toBeVisible();
      await expect(readerSection).toContainText('Sensor6007');
      await expect(readerSection).toContainText('Lector compartido (Palo Solo)');

      // Editing shared credentials from Autolavado must NOT be enabled
      const autolavadoReaderEditBtn = readerSection.locator('button:has-text("Editar")');
      await expect(autolavadoReaderEditBtn).toHaveCount(0);

      // C) Administración de dispositivos in Autolavado:
      const devicesSection = page.locator('#m11-devices');
      if (await devicesSection.count() > 0) {
        await expect(devicesSection).toBeVisible();
        // Wait for DataTables to load items
        const table = page.locator('#m11-devices-table');
        await expect(table).toBeVisible();
        await expect(table).toContainText('Biometrico 100');
        await expect(table).toContainText('Palo Solo');
      }

      // D) Estaciones y lectores asignados in Autolavado:
      await expect(mappingSection).toBeVisible();
      await expect(mappingSection).toContainText('Autolavado · 9');
      await expect(mappingSection).toContainText('Palo Solo (Lector compartido) · 2');
      await expect(mappingSection).toContainText('1 · Departamento 10');

      // Screenshots for Autolavado at 1440px, 768px, 375px
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'autolavado-config-1440px.png') });

      await page.setViewportSize({ width: 768, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'autolavado-config-768px.png') });

      await page.setViewportSize({ width: 375, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'autolavado-config-375px.png') });

      // Reset to 1440px
      await page.setViewportSize({ width: 1440, height: 950 });

      // --- 4. Test F5 / reload while in Autolavado ---
      await page.reload({ waitUntil: 'domcontentloaded' });
      await expect(selector).toHaveValue('depto_9');
      await expect(policyHeading).toBeVisible();
      await expect(readerSection).toContainText('Lector compartido (Palo Solo)');

      // --- 5. Switch back to Palo Solo (estacion_2) ---
      const navBackToPalo = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
      await selector.selectOption('estacion_2');
      await navBackToPalo;

      await expect(selector).toHaveValue('estacion_2');
      await expect(readerSection).toContainText('Usuario activo');
      await expect(readerSection.locator('button:has-text("Editar")')).toBeVisible();

      // Screenshot restored Palo Solo
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'palo-solo-restored-config-1440px.png') });
    }

    // =========================================================================
    // CASE B: Dirección de Operaciones (User 19) -> Read-only according to permissions
    // =========================================================================
    if (projectName === 'direccion-operaciones') {
      await page.setViewportSize({ width: 1440, height: 950 });
      let resp = await page.goto(configuracionUrl, { waitUntil: 'domcontentloaded' });
      expect(resp?.status()).toBe(200);

      const selector = page.locator('#module-station-selector-checador');
      await expect(selector).toBeVisible();

      const autolavadoOption = selector.locator('option[value="depto_9"]');
      await expect(autolavadoOption).toHaveCount(1);

      const nav = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
      await selector.selectOption('depto_9');
      await nav;

      await expect(selector).toHaveValue('depto_9');
      const readerSection = page.locator('#reader-access');
      await expect(readerSection).toBeVisible();
      await expect(readerSection).toContainText('Lector compartido (Palo Solo)');

      // Dirección is strictly read-only for reader profiles: NO edit button
      await expect(readerSection.locator('button:has-text("Editar")')).toHaveCount(0);

      await page.screenshot({ path: path.join(brainScreenshotsDir, 'direccion-autolavado-config-1440px.png') });
    }
  });
});
