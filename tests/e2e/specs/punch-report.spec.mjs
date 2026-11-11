import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, qaProfiles, baseURL } from '../paths.mjs';
import { installLocalOriginGuard } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const reportPath = '/departamento-operativo/recursos-humanos/checador/reporte';
const dataPath = `${reportPath}/data`;
const oct28 = '2026-10-28';
const oct29 = '2026-10-29';
let collaboratorAlias = '';

function contrastRatio(foreground, background) {
  const luminance = (color) => {
    const channels = color.match(/[\d.]+/g)?.slice(0, 3).map(Number) ?? [];
    if (channels.length !== 3) return 0;
    const linear = channels.map((channel) => {
      const value = channel / 255;
      return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
  };
  const first = luminance(foreground);
  const second = luminance(background);
  return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}

function isDataTableResponse(response, predicate = () => true) {
  const request = response.request();
  if (request.method() !== 'GET') return false;
  const url = new URL(response.url());
  return url.origin === baseURL && url.pathname === dataPath && predicate(url.searchParams);
}

async function waitForCount(page, expectedCount, searchValue) {
  const responsePromise = page.waitForResponse((response) => isDataTableResponse(response, (params) =>
    params.get('search[value]') === searchValue));
  const input = page.locator('#checador-punch-table_filter input, #checador-punch-table_wrapper .dt-search input').first();
  await input.fill(searchValue);
  const response = await responsePromise;
  expect(response.status()).toBe(200);
  const payload = await response.json();
  expect(payload.recordsFiltered).toBe(expectedCount);
  const lengthSelect = page.locator('#checador-punch-table_length select, #checador-punch-table_wrapper .dt-length select').first();
  const pageLength = Number(await lengthSelect.inputValue());
  await expect(page.locator('#checador-punch-table tbody tr')).toHaveCount(Math.min(expectedCount, pageLength));
  return payload;
}

async function applyFilters(page, fromDate, toDate, workerId = '', captureMethod = '', processingStatus = '') {
  const workerSelect = page.locator('#punch-report-worker');
  if (workerId) await selectWorkerByName(page, workerId);
  else await workerSelect.selectOption('');
  const selectedWorkerId = await workerSelect.inputValue();
  const dataResponsePromise = page.waitForResponse((response) => isDataTableResponse(response, (params) =>
    params.get('from_date') === fromDate && params.get('to_date') === toDate && params.get('worker_id') === selectedWorkerId
      && params.get('capture_method') === captureMethod && params.get('processing_status') === processingStatus));
  const modal = page.locator('#punch-report-filter-modal');
  await page.locator('[data-bs-target="#punch-report-filter-modal"]').click();
  await expect(modal).toBeVisible();
  await modal.locator('#from_date').fill(fromDate);
  await modal.locator('#to_date').fill(toDate);
  await modal.locator('#capture_method').selectOption(captureMethod);
  await modal.locator('#processing_status').selectOption(processingStatus);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    modal.getByRole('button', { name: 'Aplicar filtros' }).click(),
  ]);
  const response = await dataResponsePromise;
  expect(response.status()).toBe(200);
  return response.json();
}

async function selectWorkerByName(page, fullName) {
  await page.locator('#select2-punch-report-worker-container').click();
  const search = page.locator('.select2-container--open .select2-search__field');
  await search.fill(fullName);
  const option = page.locator('.select2-container--open .select2-results__option').filter({ hasText: fullName }).first();
  await expect(option).toBeVisible();
  await option.click();
}

test('M02 Reporte de marcaciones: filtros, CSV, detalle y alcance de solo lectura', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA autorizada para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  expect(facts.localities.some((locality) => locality.id === 1 && locality.name === 'Interlomas')).toBe(true);
  expect(['encargado', 'direccion-operaciones']).toContain(profile.key);
  fs.mkdirSync(candidateScreenshotsRoot, { recursive: true, mode: 0o700 });
  fs.chmodSync(candidateScreenshotsRoot, 0o700);

  const pageErrors = [];
  const consoleErrors = [];
  const localHttpErrors = [];
  const writes = [];
  const contextSwitchRequests = [];
  const blockedExternalOrigins = [];
  const tableRequests = [];
  await installLocalOriginGuard(page, blockedExternalOrigins);
  page.on('pageerror', (error) => pageErrors.push(error.message));
  page.on('console', (message) => {
    if (message.type() !== 'error' || message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) return;
    consoleErrors.push(message.text());
  });
  page.on('requestfailed', (request) => {
    const url = new URL(request.url());
    if (url.pathname === `${reportPath}/colaboradores` && request.failure()?.errorText === 'net::ERR_ABORTED') return;
    if (url.hostname === 'portal3.test' && !(url.pathname === reportPath && url.searchParams.get('export') === 'csv')) {
      localHttpErrors.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`);
    }
  });
  page.on('response', (response) => {
    if (response.status() >= 400 && new URL(response.url()).hostname === 'portal3.test') {
      localHttpErrors.push(`${response.status()} ${response.request().method()} ${response.url()}`);
    }
  });
  page.on('request', (request) => {
    const url = new URL(request.url());
    if (url.pathname === '/api/module-context/set') contextSwitchRequests.push(`${request.method()} ${url.pathname}`);
    if (url.pathname === dataPath) {
      tableRequests.push({ method: request.method(), params: Object.fromEntries(url.searchParams.entries()) });
    }
    if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) writes.push(`${request.method()} ${url.pathname}`);
  });

  const firstDataResponse = page.waitForResponse((response) => isDataTableResponse(response, (params) =>
    params.get('from_date') === oct28 && params.get('to_date') === oct28));
  const pageResponse = await page.goto(`${reportPath}?from_date=${oct28}&to_date=${oct28}`, { waitUntil: 'domcontentloaded' });
  expect(pageResponse?.status()).toBe(200);
  let firstPayload = await (await firstDataResponse).json();
  const stationSelector = page.locator('#module-station-selector-checador');
  if (await stationSelector.count()) {
    const selectedText = await stationSelector.locator('option:checked').innerText();
    if (!selectedText.includes('Interlomas')) {
      const interlomasOption = stationSelector.locator('option').filter({ hasText: 'Interlomas' }).first();
      await expect(interlomasOption).toHaveCount(1);
      const nextDataResponse = page.waitForResponse((response) => isDataTableResponse(response, (params) =>
        params.get('from_date') === oct28 && params.get('to_date') === oct28));
      const contextResponsePromise = page.waitForResponse((response) => {
        const url = new URL(response.url());
        return url.origin === baseURL && url.pathname === '/api/module-context/set' && response.request().method() === 'POST';
      });
      await stationSelector.selectOption(await interlomasOption.getAttribute('value') || '');
      const contextResponse = await contextResponsePromise;
      expect(contextResponse.status()).toBe(200);
      firstPayload = await (await nextDataResponse).json();
    }
  }
  expect(firstPayload.recordsTotal).toBe(11);
  expect(firstPayload.recordsFiltered).toBe(11);
  const qaWorkerEvent = firstPayload.data.find((event) => String(event.collaborator || '').endsWith('ID interno 1005'));
  expect(qaWorkerEvent, 'readback must include the existing QA worker identity needed for the authorized name search').toBeTruthy();
  collaboratorAlias = qaWorkerEvent.collaborator.replace(/\s*·\s*ID interno\s+\d+$/u, '');
  expect(collaboratorAlias.trim()).not.toBe('');
  await expect(page.locator('.fixed-profile .john-title span')).toHaveText(profile.rolePattern);
  await expect(page.getByRole('heading', { name: 'Reporte de marcaciones', exact: true })).toBeVisible();
  await expect(page.locator('[aria-label="breadcrumb"]')).toBeVisible();
  await expect(page.locator('#checador-punch-table tbody tr')).toHaveCount(11);
  await expect(page.locator('#checador-punch-table thead th').nth(1)).toHaveText('Fecha (UTC)');
  await expect(page.locator('#checador-punch-table thead th').nth(2)).toHaveText('Hora (UTC)');
  await expect(page.locator('#checador-punch-table tbody tr').first().locator('td').nth(2)).toContainText(/\d{2}:\d{2} (am|pm)/u);
  await expect(page.getByRole('heading', { name: 'Marcaciones registradas', exact: true })).toBeVisible();
  await expect(page.locator('.container-fluid.mt-4.mb-5 a', { hasText: 'Configuración' })).toHaveCount(0);
  await expect(page.getByText('Registros originales', { exact: true })).toHaveCount(0);
  const periodSelect = page.locator('#punch-report-period');
  await periodSelect.selectOption('custom');
  await page.getByRole('button', { name: 'Consultar' }).click();
  await expect(page.locator('#punch-report-filter-modal')).toBeVisible();
  await page.locator('#punch-report-filter-modal').getByRole('button', { name: 'Cancelar' }).click();
  await periodSelect.selectOption('specific');
  await expect(page.locator('#punch-report-month')).toBeVisible();
  await expect(page.locator('#punch-report-year')).toBeVisible();
  await page.locator('#punch-report-year').fill('2026');
  await page.locator('#punch-report-month').selectOption('10');
  const monthNavigation = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: 'Consultar' }).click();
  await monthNavigation;
  expect(new URL(page.url()).searchParams.get('from_date')).toBe('2026-10-01');
  expect(new URL(page.url()).searchParams.get('to_date')).toBe('2026-10-31');
  await page.goto(`${reportPath}?from_date=${oct28}&to_date=${oct28}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#checador-punch-table tbody tr')).toHaveCount(11);
  await expect(page.locator('#punch-report-worker')).toBeVisible();
  await expect(page.locator('#punch-report-worker option').first()).toHaveText('Todos los colaboradores');
  await expect(page.locator('#select2-punch-report-worker-container')).toContainText('Todos los colaboradores');
  const initialRosterResponsePromise = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.origin === baseURL && url.pathname === `${reportPath}/colaboradores`
      && (!url.searchParams.has('term') || url.searchParams.get('term') === '');
  });
  await page.locator('#select2-punch-report-worker-container').click();
  const initialRosterResponse = await initialRosterResponsePromise;
  expect(initialRosterResponse.status()).toBe(200);
  const initialRosterPayload = await initialRosterResponse.json();
  expect(initialRosterPayload.results.length).toBeGreaterThan(0);
  expect(initialRosterPayload.results.every((worker) => /^\d+$/u.test(String(worker.id))
    && String(worker.text).trim() !== '' && !/\bID\s+interno\b/u.test(worker.text))).toBe(true);
  await expect(page.locator('.select2-container--open .select2-results__option').first()).toBeVisible();
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-m02-worker-initial-list-1440.png`), fullPage: true });
  const workerTerms = collaboratorAlias.trim().split(/\s+/u);
  const firstNameSearchPromise = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.origin === baseURL && url.pathname === `${reportPath}/colaboradores`
      && url.searchParams.get('term') === workerTerms[0];
  });
  const workerSearchField = page.locator('.select2-container--open .select2-search__field');
  await workerSearchField.fill(workerTerms[0]);
  const firstNameSearch = await firstNameSearchPromise;
  expect(firstNameSearch.status()).toBe(200);
  expect((await firstNameSearch.json()).results.length).toBeGreaterThan(0);
  await expect(page.locator('.select2-container--open .select2-results__option').first()).toBeVisible();
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-m02-worker-search-open-1440.png`), fullPage: true });
  const surname = workerTerms[workerTerms.length - 1];
  const surnameSearchPromise = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.origin === baseURL && url.pathname === `${reportPath}/colaboradores`
      && url.searchParams.get('term') === surname;
  });
  await workerSearchField.fill(surname);
  const surnameSearch = await surnameSearchPromise;
  expect(surnameSearch.status()).toBe(200);
  const surnamePayload = await surnameSearch.json();
  expect(surnamePayload.results.some((worker) => worker.text === collaboratorAlias)).toBe(true);
  await expect(page.locator('.select2-container--open .select2-results__option').filter({ hasText: collaboratorAlias })).toBeVisible();
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-m02-worker-search-results-1440.png`), fullPage: true });
  await page.locator('.select2-container--open .select2-results__option').filter({ hasText: collaboratorAlias }).first().click();
  await expect(page.locator('#select2-punch-report-worker-container')).toContainText(collaboratorAlias);
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-m02-worker-selected-1440.png`), fullPage: true });
  const firstEvent = firstPayload.data[0];
  const captureMethod = await page.locator('#capture_method option').filter({ hasText: firstEvent.capture_method }).evaluate((option) => option.value);
  const processingStatus = await page.locator('#processing_status option').filter({ hasText: firstEvent.processing_status }).evaluate((option) => option.value);

  if (await stationSelector.count()) {
    const optionNames = await stationSelector.locator('option').allTextContents();
    for (const name of optionNames.map((item) => item.trim()).filter((item) => item && !/^Selecciona una estación/i.test(item))) {
      expect(facts.localities.some((locality) => name.includes(locality.name)), `Selector contains out-of-scope locality: ${name}`).toBe(true);
    }
    const selectedOption = stationSelector.locator('option:checked');
    await expect(selectedOption).toContainText('Interlomas');
  }
  await expect(page.locator('#punch-report-csv-form button[type="submit"]')).toBeVisible();
  const stationCells = await page.locator('#checador-punch-table tbody tr td:first-child').allTextContents();
  expect(stationCells.length).toBe(11);
  expect(stationCells.every((name) => name.trim() === 'Interlomas')).toBe(true);

  const reportNote = page.locator('section[aria-labelledby="punch-report-results-title"] .card-body > p').first();
  await expect(reportNote).toContainText('integridad no está verificada');
  await expect(page.locator('.card-header.bg-primary')).toBeVisible();

  const firstRowStyles = await page.locator('#checador-punch-table tbody tr').first().evaluate((row) => ({
    dataCell: getComputedStyle(row.children[3]).textAlign,
    actionCell: getComputedStyle(row.lastElementChild).textAlign,
    actionClasses: row.lastElementChild.className,
  }));
  expect(['left', 'start']).toContain(firstRowStyles.dataCell);
  expect(firstRowStyles.actionCell).toBe('center');
  expect(firstRowStyles.actionClasses).toContain('table-actions-cell');

  await waitForCount(page, 4, '1005');
  await waitForCount(page, 4, collaboratorAlias);
  await expect(page.locator('#checador-punch-table tbody tr').first()).toContainText(collaboratorAlias);
  await page.locator('#punch-report-worker').selectOption('');
  await waitForCount(page, 11, 'Interlomas');
  expect(await page.locator('#checador-punch-table tbody tr td:first-child').allTextContents())
    .toEqual(Array(11).fill('Interlomas'));
  await waitForCount(page, 11, '');

  await applyFilters(page, oct28, oct28, '', captureMethod, '');
  expect((await page.locator('#checador-punch-table_info').innerText()).trim()).not.toBe('');
  await applyFilters(page, oct28, oct28, '', '', processingStatus);
  await applyFilters(page, oct28, oct28, '', captureMethod, processingStatus);
  await expect(page.locator('#checador-punch-table tbody tr')).not.toHaveCount(0);
  await applyFilters(page, oct28, oct28, '');

  await applyFilters(page, '2026-01-01', '2026-01-01', collaboratorAlias);
  await expect(page.locator('#punch-report-empty-state')).toBeVisible();
  await expect(page.locator('#punch-report-empty-state')).toContainText('Sin marcaciones en el periodo consultado');
  await applyFilters(page, oct28, oct28, '');

  const lengthSelect = page.locator('#checador-punch-table_length select, #checador-punch-table_wrapper .dt-length select').first();
  const paginationResponse = page.waitForResponse((response) => isDataTableResponse(response, (params) => params.get('length') === '10'));
  await lengthSelect.selectOption('10');
  expect((await paginationResponse).status()).toBe(200);
  await expect(page.locator('#checador-punch-table tbody tr')).toHaveCount(10);
  await expect(page.locator('#checador-punch-table_info')).toContainText(/1\s+a\s+10\s+de\s+11/);
  const secondPageResponse = page.waitForResponse((response) => isDataTableResponse(response, (params) => params.get('start') === '10'));
  await page.locator('#checador-punch-table_next, #checador-punch-table_wrapper .dt-paging-button.next').first().click();
  expect((await secondPageResponse).status()).toBe(200);
  await expect(page.locator('#checador-punch-table tbody tr')).toHaveCount(1);
  await expect(page.locator('#checador-punch-table_info')).toContainText(/11\s+a\s+11\s+de\s+11/);

  await page.setViewportSize({ width: 1440, height: 1000 });
  await waitForCount(page, 4, collaboratorAlias);
  const aliasRow = page.locator('#checador-punch-table tbody tr').filter({ hasText: collaboratorAlias }).first();
  await expect(aliasRow).toBeVisible();
  const detailButton = aliasRow.getByRole('button', { name: 'Ver detalle' });
  await expect(detailButton).toHaveClass(/\bbtn\b.*\bbtn-sm\b.*\bbtn-outline-primary\b/);
  await expect(detailButton.locator('i.ti-eye')).toHaveCount(1);
  await detailButton.focus();
  await expect(detailButton).toBeFocused();
  await detailButton.press('Enter');
  const detailModal = page.locator('#checador-punch-detail-modal');
  await expect(detailModal).toBeVisible();
  await expect(detailModal.locator('#punch-detail-collaborator')).toContainText(collaboratorAlias);
  await expect(detailModal.locator('#punch-detail-station')).toHaveText('Interlomas');
  await expect(detailModal.locator('#punch-detail-event-uuid')).not.toHaveText('—');
  const modalAlignment = await detailModal.locator('.modal-body').evaluate((element) => getComputedStyle(element).textAlign);
  expect(['left', 'start']).toContain(modalAlignment);
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-m03-detail-1440.png`), fullPage: true });
  await detailModal.locator('[data-bs-dismiss="modal"]').first().click();
  await expect(detailModal).not.toHaveClass(/\bshow\b/);

  await waitForCount(page, 11, '');
  await applyFilters(page, oct28, oct28, collaboratorAlias);
  await expect(page.locator('#checador-punch-table tbody tr')).toHaveCount(4);
  expect(new URL(page.url()).searchParams.get('worker_id')).toBe('1005');
  expect(new URL(page.url()).searchParams.get('from_date')).toBe(oct28);

  await applyFilters(page, oct29, oct29, '');
  await expect(page.locator('#checador-punch-table tbody tr')).toHaveCount(1);
  expect(new URL(page.url()).searchParams.get('from_date')).toBe(oct29);
  expect(new URL(page.url()).searchParams.get('to_date')).toBe(oct29);
  const oct29RowText = await page.locator('#checador-punch-table tbody tr').first().innerText();
  expect(oct29RowText).toContain('Interlomas');

  const oct28AgainResponse = page.waitForResponse((response) => isDataTableResponse(response, (params) =>
    params.get('from_date') === oct28 && params.get('to_date') === oct28 && params.get('worker_id') === ''));
  await applyFilters(page, oct28, oct28, '');
  expect((await oct28AgainResponse).status()).toBe(200);
  await expect(page.locator('#checador-punch-table tbody tr')).toHaveCount(11);
  await waitForCount(page, 4, collaboratorAlias);

  const downloadResponsePromise = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.origin === baseURL && url.pathname === reportPath && url.searchParams.get('export') === 'csv';
  });
  const downloadPromise = page.waitForEvent('download');
  await page.locator('#punch-report-csv-form button[type="submit"]').click();
  const [download, downloadResponse] = await Promise.all([downloadPromise, downloadResponsePromise]);
  expect(downloadResponse.status()).toBe(200);
  expect(downloadResponse.headers()['content-type']).toContain('text/csv');
  const csvPath = testInfo.outputPath(`${profile.key}-m03-filtered.csv`);
  await download.saveAs(csvPath);
  const csv = fs.readFileSync(csvPath, 'utf8');
  expect(csv).toContain(collaboratorAlias);
  expect(csv).toContain('Interlomas');
  expect(csv).toMatch(/"Registros exportados",4/);
  expect(csv).toContain('Completa');
  const exportedEventUuids = csv.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/giu) ?? [];
  expect(exportedEventUuids).toHaveLength(4);
  expect(new Set(exportedEventUuids).size).toBe(4);

  await page.locator('[data-bs-target="#punch-report-filter-modal"]').click();
  const clearNavigation = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
  await page.locator('#punch-report-filter-modal').getByRole('button', { name: 'Limpiar filtros' }).click();
  await clearNavigation;
  const clearedUrl = new URL(page.url());
  expect(clearedUrl.searchParams.get('period')).toBe('today');
  expect(clearedUrl.searchParams.get('worker_id') || '').toBe('');
  expect(clearedUrl.searchParams.get('capture_method') || '').toBe('');
  expect(clearedUrl.searchParams.get('processing_status') || '').toBe('');

  await page.goto(`${reportPath}?from_date=${oct28}&to_date=${oct28}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#checador-punch-table tbody tr')).toHaveCount(11);
  const screenshotResponsive = [];
  for (const width of [1440, 1024, 768, 375]) {
    await page.setViewportSize({ width, height: 1000 });
    const metrics = await page.locator('.table-responsive').evaluate((wrapper) => ({
      clientWidth: wrapper.clientWidth,
      scrollWidth: wrapper.scrollWidth,
    }));
    screenshotResponsive.push({ width, ...metrics });
    const pageOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(pageOverflow, `page must not overflow at ${width}px`).toBe(false);
    if (width === 375) expect(metrics.scrollWidth).toBeGreaterThan(metrics.clientWidth);
    await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-m02-report-${width}.png`), fullPage: true });
  }

  await page.goto(`${reportPath}?from_date=${oct28}&to_date=${oct28}&worker_id=1005`, { waitUntil: 'domcontentloaded' });
  await page.setViewportSize({ width: 375, height: 812 });
  await expect(page.locator('#select2-punch-report-worker-container')).toContainText(collaboratorAlias);
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-m02-worker-selected-375.png`), fullPage: true });
  await page.locator('#select2-punch-report-worker-container').click();
  await page.locator('.select2-container--open .select2-search__field').fill(collaboratorAlias);
  await expect(page.locator('.select2-container--open .select2-results__option').filter({ hasText: collaboratorAlias })).toBeVisible();
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-m02-worker-search-375.png`), fullPage: true });
  await page.keyboard.press('Escape');

  await page.locator('[data-bs-target="#punch-report-filter-modal"]').click();
  await expect(page.locator('#punch-report-filter-modal')).toBeVisible();
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-m02-filter-modal-375.png`), fullPage: true });
  await page.locator('#punch-report-filter-modal').getByRole('button', { name: 'Cancelar' }).click();

  await page.setViewportSize({ width: 375, height: 812 });
  const responsiveTable = page.locator('.table-responsive').first();
  await responsiveTable.evaluate((wrapper) => { wrapper.scrollLeft = wrapper.scrollWidth; });
  const mobileActionButton = page.locator('#checador-punch-table tbody tr').filter({ hasText: collaboratorAlias }).first().getByRole('button', { name: 'Ver detalle' });
  await expect(mobileActionButton).toBeVisible();
  await mobileActionButton.click();
  await expect(detailModal).toBeVisible();
  const dialogBounds = await detailModal.locator('.modal-dialog').evaluate((dialog) => {
    const rect = dialog.getBoundingClientRect();
    return { left: rect.left, right: rect.right, width: window.innerWidth };
  });
  expect(dialogBounds.left).toBeGreaterThanOrEqual(0);
  expect(dialogBounds.right).toBeLessThanOrEqual(dialogBounds.width);
  await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-m03-detail-375.png`), fullPage: true });
  await detailModal.locator('[data-bs-dismiss="modal"]').first().click();

  expect(tableRequests.length).toBeGreaterThan(0);
  expect(tableRequests.every((request) => request.method === 'GET')).toBe(true);
  expect(tableRequests.some((request) => request.params.from_date === oct28)).toBe(true);
  expect(tableRequests.some((request) => request.params.from_date === oct29)).toBe(true);
  expect(contextSwitchRequests.length, 'only the authorized locality selector may update session context').toBeLessThanOrEqual(1);
  expect(writes, 'report reads must not issue data-mutating requests').toEqual(contextSwitchRequests);
  expect(pageErrors, `JavaScript errors: ${pageErrors.join('\n')}`).toEqual([]);
  expect(consoleErrors, `Console errors: ${consoleErrors.join('\n')}`).toEqual([]);
  expect(localHttpErrors, `Local HTTP errors: ${localHttpErrors.join('\n')}`).toEqual([]);
  testInfo.annotations.push({ type: 'responsive-screenshots', description: `Candidate screenshots ${JSON.stringify(screenshotResponsive)} saved in ${candidateScreenshotsRoot}` });
  testInfo.annotations.push({ type: 'csv', description: `Authorized filtered CSV downloaded as ${download.suggestedFilename()}; four matching raw event UUIDs read back.` });
  testInfo.annotations.push({ type: 'network-policy', description: `All external origins blocked; observed: ${[...new Set(blockedExternalOrigins)].join(', ') || 'none'}` });
});
