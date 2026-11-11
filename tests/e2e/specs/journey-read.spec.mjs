import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const journeyPath = '/departamento-operativo/recursos-humanos/checador/jornadas';
const evidenceRoot = path.join(candidateScreenshotsRoot, 'm05-station-journey-read-authorized-20261102');
const realPunchDates = ['2026-10-28', '2026-10-29'];

test('M05: read canonical observations, effective schedule and trace without creating Journey results', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: missing authorized QA session for ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  ensurePrivateDirectory(evidenceRoot);

  const pageErrors = [];
  const consoleErrors = [];
  const localHttpErrors = [];
  const unexpectedWrites = [];
  const blockedOrigins = [];
  await installLocalOriginGuard(page, blockedOrigins);
  page.on('pageerror', error => pageErrors.push(error.message));
  page.on('console', message => {
    if (message.type() !== 'error' || message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) return;
    consoleErrors.push(message.text());
  });
  page.on('response', response => {
    const url = new URL(response.url());
    if (url.hostname === 'portal3.test' && response.status() >= 400) {
      localHttpErrors.push(`${response.status()} ${response.request().method()} ${url.pathname}`);
    }
  });
  page.on('requestfailed', request => {
    const url = new URL(request.url());
    if (url.hostname === 'portal3.test') localHttpErrors.push(`${request.method()} ${url.pathname} ${request.failure()?.errorText || ''}`);
  });
  page.on('request', request => {
    const url = new URL(request.url());
    if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) unexpectedWrites.push(`${request.method()} ${url.pathname}`);
  });

  const session = await verifyPortalSession(page, profile.key, { blockedOrigins });
  expect(session.valid, `Stored session/profile verification failed: ${session.reason}`).toBe(true);

  let dateWithRecords = null;
  let eventRows = null;
  for (const date of realPunchDates) {
    const response = await page.goto(`${journeyPath}?date=${date}`, { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);
    await expect(page.locator('.fixed-profile .john-title span')).toHaveText(profile.rolePattern);
    await expect(page.locator('[aria-label="breadcrumb"]')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Jornadas laborales', exact: true })).toBeVisible();
    const rows = page.locator('section[aria-labelledby="journey-workspace-heading"] table tbody tr');
    await expect(page.getByText(/permiso específico de consulta de Jornadas no está habilitado/i)).toHaveCount(0);

    await expect(page.getByText('Jornada no evaluada en esta consulta.', { exact: false })).toBeVisible();
    await expect(page.getByText(/cobertura de fuente: desconocida/i)).toBeVisible();
    await expect(page.getByText('Resultado disponible', { exact: true })).toHaveCount(0);
    await expect(page.locator('section[aria-labelledby="journey-workspace-heading"] table tbody').getByText(/\bFALTA\b|\bRETARDO\b|Jornada completa/i)).toHaveCount(0);
    const count = await rows.count();
    if (count > 0 && dateWithRecords === null) {
      dateWithRecords = date;
      eventRows = rows;
    }
    await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-journeys-${date}-read-${count > 0 ? 'rows' : 'empty'}-1440.png`), fullPage: true });
  }

  expect(dateWithRecords, 'API did not return canonical raw events for the known development dates').not.toBeNull();
  expect(eventRows).not.toBeNull();
  await page.goto(`${journeyPath}?date=${dateWithRecords}`, { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('Interlomas', { exact: false }).first()).toBeVisible();
  const aggregateObserved = await eventRows.locator('td:nth-child(3)').allTextContents();
  const observedCount = aggregateObserved.reduce((sum, value) => sum + (Number.parseInt(value, 10) || 0), 0);
  expect(observedCount, 'M05 should show the known real observation set for the selected date').toBe(12);
  const rowText = (await eventRows.allTextContents()).join(' ');
  for (const workerId of ['1004', '1005', '1006', '1007', '1008']) {
    expect(rowText).toContain(workerId);
  }
  if (profile.key === 'encargado') {
    const forgedStationResponse = await page.goto(`${journeyPath}?date=${dateWithRecords}&station_id=2`, { waitUntil: 'domcontentloaded' });
    expect(forgedStationResponse?.status()).toBe(200);
    await expect(page.getByText('Interlomas', { exact: false }).first()).toBeVisible();
    const unchangedObservationCount = await page.locator('section[aria-labelledby="journey-workspace-heading"] table tbody tr td:nth-child(3)').allTextContents();
    expect(unchangedObservationCount.reduce((sum, value) => sum + (Number.parseInt(value, 10) || 0), 0)).toBe(12);
    await page.goto(`${journeyPath}?date=${dateWithRecords}`, { waitUntil: 'domcontentloaded' });
  }
  const firstRow = eventRows.first();
  await expect(firstRow.locator('td').nth(1)).toHaveText(/\d+/);
  await expect(firstRow.locator('td').nth(3)).toContainText('No evaluada');

  const workerOptions = await page.locator('#journey-worker-filter option').evaluateAll(options =>
    options.map(option => ({ value: option.value, label: option.textContent?.trim() || '' })).filter(option => option.value !== '')
  );
  expect(workerOptions.length).toBeGreaterThan(0);
  const selectedWorker = workerOptions[0].value;
  await page.locator('#journey-worker-filter').selectOption(selectedWorker);
  await Promise.all([
    page.waitForURL(url => url.searchParams.get('worker_id') === selectedWorker && url.searchParams.get('date') === dateWithRecords),
    page.getByRole('button', { name: 'Consultar' }).click(),
  ]);
  await expect(page.locator('#journey-worker-filter')).toHaveValue(selectedWorker);
  await expect(page.locator('#journey-work-date')).toHaveValue(dateWithRecords);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(page.locator('#journey-worker-filter')).toHaveValue(selectedWorker);
  await expect(page.locator('#journey-work-date')).toHaveValue(dateWithRecords);
  await expect(page.locator('section[aria-labelledby="journey-workspace-heading"] table tbody tr').first()).toBeVisible();

  const tableContainer = page.locator('section[aria-labelledby="journey-workspace-heading"] .table-responsive').first();
  const actionButton = page.getByRole('button', { name: /Ver trazabilidad/ }).first();
  for (const width of [1440, 1024, 768, 375]) {
    await page.setViewportSize({ width, height: 1000 });
    const geometry = await page.evaluate(() => ({ viewport: window.innerWidth, document: document.documentElement.scrollWidth }));
    expect(geometry.document).toBeLessThanOrEqual(width + 1);
    if (width === 375) {
      const overflow = await tableContainer.evaluate(node => ({ client: node.clientWidth, scroll: node.scrollWidth }));
      expect(overflow.scroll).toBeGreaterThan(overflow.client);
      await tableContainer.evaluate(node => { node.scrollLeft = node.scrollWidth; });
      await expect(actionButton).toBeVisible();
    }
    await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-journeys-${width}.png`), fullPage: true });
  }

  await actionButton.click();
  const modal = page.locator('.modal.show').last();
  await expect(modal).toBeVisible();
  await expect(modal.getByText('No consultable: API V2 no expone una lectura GET para este dato.')).toBeVisible();
  const firstTraceRow = modal.locator('tbody tr').first();
  await expect(firstTraceRow.locator('td').first()).not.toHaveText(/^(?:-|No disponible)$/);
  await expect(firstTraceRow.locator('td').nth(1)).not.toHaveText(/^(?:-|No disponible)$/);
  await expect(firstTraceRow.locator('td').nth(3)).not.toHaveText(/^(?:-|No disponible)$/);
  const modalAlignment = await modal.locator('.modal-body').evaluate(node => getComputedStyle(node).textAlign);
  expect(['start', 'left']).toContain(modalAlignment);
  await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-journeys-${dateWithRecords}-trace-modal-375.png`), fullPage: true });

  expect(unexpectedWrites, `Unexpected browser writes: ${unexpectedWrites.join('\n')}`).toEqual([]);
  expect(pageErrors, `JavaScript errors: ${pageErrors.join('\n')}`).toEqual([]);
  expect(consoleErrors, `Console errors: ${consoleErrors.join('\n')}`).toEqual([]);
  expect(localHttpErrors, `Local HTTP errors: ${localHttpErrors.join('\n')}`).toEqual([]);
  testInfo.annotations.push({ type: 'real-read-source', description: `API V2 raw-observation read; dates checked ${realPunchDates.join(', ')}; 12 events across five known QA identities observed on ${dateWithRecords}.` });
  testInfo.annotations.push({ type: 'screenshots', description: `Candidate evidence stored in ${evidenceRoot}` });
  testInfo.annotations.push({ type: 'external-network-policy', description: `Blocked origins: ${[...new Set(blockedOrigins)].join(', ') || 'none'}` });
});
