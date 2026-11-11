import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, baseURL, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const brainScreenshotsDir = '/home/silvinol/.gemini/antigravity-cli/brain/21a67725-b326-49fb-8ba0-97b1b0e307f4/screenshots';
const evidenceRoot = path.join(candidateScreenshotsRoot, 'palo-solo-autolavado-selector-' + Date.now());

test('Validación E2E: Selector, Alcance Individual, Aislamiento de Datos y M03 Fail-Closed', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
    test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);

    // Skip direccion-operaciones for this specific test
    if (profile.key === 'direccion-operaciones') {
      test.skip(true, 'Test enfocado en Encargados');
      return;
    }

    ensurePrivateDirectory(evidenceRoot);
    if (!fs.existsSync(brainScreenshotsDir)) {
      fs.mkdirSync(brainScreenshotsDir, { recursive: true });
    }

    const pageErrors = [];
    const consoleErrors = [];
    const blockedOrigins = [];
    await installLocalOriginGuard(page, blockedOrigins);

    page.on('pageerror', error => pageErrors.push(error.message));
    page.on('console', message => {
      if (message.type() !== 'error' || message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) return;
      consoleErrors.push(message.text());
    });

    const session = await verifyPortalSession(page, profile.key, { blockedOrigins });
    expect(session.valid, `Stored session verification failed: ${session.reason}`).toBe(true);

    const asistenciaUrl = `${baseURL}/departamento-operativo/recursos-humanos/checador/asistencia`;
    const programacionUrl = `${baseURL}/departamento-operativo/recursos-humanos/checador/programacion-semanal`;
    const reporteChecadorUrl = `${baseURL}/departamento-operativo/recursos-humanos/checador/reporte`;
    const horariosTurnosUrl = `${baseURL}/departamento-operativo/recursos-humanos/checador/horarios-turnos`;

    // =========================================================================
    // CASE A: Encargado Interlomas (User 33) -> Alcance Individual Estricto
    // =========================================================================
    if (profile.key === 'encargado') {
      await page.setViewportSize({ width: 1440, height: 900 });
      const resp = await page.goto(asistenciaUrl, { waitUntil: 'domcontentloaded' });
      expect(resp?.status()).toBe(200);

      // Verify that NO select element exists (count = 0)
      const selector = page.locator('#module-station-selector-checador');
      await expect(selector).toHaveCount(0);

      // Verify badge displays Interlomas only
      const badge = page.locator('#module-station-badge-checador');
      await expect(badge).toHaveCount(1);
      await expect(badge).toContainText('Interlomas');

      // Verify Autolavado is nowhere in the DOM
      const pageContent = await page.content();
      expect(pageContent).not.toContain('Autolavado');
      return;
    }

    // =========================================================================
    // CASE B: Encargado Palo Solo (User 52) -> Selector Palo Solo / Autolavado
    // =========================================================================
    if (profile.key === 'encargado-palo-solo') {
      // 1. Visit Asistencia with initial viewport 1440
      await page.setViewportSize({ width: 1440, height: 900 });
      let resp = await page.goto(asistenciaUrl, { waitUntil: 'domcontentloaded' });
      expect(resp?.status()).toBe(200);

      const selector = page.locator('#module-station-selector-checador');
      await expect(selector).toBeVisible();

      // Verify options: placeholder, Palo Solo and Autolavado
      const options = selector.locator('option');
      await expect(options).toHaveCount(3);

      const placeholderOption = selector.locator('option[value=""]');
      await expect(placeholderOption).toHaveCount(1);

      const paloOption = selector.locator('option[value="estacion_2"]');
      await expect(paloOption).toHaveCount(1);
      await expect(paloOption).toContainText('Palo Solo');

      const autolavadoOption = selector.locator('option[value="depto_9"]');
      await expect(autolavadoOption).toHaveCount(1);
      await expect(autolavadoOption).toContainText('Autolavado');

      // The selectable items inside optgroups are exactly 2
      const groupOptions = selector.locator('optgroup option');
      await expect(groupOptions).toHaveCount(2);

      // Check optgroups
      const optgroups = selector.locator('optgroup');
      await expect(optgroups).toHaveCount(2);
      await expect(optgroups.nth(0)).toHaveAttribute('label', 'Estaciones');
      await expect(optgroups.nth(1)).toHaveAttribute('label', 'Departamentos');

      // Take screenshot of header with selector in Palo Solo at 1440px
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'palo-solo-selector-1440px.png') });

      // Responsive views for Palo Solo
      await page.setViewportSize({ width: 768, height: 900 });
      await expect(selector).toBeVisible();
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'palo-solo-selector-768px.png') });

      await page.setViewportSize({ width: 375, height: 900 });
      await expect(selector).toBeVisible();
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'palo-solo-selector-375px.png') });

      // Reset to 1440
      await page.setViewportSize({ width: 1440, height: 900 });

      // 2. Switch context to Autolavado
      // Listen for navigation or response
      const navigationPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
      await selector.selectOption('depto_9');
      await navigationPromise;

      // 3. Verify context is now Autolavado
      await expect(selector).toHaveValue('depto_9');

      // Screenshot Autolavado in Asistencia at 1440, 768, 375
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'autolavado-asistencia-1440px.png') });

      await page.setViewportSize({ width: 768, height: 900 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'autolavado-asistencia-768px.png') });

      await page.setViewportSize({ width: 375, height: 900 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'autolavado-asistencia-375px.png') });

      await page.setViewportSize({ width: 1440, height: 900 });

      // Verify that workers in Asistencia belong to Autolavado (e.g. Ramirez Antonio Fredy Noe)
      const tableContent = await page.locator('#tabla-asistencia, table').first().textContent();
      // Verify Autolavado personal appears (e.g. Alvarez Olmedo David) and Palo Solo workers are NOT present
      expect(tableContent).toMatch(/Alvarez Olmedo David|Méndez Chávez Juan Carlos/i);
      expect(tableContent).not.toMatch(/Beltran Torres Jose Luis|Chino Varela Francisco/i);

      // 4. Navigate to Reporte Checador in Autolavado context
      resp = await page.goto(reporteChecadorUrl, { waitUntil: 'domcontentloaded' });
      expect(resp?.status()).toBe(200);
      const repSelector = page.locator('#module-station-selector-checador');
      await expect(repSelector).toBeVisible();
      await expect(repSelector).toHaveValue('depto_9');

      // 5. Navigate to Programación Semanal (M03) in Autolavado context -> MUST FAIL-CLOSED
      resp = await page.goto(programacionUrl, { waitUntil: 'domcontentloaded' });
      expect(resp?.status()).toBe(200);

      // Verify warning banner exists and explains module unavailability for Autolavado
      const warningBanner = page.locator('.alert-warning');
      await expect(warningBanner).toBeVisible();
      await expect(warningBanner).toContainText('Módulo no disponible para esta localidad');
      await expect(warningBanner).toContainText('Autolavado');

      // Verify context was NOT silently overwritten back to Palo Solo!
      const progSelector = page.locator('#module-station-selector-checador');
      await expect(progSelector).toBeVisible();
      await expect(progSelector).toHaveValue('depto_9');

      // Verify empty habitual schedule state
      await expect(page.locator('text=No hay trabajadores disponibles para consultar en esta estación.')).toBeVisible();
      // Verify no worker rows are present
      await expect(page.locator('[data-schedule-matrix-panel] tbody tr')).toHaveCount(0);

      // Screenshot M03 Fail-Closed notice
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'autolavado-m03-fail-closed-1440px.png') });

      // 6. Switch back to Palo Solo from M03
      const navBackPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
      await progSelector.selectOption('estacion_2');
      await navBackPromise;

      // Verify Palo Solo schedule is restored
      const paloProgSelector = page.locator('#module-station-selector-checador');
      await expect(paloProgSelector).toBeVisible();
      await expect(paloProgSelector).toHaveValue('estacion_2');

      // Department warning banner must NOT be present for Palo Solo
      await expect(page.locator('text=Módulo no disponible para esta localidad')).toHaveCount(0);

      // Schedule matrix table is present
      const scheduleTable = page.locator('table');
      await expect(scheduleTable.first()).toBeVisible();

      // Screenshot M03 Palo Solo restored
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'palo-solo-m03-active-1440px.png') });

      // 7. Check M04 Horarios y Turnos is preserved
      resp = await page.goto(horariosTurnosUrl, { waitUntil: 'domcontentloaded' });
      expect(resp?.status()).toBe(200);
      const m04Table = page.locator('#m04-template-table');
      await expect(m04Table).toBeVisible();

      // Zero page errors
      expect(pageErrors).toEqual([]);
    }
  });
