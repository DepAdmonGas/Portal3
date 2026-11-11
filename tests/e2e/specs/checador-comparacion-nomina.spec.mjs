import { test, expect } from '@playwright/test';
import path from 'path';

const brainScreenshotsDir = '/home/silvinol/.gemini/antigravity-cli/brain/21a67725-b326-49fb-8ba0-97b1b0e307f4/screenshots';
const payrollUrl = '/departamento-operativo/recursos-humanos/checador/comparacion-nomina';

test.describe('CHECADOR_V2_COMPARACION_NOMINA: Modal de búsqueda, DataTables Bootstrap 5, paginación y responsive', () => {
  test('Verificar modal institucional de búsqueda, Select2, DataTables, paginación y responsive en 4 resoluciones', async ({ page }, testInfo) => {
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
    const response = await page.goto(payrollUrl + '?year=2026&period_number=44', { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);

    // 2. VERIFICAR ENCABEZADO INSTITUCIONAL Y BREADCRUMB
    const breadcrumb = page.locator('[aria-label="breadcrumb"]');
    await expect(breadcrumb).toBeVisible();

    const cardHeader = page.locator('section[aria-labelledby="payroll-comparison-workspace-heading"] .card-header');
    await expect(cardHeader).toBeVisible();
    await expect(cardHeader).toHaveClass(/bg-primary/);
    await expect(cardHeader.locator('#payroll-comparison-workspace-heading')).toHaveText('Comparación con Nómina');

    // 3. FLUJO DIRECCIÓN DE OPERACIONES
    if (projectName === 'direccion-operaciones') {
      const stationSelector = page.locator('#module-station-selector-checador');
      await expect(stationSelector).toBeVisible();

      // Seleccionar Palo Solo si no hay seleccionada
      const paloSoloOpt = stationSelector.locator('option').filter({ hasText: /Palo Solo/i }).first();
      if (await paloSoloOpt.count() > 0) {
        const val = await paloSoloOpt.getAttribute('value');
        const contextPromise = page.waitForResponse(resp => resp.url().includes('/api/module-context/set') && resp.status() === 200);
        await stationSelector.selectOption(val);
        await contextPromise;
        await page.waitForLoadState('domcontentloaded');
        await page.goto(payrollUrl + '?year=2026&period_number=44', { waitUntil: 'domcontentloaded' });
      }

      await page.setViewportSize({ width: 1440, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'checador-nomina-direccion-1440px.png'), fullPage: true });

      const searchBtn = page.locator('#open-payroll-search-modal-btn');
      await expect(searchBtn).toBeVisible();
      const dirTable = page.locator('#checador-payroll-table');
      await expect(dirTable).toBeVisible();

      expect(consoleErrors).toEqual([]);
      return;
    }

    // 4. FLUJO ENCARGADO PALO SOLO
    // 4.1 VERIFICAR BOTÓN BUSCAR / FILTRAR Y RESUMEN COMPACTO
    const searchModalBtn = page.locator('#open-payroll-search-modal-btn');
    await expect(searchModalBtn).toBeVisible();
    await expect(searchModalBtn).toContainText('Buscar / Filtrar');

    // Resumen compacto de criterios activos
    const activeCriteria = page.locator('[aria-label="Criterios de consulta activos"]');
    await expect(activeCriteria).toBeVisible();
    await expect(activeCriteria).toContainText('Semana 44');

    // 4.2 VERIFICAR MODAL DE BÚSQUEDA Y SUS ELEMENTOS
    await searchModalBtn.click();
    const searchModal = page.locator('#checador-payroll-search-modal');
    await expect(searchModal).toBeVisible();

    // Encabezado institucional del modal
    const modalHeader = searchModal.locator('.modal-header');
    await expect(modalHeader).toHaveClass(/bg-primary/);
    await expect(modalHeader.locator('#payroll-search-modal-title')).toHaveText('Buscar comparación con Nómina');
    await expect(searchModal).toContainText('Selecciona el periodo y los criterios para consultar la información de asistencia y nómina');

    // Formulario y campos del modal
    const filterForm = searchModal.locator('#payroll-comparison-filter-form');
    await expect(filterForm).toBeVisible();

    const yearSelect = filterForm.locator('#comparison-year');
    const periodSelect = filterForm.locator('#comparison-period-select');
    const workerSelect = filterForm.locator('#comparison-worker-filter');
    const statusSelect = filterForm.locator('#comparison-status-filter');
    const datesInfo = filterForm.locator('#comparison-period-dates-text');

    await expect(yearSelect).toBeVisible();
    await expect(periodSelect).toBeVisible();
    await expect(workerSelect).toBeAttached();
    await expect(statusSelect).toBeVisible();
    await expect(datesInfo).toBeVisible();
    await expect(datesInfo).toContainText('2026');

    // Select2 integrado en modal con dropdownParent
    const select2Container = filterForm.locator('.select2-modal-field .select2-container');
    await expect(select2Container).toBeVisible();

    // Botones del modal
    const resetBtn = filterForm.locator('#payroll-reset-filters-btn');
    const cancelBtn = filterForm.locator('.modal-footer button[data-bs-dismiss="modal"]');
    const submitBtn = filterForm.locator('#payroll-submit-filters-btn');

    await expect(resetBtn).toBeVisible();
    await expect(resetBtn).toContainText('Limpiar filtros');
    await expect(cancelBtn).toBeVisible();
    await expect(cancelBtn).toContainText('Cancelar');
    await expect(submitBtn).toBeVisible();
    await expect(submitBtn).toContainText('Consultar');

    // Captura del modal de búsqueda abierto (Desktop)
    await page.screenshot({ path: path.join(brainScreenshotsDir, 'checador-nomina-search-modal-1440px.png'), fullPage: false });

    // Probar Cancelar (cierra modal sin cambios)
    await cancelBtn.click();
    await expect(searchModal).not.toBeVisible();

    // 4.3 VERIFICAR DATATABLES BOOTSTRAP 5
    const table = page.locator('#checador-payroll-table');
    await expect(table).toBeVisible();

    // Ausencia de búsqueda duplicada ("Buscar colaborador:")
    const duplicateSearch = page.locator('#checador-payroll-table_filter');
    expect(await duplicateSearch.count()).toBe(0);

    // Selector de registros por página [10, 25, 50]
    const lengthSelect = page.locator('#checador-payroll-table_length select');
    await expect(lengthSelect).toBeVisible();
    const lengthOptions = await lengthSelect.locator('option').allInnerTexts();
    expect(lengthOptions).toEqual(['10', '25', '50']);

    // Paginación y conteos iniciales (Palo Solo Semana 44/2026 tiene 21 colaboradores)
    const infoText = page.locator('#checador-payroll-table_info');
    await expect(infoText).toContainText('Mostrando 1 a 21 de 21 colaboradores');

    const paginate = page.locator('#checador-payroll-table_paginate');
    await expect(paginate).toBeVisible();
    await expect(paginate.locator('.page-item.active')).toHaveText('1');

    // Cambiar a 10 registros por página para verificar paginación activa
    await lengthSelect.selectOption('10');
    await expect(infoText).toContainText('Mostrando 1 a 10 de 21 colaboradores');

    const page2Btn = paginate.locator('.page-item').filter({ hasText: '2' });
    await expect(page2Btn).toBeVisible();
    await page2Btn.click();
    await expect(infoText).toContainText('Mostrando 11 a 20 de 21 colaboradores');
    await expect(paginate.locator('.page-item.active')).toHaveText('2');

    // Regresar a 25 registros por página
    await lengthSelect.selectOption('25');
    await expect(infoText).toContainText('Mostrando 1 a 21 de 21 colaboradores');

    // 4.4 VERIFICAR FILAS Y BOTÓN DETALLE
    const rows = table.locator('tbody tr');
    const count = await rows.count();
    expect(count).toBe(21);

    const targetRow = rows.filter({ hasText: 'ALAN ISRAEL PASCUAL GARCIA' }).first();
    await expect(targetRow.locator('td').first()).toContainText('ALAN ISRAEL PASCUAL GARCIA');
    await expect(targetRow.locator('td').nth(1)).toContainText('Palo Solo');
    await expect(targetRow.locator('td').nth(2)).toContainText('Semana 44');

    const detailBtn = targetRow.locator('.table-actions-cell button');
    await expect(detailBtn).toBeVisible();
    await expect(detailBtn).toHaveText(/Detalle/);

    await detailBtn.click();
    const detailModal = page.locator('.modal.show');
    await expect(detailModal).toBeVisible();
    await expect(detailModal.locator('.modal-title')).toContainText('Detalle de comparación');
    await expect(detailModal).toContainText('Referencias de Nómina');
    await expect(detailModal).toContainText('Asistencia observada en Checador V2');
    await expect(detailModal).toContainText('Dictamen de Comparación');

    await detailModal.locator('.btn-close').click();
    await expect(detailModal).not.toBeVisible();

    // 5. CAPTURAS RESPONSIVAS EN LOS 4 VIEWPORTS OBLIGATORIOS (1440, 1024, 768, 375 px)
    // 1440px
    await page.setViewportSize({ width: 1440, height: 950 });
    await page.screenshot({ path: path.join(brainScreenshotsDir, 'checador-nomina-palo-solo-1440px.png'), fullPage: true });

    // 1024px
    await page.setViewportSize({ width: 1024, height: 950 });
    await page.screenshot({ path: path.join(brainScreenshotsDir, 'checador-nomina-palo-solo-1024px.png'), fullPage: true });

    // 768px
    await page.setViewportSize({ width: 768, height: 950 });
    await page.screenshot({ path: path.join(brainScreenshotsDir, 'checador-nomina-palo-solo-768px.png'), fullPage: true });

    // 375px
    await page.setViewportSize({ width: 375, height: 950 });
    await page.screenshot({ path: path.join(brainScreenshotsDir, 'checador-nomina-palo-solo-375px.png'), fullPage: true });

    // Modal en móvil (375px)
    await searchModalBtn.click();
    await expect(searchModal).toBeVisible();
    await page.screenshot({ path: path.join(brainScreenshotsDir, 'checador-nomina-search-modal-375px.png'), fullPage: false });
    await cancelBtn.click();
    await expect(searchModal).not.toBeVisible();

    // 6. VERIFICAR AUTOLAVADO
    await page.setViewportSize({ width: 1440, height: 950 });
    const stationSelector = page.locator('#module-station-selector-checador');
    if (await stationSelector.count() > 0) {
      const autolavadoOption = stationSelector.locator('option').filter({ hasText: 'Autolavado' }).first();
      if (await autolavadoOption.count() > 0) {
        const contextVal = await autolavadoOption.getAttribute('value');
        const contextRespPromise = page.waitForResponse(resp => resp.url().includes('/api/module-context/set') && resp.status() === 200);
        await stationSelector.selectOption(contextVal);
        await contextRespPromise;
        await page.waitForLoadState('domcontentloaded');

        // Ir a semana 8 para autolavado
        await page.goto(payrollUrl + '?year=2026&period_number=8', { waitUntil: 'domcontentloaded' });
        await page.screenshot({ path: path.join(brainScreenshotsDir, 'checador-nomina-autolavado-1440px.png'), fullPage: true });

        const autoTable = page.locator('#checador-payroll-table');
        await expect(autoTable).toBeVisible();
        const autoRows = await autoTable.locator('tbody tr').count();
        expect(autoRows).toBe(18);

        // Restaurar a Palo Solo
        const paloSoloOption = stationSelector.locator('option').filter({ hasText: 'Palo Solo' }).first();
        if (await paloSoloOption.count() > 0) {
          const paloSoloVal = await paloSoloOption.getAttribute('value');
          const resetPromise = page.waitForResponse(resp => resp.url().includes('/api/module-context/set') && resp.status() === 200);
          await stationSelector.selectOption(paloSoloVal);
          await resetPromise;
          await page.waitForLoadState('domcontentloaded');
        }
      }
    }

    expect(consoleErrors).toEqual([]);
  });
});
