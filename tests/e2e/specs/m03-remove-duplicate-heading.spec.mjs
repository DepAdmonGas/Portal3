import { test, expect } from '@playwright/test';
import path from 'path';

const brainScreenshotsDir = '/home/silvinol/.gemini/antigravity-cli/brain/21a67725-b326-49fb-8ba0-97b1b0e307f4/screenshots';
const scheduleWeekUrl = '/departamento-operativo/recursos-humanos/checador/programacion-semanal?week_start=2026-11-09';

test.describe('CHECADOR_V2_M03_REMOVE_DUPLICATE_HEADING: Limpieza de encabezado duplicado en M03', () => {
  test('Verificar eliminación de encabezado duplicado y subtítulo, preservando título institucional, breadcrumb, menú de opciones y pestañas', async ({ page }, testInfo) => {
    test.setTimeout(60000);

    const projectName = testInfo.project.name;
    // Run for encargado-palo-solo and direccion-operaciones
    if (projectName === 'encargado') {
      test.skip(true, 'Test focused on Palo Solo Encargado and Dirección');
      return;
    }

    await page.setViewportSize({ width: 1440, height: 950 });
    const response = await page.goto(scheduleWeekUrl, { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);

    // 1. VERIFICAR TÍTULO INSTITUCIONAL
    const institutionalHeader = page.locator('h4.fw-semibold', { hasText: 'Programación semanal de turnos' });
    await expect(institutionalHeader).toBeVisible();

    // 2. VERIFICAR ELIMINACIÓN DE ENCABEZADO ADICIONAL Y SUBTÍTULO
    // No debe existir ningún h1 con "Programación semanal"
    const duplicateH1 = page.locator('h1', { hasText: 'Programación semanal' });
    await expect(duplicateH1).toHaveCount(0);

    // El subtítulo "Turnos y descansos por persona." no debe aparecer en el DOM
    const subtitle = page.locator('text=Turnos y descansos por persona.');
    await expect(subtitle).toHaveCount(0);

    // 3. VERIFICAR BREADCRUMB
    const breadcrumb = page.locator('[aria-label="breadcrumb"]');
    await expect(breadcrumb).toBeVisible();

    // 4. VERIFICAR MENÚ DE TRES PUNTOS INSTITUCIONAL (DROPDOWN)
    const headerElement = page.locator('#container > header');
    await expect(headerElement).toBeVisible();
    await expect(headerElement).toHaveClass(/justify-content-end/);

    const dropdownButton = headerElement.locator('button[aria-label="Opciones del módulo"]');
    await expect(dropdownButton).toBeVisible();

    // Abrir dropdown
    await dropdownButton.click();
    await expect(dropdownButton).toHaveAttribute('aria-expanded', 'true');

    const dropdownMenu = headerElement.locator('.dropdown-menu.dropdown-menu-end');
    await expect(dropdownMenu).toBeVisible();

    // Opciones del menú
    await expect(dropdownMenu.getByRole('link', { name: 'Configuración' })).toBeVisible();
    await expect(dropdownMenu.getByRole('link', { name: 'Asistencia del día' })).toBeVisible();
    const turnsOption = dropdownMenu.locator('a.dropdown-item', { hasText: /turnos/i });
    await expect(turnsOption).toBeVisible();

    // Cerrar dropdown con Escape
    await page.keyboard.press('Escape');
    await expect(dropdownButton).toHaveAttribute('aria-expanded', 'false');

    // 5. VERIFICAR TARJETA DE SELECCIÓN DE SEMANA Y ESPACIADO
    const weekFilterCard = page.locator('section[aria-labelledby="week-filter-heading"]');
    await expect(weekFilterCard).toBeVisible();
    await expect(page.locator('#checador-week-start')).toBeVisible();

    // 6. VERIFICAR PESTAÑAS FUNCIONALES
    const habitualTab = page.locator('#habitual-schedule-tab');
    const temporaryTab = page.locator('#temporary-schedule-tab');
    await expect(habitualTab).toBeVisible();
    await expect(temporaryTab).toBeVisible();

    // Navegar entre pestañas
    await temporaryTab.click();
    await expect(page.locator('#temporary-schedule-panel')).toHaveClass(/show/);
    await habitualTab.click();
    await expect(page.locator('#habitual-schedule-panel')).toHaveClass(/show/);

    // 7. CAPTURAS RESPONSIVAS EN DIFERENTES VIEWPORTS
    if (projectName === 'encargado-palo-solo') {
      // 1440px
      await page.setViewportSize({ width: 1440, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm03-clean-header-1440px.png') });

      // 1024px
      await page.setViewportSize({ width: 1024, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm03-clean-header-1024px.png') });

      // 768px
      await page.setViewportSize({ width: 768, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm03-clean-header-768px.png') });

      // 375px
      await page.setViewportSize({ width: 375, height: 950 });
      await page.screenshot({ path: path.join(brainScreenshotsDir, 'm03-clean-header-375px.png') });
    }
  });
});
