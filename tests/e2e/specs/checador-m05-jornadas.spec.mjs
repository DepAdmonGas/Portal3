import { test, expect } from '@playwright/test';
import path from 'path';

const brainScreenshotsDir = '/home/silvinol/.gemini/antigravity-cli/brain/21a67725-b326-49fb-8ba0-97b1b0e307f4/screenshots';
const journeysUrl = '/departamento-operativo/recursos-humanos/checador/jornadas';

test.describe('CHECADOR_V2_M05_JORNADAS: Modal institucional de búsqueda, remoción de bloque de contexto y Select2', () => {
  test('Verificar modal de búsqueda, eliminación de bloque de contexto, filtros y responsive', async ({ page }, testInfo) => {
    test.setTimeout(90000);

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

    // 3. VERIFICAR QUE EL BLOQUE INFORMATIVO DE CENTRO/LOCALIDAD FUE ELIMINADO
    const contextBlock = page.locator('[aria-label="Contexto de Checador"]');
    await expect(contextBlock).toHaveCount(0);

    // 4. VERIFICAR BOTÓN "BUSCAR / FILTRAR" Y BARRA DE CRITERIOS
    const openModalBtn = page.locator('#open-journeys-search-modal-btn');
    await expect(openModalBtn).toBeVisible();
    await expect(openModalBtn).toContainText('Buscar / Filtrar');

    const criteriaSummary = page.locator('[aria-label="Criterios de consulta activos"]');
    await expect(criteriaSummary).toBeVisible();

    // 5. ABRIR EL MODAL INSTITUCIONAL DE BÚSQUEDA
    await openModalBtn.click();
    const searchModal = page.locator('#checador-journeys-search-modal');
    await expect(searchModal).toBeVisible();

    // 6. VERIFICAR CONTROLES DENTRO DEL MODAL
    const filterForm = searchModal.locator('#journeys-filter-form');
    await expect(filterForm).toBeVisible();

    const dateFromInput = filterForm.locator('#journey-date-from');
    const dateToInput = filterForm.locator('#journey-date-to');
    const workerSelect = filterForm.locator('#journey-worker-filter');
    const statusSelect = filterForm.locator('#journey-status-filter');
    const resetBtn = filterForm.locator('#journeys-reset-filters-btn');
    const cancelBtn = filterForm.locator('button[data-bs-dismiss="modal"]', { hasText: 'Cancelar' });
    const submitBtn = filterForm.locator('#journeys-submit-filters-btn');

    await expect(dateFromInput).toBeVisible();
    await expect(dateToInput).toBeVisible();
    await expect(workerSelect).toBeAttached();
    await expect(statusSelect).toBeAttached();
    await expect(resetBtn).toBeVisible();
    await expect(cancelBtn).toBeVisible();
    await expect(submitBtn).toBeVisible();

    // 7. VERIFICAR GEOMETRÍA Y ALINEACIÓN DE FLECHAS SELECT2
    const visualMetrics = await page.evaluate(() => {
      function checkControl(selId) {
        const el = document.getElementById(selId);
        const container = el ? el.nextElementSibling : null;
        const singleSel = container ? container.querySelector('.select2-selection--single') : null;
        const arrow = container ? container.querySelector('.select2-selection__arrow') : null;
        const b = arrow ? arrow.querySelector('b') : null;

        const singleRect = singleSel?.getBoundingClientRect();
        const arrowRect = arrow?.getBoundingClientRect();
        const bRect = b?.getBoundingClientRect();

        return {
          present: !!container,
          singleHeight: singleRect ? Math.round(singleRect.height) : 0,
          arrowInside: arrowRect && singleRect ? (arrowRect.top >= singleRect.top - 2 && arrowRect.bottom <= singleRect.bottom + 2) : false,
          bInside: bRect && singleRect ? (bRect.top >= singleRect.top && bRect.bottom <= singleRect.bottom) : false,
          arrowTop: arrowRect ? Math.round(arrowRect.top) : 0,
          singleTop: singleRect ? Math.round(singleRect.top) : 0,
        };
      }

      const submit = document.getElementById('journeys-submit-filters-btn');
      const dateInp = document.getElementById('journey-date-from');
      const submitRect = submit?.getBoundingClientRect();
      const dateRect = dateInp?.getBoundingClientRect();

      return {
        submitHeight: submitRect ? Math.round(submitRect.height) : 0,
        submitText: submit?.innerText?.trim(),
        dateHeight: dateRect ? Math.round(dateRect.height) : 0,
        worker: checkControl('journey-worker-filter'),
        status: checkControl('journey-status-filter'),
      };
    });

    expect(visualMetrics.submitHeight).toBeGreaterThanOrEqual(38);
    expect(visualMetrics.submitHeight).toBeLessThanOrEqual(42);
    expect(visualMetrics.submitText).toContain('Consultar');
    expect(visualMetrics.dateHeight).toBeGreaterThanOrEqual(38);
    expect(visualMetrics.dateHeight).toBeLessThanOrEqual(42);

    // Verificación Colaborador
    expect(visualMetrics.worker.present).toBe(true);
    expect(visualMetrics.worker.singleHeight).toBeGreaterThanOrEqual(38);
    expect(visualMetrics.worker.singleHeight).toBeLessThanOrEqual(42);
    expect(visualMetrics.worker.arrowInside).toBe(true);
    expect(visualMetrics.worker.bInside).toBe(true);

    // Verificación Estado
    expect(visualMetrics.status.present).toBe(true);
    expect(visualMetrics.status.singleHeight).toBeGreaterThanOrEqual(38);
    expect(visualMetrics.status.singleHeight).toBeLessThanOrEqual(42);
    expect(visualMetrics.status.arrowInside).toBe(true);
    expect(visualMetrics.status.bInside).toBe(true);

    // 7.1 PROBAR INTERACCIÓN SELECT2 (DROPDOWNPARENT Y SELECCIÓN)
    const workerContainer = searchModal.locator('#journey-worker-filter + .select2-container');
    await workerContainer.click();
    const workerDropdown = searchModal.locator('.select2-dropdown');
    await expect(workerDropdown).toBeVisible();

    if (projectName === 'encargado-palo-solo') {
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-select2-desplegado-1440px.png') });
    }

    // Cerrar dropdown haciendo click en el modal body
    await searchModal.locator('.modal-header').click();

    const statusContainer = searchModal.locator('#journey-status-filter + .select2-container');
    await statusContainer.click();
    const statusDropdown = searchModal.locator('.select2-dropdown');
    await expect(statusDropdown).toBeVisible();
    const statusOption = statusDropdown.locator('.select2-results__option', { hasText: 'Calculada' }).first();
    if (await statusOption.count() > 0) {
      await statusOption.click();
      expect(await page.locator('#journey-status-filter').inputValue()).toBe('CALCULATED');
    } else {
      await searchModal.locator('.modal-header').click();
    }

    // 8. TESTEAR BOTÓN LIMPIAR FILTROS (SIN ENVIAR EL FORMULARIO)
    await resetBtn.click();
    await page.waitForTimeout(200);

    const resetValues = await page.evaluate(() => {
      return {
        workerVal: document.getElementById('journey-worker-filter')?.value,
        statusVal: document.getElementById('journey-status-filter')?.value,
        modalStillVisible: document.getElementById('checador-journeys-search-modal')?.classList.contains('show'),
      };
    });

    expect(resetValues.workerVal).toBe('');
    expect(resetValues.statusVal).toBe('');
    expect(resetValues.modalStillVisible).toBe(true);

    // 9. TESTEAR BOTÓN CANCELAR (CIERRA EL MODAL)
    await cancelBtn.click();
    await expect(searchModal).toBeHidden();

    // 10. VERIFICAR ESTADO VACÍO (BD admongas sin jornadas persistidas para la fecha)
    const emptyAlert = page.locator('.alert.alert-secondary', { hasText: 'No se encontraron jornadas laborales para los filtros seleccionados.' });
    await expect(emptyAlert).toBeVisible();

    // 11. CAPTURAS RESPONSIVAS EN LOS 4 VIEWPORTS OBLIGATORIOS (1440, 1024, 768, 375 px)
    if (projectName === 'encargado-palo-solo') {
      // 1440px vista principal (sin bloque informativo)
      await page.setViewportSize({ width: 1440, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-1440px.png'), fullPage: true });

      // 1440px modal de búsqueda abierto
      await openModalBtn.click();
      await expect(searchModal).toBeVisible();
      await page.waitForTimeout(300);
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-modal-1440px.png') });
      await cancelBtn.click();
      await expect(searchModal).toBeHidden();

      // 1024px
      await page.setViewportSize({ width: 1024, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-1024px.png'), fullPage: true });

      // 1024px modal abierto
      await openModalBtn.click();
      await expect(searchModal).toBeVisible();
      await page.waitForTimeout(300);
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-modal-1024px.png') });
      await cancelBtn.click();
      await expect(searchModal).toBeHidden();

      // 768px
      await page.setViewportSize({ width: 768, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-768px.png'), fullPage: true });

      // 768px modal abierto
      await openModalBtn.click();
      await expect(searchModal).toBeVisible();
      await page.waitForTimeout(300);
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-modal-768px.png') });
      await cancelBtn.click();
      await expect(searchModal).toBeHidden();

      // 375px
      await page.setViewportSize({ width: 375, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-375px.png'), fullPage: true });

      // 375px modal abierto
      await openModalBtn.click();
      await expect(searchModal).toBeVisible();
      await page.waitForTimeout(300);
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-modal-375px.png') });
      await cancelBtn.click();
      await expect(searchModal).toBeHidden();
    }

    if (projectName === 'direccion-operaciones') {
      await page.setViewportSize({ width: 1440, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-direccion-1440px.png'), fullPage: true });
    }

    // 12. VERIFICAR AISLAMIENTO DE LOCALIDAD Y CAMBIO DE ESTACIÓN
    if (projectName === 'encargado-palo-solo') {
      const stationSelector = page.locator('#module-station-selector-checador');
      if (await stationSelector.count() > 0) {
        const autolavadoOption = stationSelector.locator('option').filter({ hasText: 'Autolavado' }).first();
        if (await autolavadoOption.count() > 0) {
          const contextVal = await autolavadoOption.getAttribute('value');
          const contextRespPromise = page.waitForResponse(resp => resp.url().includes('/api/module-context/set') && resp.status() === 200);
          await stationSelector.selectOption(contextVal);
          await contextRespPromise;
          await page.waitForLoadState('domcontentloaded');
          await page.waitForTimeout(500);

          // Captura Autolavado
          await page.setViewportSize({ width: 1440, height: 950 });
          await page.screenshot({ path: path.join(brainScreenshotsDir, 'm05-jornadas-autolavado-1440px.png'), fullPage: true });

          // Verificar que el bloque de contexto tampoco aparece en Autolavado
          await expect(page.locator('[aria-label="Contexto de Checador"]')).toHaveCount(0);

          // Restaurar a Palo Solo
          const paloSoloOption = stationSelector.locator('option').filter({ hasText: 'Palo Solo' }).first();
          if (await paloSoloOption.count() > 0) {
            const paloSoloVal = await paloSoloOption.getAttribute('value');
            const resetPromise = page.waitForResponse(resp => resp.url().includes('/api/module-context/set') && resp.status() === 200);
            await stationSelector.selectOption(paloSoloVal);
            await resetPromise;
            await page.waitForLoadState('domcontentloaded');
            await page.waitForTimeout(500);
          }
        }
      }
    }

    expect(consoleErrors).toEqual([]);
  });
});
