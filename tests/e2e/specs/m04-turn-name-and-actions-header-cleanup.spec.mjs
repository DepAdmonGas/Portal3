import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, baseURL, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const route = '/departamento-operativo/recursos-humanos/checador/horarios-turnos';
const evidenceRoot = path.join(candidateScreenshotsRoot, 'm04-turn-name-actions-header-cleanup-' + Date.now());

test('M04: nombres legibles, encabezado de acciones y catálogo sin escrituras', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión autorizada para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.schedule_read).toBe(true);
  ensurePrivateDirectory(evidenceRoot);

  const writes = [];
  const pageErrors = [];
  await installLocalOriginGuard(page, []);
  page.on('request', request => {
    const url = new URL(request.url());
    if (url.hostname === 'portal3.test' && !['GET', 'HEAD', 'OPTIONS'].includes(request.method())) writes.push(`${request.method()} ${url.pathname}`);
  });
  page.on('pageerror', error => pageErrors.push(error.message));

  const session = await verifyPortalSession(page, profile.key);
  expect(session.valid, `Profile rejected: ${session.reason}`).toBe(true);

  for (const width of [1440, 768, 375]) {
    await page.setViewportSize({ width, height: 950 });
    const response = await page.goto(baseURL + route, { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);
    const table = page.locator('#m04-template-table');
    await expect(table).toBeVisible();
    const geometry = await page.locator('#m04-template-catalog .table-responsive').evaluate(wrapper => ({
      viewport: innerWidth,
      wrapper: wrapper.clientWidth,
      table: wrapper.querySelector('#m04-template-table')?.scrollWidth ?? 0,
    }));
    expect(geometry.wrapper).toBeLessThanOrEqual(width);
    if (width === 375) expect(geometry.table).toBeGreaterThan(geometry.wrapper);

    const actionsHeader = table.locator('thead th[data-m04-actions-header]');
    if (profile.key === 'encargado' && facts.station_template_admin) {
      expect((await actionsHeader.getAttribute('aria-label'))?.trim()).toBe('Acciones');
      await expect(actionsHeader.locator('span.visually-hidden')).toHaveText('Acciones');
      const hiddenLabelGeometry = await actionsHeader.locator('span.visually-hidden').evaluate(element => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return { width: rect.width, height: rect.height, overflow: style.overflow, clip: style.clip };
      });
      expect(hiddenLabelGeometry.width).toBeLessThanOrEqual(1);
      expect(hiddenLabelGeometry.height).toBeLessThanOrEqual(1);
      expect(hiddenLabelGeometry.overflow).toBe('hidden');
      await expect(actionsHeader.locator('i.ti-dots-vertical[aria-hidden="true"]')).toHaveCount(1);
      await expect(actionsHeader).toHaveClass(/sorting_disabled/);

      const assistantRow = table.locator('tbody tr[data-template-key="LEGACY_IL1_090000_180000"]');
      await expect(assistantRow).toHaveCount(1);
      await assistantRow.locator('.dropdown.dropstart > a[data-bs-toggle="dropdown"]').click();
      const assistantEditAction = assistantRow.locator('[data-m04-edit-template]');
      await expect(assistantEditAction).toBeVisible();
      await assistantEditAction.click();
      const assistantEditModal = page.locator('#m04-edit-template-modal');
      await expect(assistantEditModal).toBeVisible();
      await expect(assistantEditModal.locator('#m04-edit-turn-name')).toHaveValue('ASISTENTE / INTENDENCIA');
      await page.screenshot({ path: path.join(evidenceRoot, `encargado-asistente-edit-${width}.png`), fullPage: true });
      await assistantEditModal.getByRole('button', { name: 'Cancelar' }).click();
      await expect(assistantEditModal).toBeHidden();

      // Interlomas already has this global template personalized. Exercise the
      // same Alpine click handler with its original display source without POSTing.
      await page.evaluate(() => {
        const root = document.querySelector('#m04-template-catalog');
        const row = document.querySelector('#m04-template-table tbody tr[data-template-key="LEGACY_IL1_090000_180000"]');
        const trigger = document.createElement('a');
        trigger.href = '#';
        trigger.dataset.m04CustomizeTemplate = JSON.stringify({
          key: 'LEGACY_IL1_090000_180000', version: 1,
          name: 'Legacy Interlomas — ASISTENTE / INTENDENCIA — 09:00–18:00', type: 'REGULAR',
          fields: { start_time: '09:00', end_time: '18:00', break_start: '', break_end: '' },
          segments: [{ ordinal: 1, tipo_segmento: 'WORK', hora_inicio_local: '09:00:00', hora_fin_local: '18:00:00', dia_offset_inicio: 0, dia_offset_fin: 0 }],
        });
        row.append(trigger);
        trigger.click();
        trigger.remove();
      });
      const assistantCustomizeModal = page.locator('#m04-create-template-modal');
      await expect(assistantCustomizeModal).toBeVisible();
      await expect(assistantCustomizeModal.locator('#m04-turn-name')).toHaveValue('ASISTENTE / INTENDENCIA');
      await page.screenshot({ path: path.join(evidenceRoot, `encargado-asistente-personalizar-${width}.png`), fullPage: true });
      await assistantCustomizeModal.getByRole('button', { name: 'Cancelar' }).click();
      await expect(assistantCustomizeModal).toBeHidden();

      const numericGlobalRow = table.locator('tbody tr[data-template-key="LEGACY_IL1_060000_140000"]');
      await expect(numericGlobalRow).toContainText('TURNO 1 / ENCARGADO 1');
      await numericGlobalRow.locator('.dropdown.dropstart > a[data-bs-toggle="dropdown"]').click();
      const customizeAction = numericGlobalRow.locator('[data-m04-customize-template]');
      await expect(customizeAction).toBeVisible();
      await customizeAction.click();
      const customizeModal = page.locator('#m04-create-template-modal');
      await expect(customizeModal).toBeVisible();
      await expect(customizeModal.locator('#m04-turn-name')).toHaveValue('TURNO 1 / ENCARGADO 1');
      await expect(customizeModal.locator('#m04-turn-name')).not.toHaveValue(/Legacy|06:00|14:00/);
      await page.screenshot({ path: path.join(evidenceRoot, `encargado-personalizar-${width}.png`), fullPage: true });
      await customizeModal.getByRole('button', { name: 'Cancelar' }).click();
      await expect(customizeModal).toBeHidden();
      const localRow = table.locator('tbody tr[data-template-key="LOC_1_T_4_14002200"]');
      await expect(localRow).toHaveCount(1);
      await localRow.locator('.dropdown.dropstart > a[data-bs-toggle="dropdown"]').click();
      const editAction = localRow.locator('[data-m04-edit-template]');
      await expect(editAction).toBeVisible();
      await editAction.click();
      const editModal = page.locator('#m04-edit-template-modal');
      await expect(editModal).toBeVisible();
      await expect(editModal.locator('#m04-edit-turn-name')).toHaveValue('Turno 4 · Edición QA 20261104');
      await page.screenshot({ path: path.join(evidenceRoot, `encargado-editar-${width}.png`), fullPage: true });
      await editModal.getByRole('button', { name: 'Cancelar' }).click();
      await expect(editModal).toBeHidden();
    } else {
      await expect(actionsHeader).toHaveCount(0);
      await expect(table.locator('tbody tr .dropdown.dropstart')).toHaveCount(0);
      await expect(page.locator('#m04-btn-new-template')).toHaveCount(0);
      await page.screenshot({ path: path.join(evidenceRoot, `direccion-${width}.png`), fullPage: true });
    }

    const search = page.locator('#m04-template-table_filter input');
    await search.fill('ASISTENTE / INTENDENCIA');
    await expect.poll(async () => table.locator('tbody tr').count()).toBeGreaterThan(0);
    const searchResults = await table.locator('tbody tr th[scope="row"]').allTextContents();
    expect(searchResults.every(name => name.trim() === 'ASISTENTE / INTENDENCIA')).toBe(true);
    await search.fill('');
    await expect(table.locator('tbody tr').first()).toBeVisible();
  }

  const pageLength = page.locator('#m04-template-table_length select');
  const totalRows = await page.locator('#m04-template-table tbody tr').count();
  expect(totalRows).toBeGreaterThan(10);
  await pageLength.selectOption('10');
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(10);
  await page.locator('#m04-template-table_next').click();
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(totalRows - 10);
  await page.locator('#m04-template-table_previous').click();
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(10);
  await pageLength.selectOption('25');
  await expect(page.locator('#m04-template-table tbody tr')).toHaveCount(totalRows);

  expect(writes).toEqual([]);
  expect(pageErrors).toEqual([]);
  testInfo.annotations.push({ type: 'screenshots', description: evidenceRoot });
});
