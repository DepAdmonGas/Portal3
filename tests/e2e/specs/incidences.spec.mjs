import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { installLocalOriginGuard } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const incidencePath = '/departamento-operativo/recursos-humanos/checador/incidencias';
const screenshotRoot = path.resolve(process.env.PORTAL3_M02_AUDIT_SCREENSHOTS || candidateScreenshotsRoot);

async function selectInterlomasIfNeeded(page, facts) {
  const selector = page.locator('#module-station-selector-checador');
  if (!await selector.count()) return;

  const allowedNames = facts.localities.map((locality) => String(locality.name));
  const optionTexts = await selector.locator('option').allTextContents();
  for (const optionText of optionTexts.map((value) => value.trim()).filter((value) => value && !/^Selecciona una estación o departamento/i.test(value))) {
    expect(allowedNames.some((name) => optionText.includes(name)), `Out-of-scope locality option: ${optionText}`).toBe(true);
  }

  const interlomas = selector.locator('option').filter({ hasText: /^Interlomas(?:\s|$)/i });
  await expect(interlomas).toHaveCount(1);
  const value = await interlomas.getAttribute('value');
  expect(value).toBeTruthy();
  if (await selector.inputValue() !== value) {
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      selector.selectOption(value),
    ]);
  }
}

test('Incidencias V2: lectura, filtros, detalle e historial QA local sin escrituras', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión autorizada para ${testInfo.project.name}`);

  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  expect(facts.incidence_read).toBe(true);
  expect(Boolean(facts.incidence_create)).toBe(profile.key === 'encargado');
  ensurePrivateDirectory(screenshotRoot);

  const pageErrors = [];
  const consoleErrors = [];
  const localHttpErrors = [];
  const unexpectedWrites = [];
  const blockedExternalOrigins = [];
  await installLocalOriginGuard(page, blockedExternalOrigins);
  page.on('pageerror', (error) => pageErrors.push(error.message));
  page.on('console', (message) => {
    if (message.type() !== 'error' || message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) return;
    consoleErrors.push(message.text());
  });
  page.on('requestfailed', (request) => {
    if (new URL(request.url()).hostname === 'portal3.test') {
      localHttpErrors.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`);
    }
  });
  page.on('response', (response) => {
    if (response.status() >= 400 && new URL(response.url()).hostname === 'portal3.test') {
      localHttpErrors.push(`${response.status()} ${response.request().method()} ${response.url()}`);
    }
  });
  page.on('request', (request) => {
    const pathname = new URL(request.url()).pathname;
    if (['GET', 'HEAD', 'OPTIONS'].includes(request.method()) || pathname === '/api/module-context/set') return;
    unexpectedWrites.push(`${request.method()} ${pathname}`);
  });

  const response = await page.goto(`${incidencePath}?date=2026-10-28`, { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(200);
  await expect(page.locator('.fixed-profile .john-title span')).toHaveText(profile.rolePattern);
  await expect(page.getByRole('heading', { name: 'Incidencias', exact: true })).toHaveCount(1);
  await expect(page.getByRole('heading', { name: 'Incidencias e historial', exact: true })).toHaveCount(0);
  await expect(page.locator('[aria-label="breadcrumb"]')).toBeVisible();
  await selectInterlomasIfNeeded(page, facts);
  await expect(page.locator('#container')).toHaveAttribute('data-applied-date', '2026-10-28');
  await expect(page.getByRole('button', { name: 'Nueva incidencia' })).toHaveCount(facts.incidence_create ? 1 : 0);
  if (!facts.incidence_create) {
    await expect(page.locator('form[action$="/incidencias/crear"]')).toHaveCount(0);
  }

  const searchButton = page.locator('[data-bs-target="#checador-incidence-search-modal"]');
  await expect(searchButton).toBeVisible();
  const tables = page.locator('.table-responsive table');
  const qaRows = page.locator('.table-responsive table tbody tr');
  await expect(qaRows.getByRole('rowheader', { name: 'Desviación de horario' })).toHaveCount(1);
  await expect(qaRows.getByRole('rowheader', { name: 'Segmento incompleto' })).toHaveCount(1);

  const headingSnapshot = await page.locator('h1,h2,h3,h4,h5').evaluateAll((nodes) => nodes
    .filter((node) => node.getClientRects().length > 0)
    .map((node) => ({ level: node.tagName, text: (node.innerText || '').trim() })));
  testInfo.annotations.push({ type: 'visible-headings', description: JSON.stringify(headingSnapshot) });

  const responsiveMetrics = [];
  for (const width of [1440, 1024, 768, 375]) {
    await page.setViewportSize({ width, height: 1000 });
    await expect(page.locator('#main-wrapper')).toBeVisible();
    await expect(searchButton).toBeVisible();
    await expect(qaRows.first()).toBeVisible();
    responsiveMetrics.push(await page.evaluate(() => {
      const wrapper = document.querySelector('.table-responsive');
      return {
        viewport: window.innerWidth,
        document: document.documentElement.scrollWidth,
        tableWrapperClient: wrapper?.clientWidth ?? null,
        tableWrapperScroll: wrapper?.scrollWidth ?? null,
      };
    }));
    await page.screenshot({ path: path.join(screenshotRoot, `${profile.key}-incidences-2026-10-28-${width}.png`), fullPage: true });
  }

  // Inspect the GET search form and its scoped collaborator choices.
  await page.setViewportSize({ width: 1440, height: 1000 });
  await searchButton.click();
  const searchModal = page.locator('#checador-incidence-search-modal');
  await expect(searchModal).toBeVisible();
  const formMethod = await searchModal.locator('form').getAttribute('method');
  expect((formMethod || '').toLowerCase()).toBe('get');
  await expect(searchModal.locator('#checador-incidence-filter-date')).toHaveValue('2026-10-28');
  const workerSelect = searchModal.locator('#checador-incidence-filter-worker');
  await expect(workerSelect.locator('option')).toHaveCount(47);
  const workerOptionValues = await workerSelect.locator('option').evaluateAll((nodes) => nodes.map((node) => node.value));
  expect(workerOptionValues).toContain('1007');
  expect(workerOptionValues).toContain('1008');
  await page.screenshot({ path: path.join(screenshotRoot, `${profile.key}-incidences-search-modal-1440.png`), fullPage: true });
  await page.setViewportSize({ width: 375, height: 1000 });
  await page.screenshot({ path: path.join(screenshotRoot, `${profile.key}-incidences-search-modal-375.png`), fullPage: true });

  // A QA fixture row is read-only evidence. Opening detail/history is safe; no form is submitted.
  await workerSelect.selectOption('1007');
  const submitSearch = searchModal.locator('button[type="submit"]');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    submitSearch.click(),
  ]);
  expect(new URL(page.url()).searchParams.get('date')).toBe('2026-10-28');
  expect(new URL(page.url()).searchParams.get('worker_id')).toBe('1007');
  await expect(page.locator('#container')).toHaveAttribute('data-applied-worker', '1007');
  await expect(qaRows.getByRole('rowheader', { name: 'Desviación de horario' })).toHaveCount(1);
  await expect(page.getByText('Segmento incompleto', { exact: true })).toHaveCount(0);
  await expect(tables).toHaveCount(1);
  await expect(qaRows).toHaveCount(1);
  const detailButton = qaRows.first().getByRole('button', { name: 'Ver detalle' });
  await page.setViewportSize({ width: 375, height: 812 });
  const actionTableWrapper = page.locator('.table-responsive').first();
  const mobileTableMetrics = await actionTableWrapper.evaluate((wrapper) => {
    const before = { clientWidth: wrapper.clientWidth, scrollWidth: wrapper.scrollWidth };
    wrapper.scrollLeft = wrapper.scrollWidth;
    const container = wrapper.getBoundingClientRect();
    const header = (name) => {
      const node = Array.from(wrapper.querySelectorAll('thead th')).find((item) => item.textContent.trim() === name);
      if (!node) return null;
      const rect = node.getBoundingClientRect();
      return { left: rect.left, right: rect.right, visible: rect.left >= container.left - 1 && rect.right <= container.right + 1 };
    };
    const button = wrapper.querySelector('tbody button[aria-label="Ver detalle"], tbody button');
    const buttonRect = button?.getBoundingClientRect();
    return {
      ...before,
      maxScrollLeft: wrapper.scrollWidth - wrapper.clientWidth,
      reviewHeader: header('Revisión'),
      detailHeader: header('Detalle e historial'),
      detailButtonVisible: Boolean(buttonRect && buttonRect.left >= container.left - 1 && buttonRect.right <= container.right + 1),
    };
  });
  expect(mobileTableMetrics.scrollWidth).toBeGreaterThan(mobileTableMetrics.clientWidth);
  expect(mobileTableMetrics.maxScrollLeft).toBeGreaterThan(0);
  expect(mobileTableMetrics.reviewHeader?.visible).toBe(true);
  expect(mobileTableMetrics.detailHeader?.visible).toBe(true);
  expect(mobileTableMetrics.detailButtonVisible).toBe(true);
  await page.screenshot({ path: path.join(screenshotRoot, `${profile.key}-incidences-table-actions-375.png`), fullPage: false });
  await expect(detailButton).toBeVisible();
  const actionCellStyles = await detailButton.evaluate((button) => {
    const row = button.closest('tr');
    const cells = row ? Array.from(row.children) : [];
    return {
      actionCell: button.closest('td')?.className ?? '',
      actionTextAlign: button.closest('td') ? getComputedStyle(button.closest('td')).textAlign : '',
      dataTextAlign: cells[0] ? getComputedStyle(cells[0]).textAlign : '',
      buttonClasses: button.className,
      dropdownControls: row?.querySelectorAll('.dropdown-toggle').length ?? 0,
    };
  });
  testInfo.annotations.push({ type: 'table-presentation', description: JSON.stringify(actionCellStyles) });
  const detailTarget = await detailButton.getAttribute('data-bs-target');
  expect(detailTarget).toMatch(/^#incidence-history-/);
  expect(await page.locator(detailTarget).count()).toBe(1);
  await detailButton.focus();
  await page.keyboard.press('Enter');
  const detailModal = page.locator(detailTarget);
  await expect(detailModal).toBeVisible();
  expect(await detailModal.evaluate((modal) => Boolean(modal.closest('td') || modal.closest('table')))).toBe(false);
  const detailDialogBounds = await detailModal.locator('.modal-dialog').evaluate((dialog) => {
    const bounds = dialog.getBoundingClientRect();
    return { left: bounds.left, right: bounds.right, viewport: window.innerWidth };
  });
  expect(detailDialogBounds.left).toBeGreaterThanOrEqual(0);
  expect(detailDialogBounds.right).toBeLessThanOrEqual(detailDialogBounds.viewport);
  const detailAlignment = await detailModal.locator('.modal-body').evaluate((body) => ({
    body: getComputedStyle(body).textAlign,
    card: body.querySelector('.border') ? getComputedStyle(body.querySelector('.border')).textAlign : '',
    value: body.querySelector('strong') ? getComputedStyle(body.querySelector('strong')).textAlign : '',
    direction: getComputedStyle(body).direction,
  }));
  const isLeftAligned = (value) => value === 'left' || value === 'start' && detailAlignment.direction === 'ltr';
  expect(isLeftAligned(detailAlignment.body)).toBe(true);
  expect(isLeftAligned(detailAlignment.card)).toBe(true);
  expect(isLeftAligned(detailAlignment.value)).toBe(true);
  testInfo.annotations.push({ type: 'detail-modal-alignment', description: JSON.stringify(detailAlignment) });
  await expect(detailModal).toContainText('Colaborador');
  await expect(detailModal).toContainText('Diego Arriaga');
  await expect(detailModal).toContainText('Descripción');
  const revisionHistoryAvailable = await detailModal.getByRole('heading', { name: 'Historial de revisión' }).count() > 0;
  testInfo.annotations.push({ type: 'qa-detail', description: `Diego detail opened; revision history present=${revisionHistoryAvailable}` });
  await expect(detailModal.locator('[data-bs-dismiss="modal"]').first()).toBeVisible();
  await page.screenshot({ path: path.join(screenshotRoot, `${profile.key}-incidences-detail-375.png`), fullPage: true });
  await page.waitForFunction((selector) => {
    const modal = document.querySelector(selector);
    return Boolean(modal && modal.contains(document.activeElement));
  }, detailTarget, { timeout: 3000 });
  await page.keyboard.press('Escape');
  await expect(detailModal).toBeHidden();
  await detailButton.click();
  await expect(detailModal).toBeVisible();
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.screenshot({ path: path.join(screenshotRoot, `${profile.key}-incidences-detail-1440.png`), fullPage: true });
  await page.setViewportSize({ width: 375, height: 812 });
  await expect(detailModal.locator('[data-bs-dismiss="modal"]').first()).toBeVisible();
  await page.screenshot({ path: path.join(screenshotRoot, `${profile.key}-incidences-detail-375.png`), fullPage: true });
  await detailModal.locator('[data-bs-dismiss="modal"]').first().click();

  // Alicia has an existing local QA-store record with a version history; open it read-only.
  await page.goto(incidencePath + '?date=2026-10-28&worker_id=1004', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#container')).toHaveAttribute('data-applied-worker', '1004');
  await expect(qaRows.getByRole('rowheader', { name: 'Justificación' })).toHaveCount(1);
  const revisionButton = qaRows.first().getByRole('button', { name: 'Ver detalle' });
  const revisionTarget = await revisionButton.getAttribute('data-bs-target');
  await revisionButton.click();
  const revisionModal = page.locator(revisionTarget);
  await expect(revisionModal).toBeVisible();
  expect(await revisionModal.evaluate((modal) => Boolean(modal.closest('td') || modal.closest('table')))).toBe(false);
  await expect(revisionModal.getByRole('heading', { name: 'Historial de revisión' })).toBeVisible();
  await expect(revisionModal).toContainText('Revisión 1');
  const modalAlignment = await revisionModal.locator('.modal-body').evaluate((body) => ({
    body: getComputedStyle(body).textAlign,
    card: body.querySelector('.border') ? getComputedStyle(body.querySelector('.border')).textAlign : '',
    value: body.querySelector('strong') ? getComputedStyle(body.querySelector('strong')).textAlign : '',
    direction: getComputedStyle(body).direction,
  }));
  const historyIsLeftAligned = (value) => value === 'left' || value === 'start' && modalAlignment.direction === 'ltr';
  expect(historyIsLeftAligned(modalAlignment.body)).toBe(true);
  expect(historyIsLeftAligned(modalAlignment.card)).toBe(true);
  expect(historyIsLeftAligned(modalAlignment.value)).toBe(true);
  testInfo.annotations.push({ type: 'modal-content-alignment', description: JSON.stringify(modalAlignment) });
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.screenshot({ path: path.join(screenshotRoot, profile.key + '-incidences-history-1440.png'), fullPage: true });
  await page.setViewportSize({ width: 375, height: 812 });
  await page.screenshot({ path: path.join(screenshotRoot, profile.key + '-incidences-history-375.png'), fullPage: true });
  const lastRevision = revisionModal.locator('.modal-body ol li').last();
  await expect(lastRevision).toBeVisible();
  await lastRevision.scrollIntoViewIfNeeded();
  const historyDialogBounds = await revisionModal.locator('.modal-dialog').evaluate((dialog) => {
    const bounds = dialog.getBoundingClientRect();
    return { left: bounds.left, right: bounds.right, viewport: window.innerWidth };
  });
  expect(historyDialogBounds.left).toBeGreaterThanOrEqual(0);
  expect(historyDialogBounds.right).toBeLessThanOrEqual(historyDialogBounds.viewport);
  await page.screenshot({ path: path.join(screenshotRoot, profile.key + '-incidences-history-revision-375.png'), fullPage: true });
  await revisionModal.locator('[data-bs-dismiss="modal"]').first().click();

  if (facts.incidence_create) {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.getByRole('button', { name: 'Nueva incidencia' }).click();
    const createModal = page.locator('#checador-create-incidence-modal');
    await expect(createModal).toBeVisible();
    await expect(createModal.locator('form')).toHaveAttribute('method', 'post');
    await expect(createModal.locator('form')).toHaveAttribute('action', /\/incidencias\/crear$/);
    expect(await createModal.locator('select[name="worker_id"] option').count()).toBeGreaterThan(1);
    await page.screenshot({ path: path.join(screenshotRoot, profile.key + '-incidences-create-form-1440.png'), fullPage: true });
    await page.setViewportSize({ width: 375, height: 1000 });
    await page.screenshot({ path: path.join(screenshotRoot, profile.key + '-incidences-create-form-375.png'), fullPage: true });
    await createModal.locator('[data-bs-dismiss="modal"]').first().click();
  }

  // The authorized local QA creation may leave one read-only Bruno record in the private store.
  await page.goto(`${incidencePath}?date=2026-10-28&worker_id=1005`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#container')).toHaveAttribute('data-applied-worker', '1005');
  const brunoRowCount = await page.locator('.table-responsive table tbody tr').count();
  if (brunoRowCount === 0) {
    await expect(page.getByText('No hay incidencias registradas para esta búsqueda', { exact: true })).toBeVisible();
    await expect(page.locator('.table-responsive table')).toHaveCount(0);
  } else {
    const brunoQaRows = page.locator('.table-responsive table tbody tr')
      .filter({ has: page.locator('th[scope="row"]:has-text("Justificación")') });
    await expect(brunoQaRows).toHaveCount(1);
    await expect(page.locator('.table-responsive table tbody tr')).toHaveCount(1);
    const brunoQaDetailTarget = await brunoQaRows.getByRole('button', { name: 'Ver detalle' }).getAttribute('data-bs-target');
    expect(brunoQaDetailTarget).toMatch(/^#incidence-history-/);
    await brunoQaRows.getByRole('button', { name: 'Ver detalle' }).click();
    const brunoQaDetail = page.locator(brunoQaDetailTarget);
    await expect(brunoQaDetail).toBeVisible();
    await expect(brunoQaDetail).toContainText(/QA LOCAL ONLY - NO OFICIAL - M02QA-20261029-/);
    await expect(brunoQaDetail).toContainText(/Evidencia:\s*M02QA-20261029-/);
    await expect(brunoQaDetail.getByRole('heading', { name: 'Historial de revisión' })).toBeVisible();
    await expect(brunoQaDetail).toContainText('Revisión 1');
    await expect(brunoQaDetail).toContainText('Usuario ID 33');
  }

  for (const metric of responsiveMetrics) {
    expect(metric.document, 'Document overflows at ' + metric.viewport + 'px').toBeLessThanOrEqual(metric.viewport);
  }
  testInfo.annotations.push({ type: 'responsive-metrics', description: JSON.stringify(responsiveMetrics) });

  expect(unexpectedWrites, `Unexpected writes during Incidences UAT: ${unexpectedWrites.join('\n')}`).toEqual([]);
  expect(pageErrors, `JavaScript errors: ${pageErrors.join('\n')}`).toEqual([]);
  expect(consoleErrors, `Console errors: ${consoleErrors.join('\n')}`).toEqual([]);
  expect(localHttpErrors, `Local HTTP errors: ${localHttpErrors.join('\n')}`).toEqual([]);
  testInfo.annotations.push({ type: 'candidate-screenshots', description: screenshotRoot });
  testInfo.annotations.push({
    type: 'external-network',
    description: `Non-local origins blocked; observed: ${[...new Set(blockedExternalOrigins)].join(', ') || 'none'}`,
  });
});
