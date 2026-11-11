import { test, expect } from '@playwright/test';
import path from 'path';

const brainScreenshotsDir = '/home/silvinol/.gemini/antigravity-cli/brain/21a67725-b326-49fb-8ba0-97b1b0e307f4/screenshots';
const journeysUrl = '/departamento-operativo/recursos-humanos/checador/jornadas';

test.describe('CHECADOR_V2_M05_JORNADAS_LABORALES: Consulta, filtros, presentación y visualización de jornadas persistidas', () => {
  test('Verificar interfaz de Jornadas laborales, filtros, encabezado institucional, estado vacío y responsive', async ({ page }, testInfo) => {
    test.setTimeout(60000);

    const projectName = testInfo.project.name;
    if (projectName === 'encargado') {
      test.skip(true, 'Test focused on Palo Solo Encargado and Dirección');
      return;
    }

    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error' && !msg.text().includes('net::ERR_BLOCKED_BY_CLIENT')) {
        consoleErrors.push(msg.text());
      }
    });

    // 1. NAVEGACIÓN Y CARGA DE LA VISTA
    await page.setViewportSize({ width: 1440, height: 950 });
    const response = await page.goto(journeysUrl, { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);

    // 2. VERIFICAR ENCABEZADO INSTITUCIONAL Y BREADCRUMB
    const breadcrumb = page.locator('[aria-label="breadcrumb"]');
    await expect(breadcrumb).toBeVisible();

    const cardHeader = page.locator('section[aria-labelledby="journey-workspace-heading"] .card-header');
    await expect(cardHeader).toBeVisible();
    await expect(cardHeader).toHaveClass(/bg-primary/);
    await expect(cardHeader.locator('#journey-workspace-heading')).toHaveText('Jornadas laborales');

    // 3. VERIFICAR CONTROLES DE FILTROS
    const filterForm = page.locator('form[aria-label="Filtros de consulta de jornadas"]');
    await expect(filterForm).toBeVisible();

    const dateFromInput = filterForm.locator('#journey-date-from');
    const dateToInput = filterForm.locator('#journey-date-to');
    const workerSelect = filterForm.locator('#journey-worker-filter');
    const statusSelect = filterForm.locator('#journey-status-filter');
    const submitBtn = filterForm.locator('button[type="submit"]');

    await expect(dateFromInput).toBeVisible();
    await expect(dateToInput).toBeVisible();
    await expect(workerSelect).toBeAttached();
    await expect(statusSelect).toBeAttached();
    await expect(submitBtn).toBeVisible();

    // 4. VERIFICAR ESTADO VACÍO (BD admongas sin jornadas evaluadas)
    // No debe inventar jornadas ficticias
    const emptyAlert = page.locator('.alert.alert-secondary', { hasText: 'No se encontraron jornadas laborales para los filtros seleccionados.' });
    await expect(emptyAlert).toBeVisible();

    // 5. VERIFICAR AISLAMIENTO DE LOCALIDAD
    if (projectName === 'encargado-palo-solo') {
      const contextText = await page.locator('[aria-label="Contexto de Checador"]').textContent();
      expect(contextText).toContain('Palo Solo');
    }

    // 6. DIRECCIÓN DE OPERACIONES ES DE SOLO LECTURA
    if (projectName === 'direccion-operaciones') {
      // No debe haber botones de creación o edición de jornadas
      const createButtons = page.locator('button:has-text("Nueva jornada"), button:has-text("Crear jornada")');
      await expect(createButtons).toHaveCount(0);
    }

    // 7. CAPTURAS RESPONSIVAS EN LOS 4 VIEWPORTS OBLIGATORIOS (1440, 1024, 768, 375 px)
    if (projectName === 'encargado-palo-solo') {
      // 1440px
      await page.setViewportSize({ width: 1440, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-1440px.png'), fullPage: true });

      // 1024px
      await page.setViewportSize({ width: 1024, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-1024px.png'), fullPage: true });

      // 768px
      await page.setViewportSize({ width: 768, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-768px.png'), fullPage: true });

      // 375px
      await page.setViewportSize({ width: 375, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-375px.png'), fullPage: true });
    }

    if (projectName === 'direccion-operaciones') {
      await page.setViewportSize({ width: 1440, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-direccion-1440px.png'), fullPage: true });
    }

    expect(consoleErrors).toEqual([]);
  });
});
