import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, qaProfiles, baseURL } from '../paths.mjs';
import { installLocalOriginGuard } from '../scripts/session-check.mjs';
import { readProfileFacts } from '../scripts/profile-facts.mjs';

const reportPath = '/departamento-operativo/recursos-humanos/checador/reporte';
const workersPath = `${reportPath}/colaboradores`;

function isWorkerListResponse(response, expectedTerm = '') {
  const url = new URL(response.url());
  return url.origin === baseURL && url.pathname === workersPath
    && (url.searchParams.get('term') || '') === expectedTerm;
}

async function chooseLocality(page, locality) {
  const selector = page.locator('#module-station-selector-checador');
  await expect(selector).toBeVisible();
  const options = await selector.locator('option').evaluateAll((items) => items.map((option) => ({
    value: option.value,
    text: option.textContent?.trim() || '',
  })));
  const option = options.find((item) => item.text.includes(locality.name));
  expect(option, `${locality.name} must be a real option in the authorized station selector`).toBeTruthy();
  if (await selector.inputValue() === option.value) return;

  const contextPromise = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.origin === baseURL && url.pathname === '/api/module-context/set' && response.request().method() === 'POST';
  });
  const navigationPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
  await selector.selectOption(option.value);
  expect((await contextPromise).status()).toBe(200);
  await navigationPromise;
  expect(new URL(page.url()).searchParams.has('worker_id')).toBe(false);
}

async function inspectInitialRoster(page, locality, profileKey, width, closeAfter = true) {
  await page.setViewportSize({ width, height: 950 });
  const initialResponsePromise = page.waitForResponse((response) => isWorkerListResponse(response));
  await page.locator('#select2-punch-report-worker-container').click();
  const response = await initialResponsePromise;
  expect(response.status(), `${locality.name} empty-term roster request`).toBe(200);
  const payload = await response.json();
  expect(payload.results.length, `${locality.name} returns names on initial open`).toBeGreaterThan(0);
  expect(payload.results.every((worker) => Number.isInteger(Number(worker.id))
    && Number(worker.id) > 0
    && String(worker.text).trim() !== ''
    && !/\bID\s+interno\b/u.test(worker.text))).toBe(true);
  await expect(page.locator('.select2-container--open .select2-results__option').first()).toBeVisible();
  if (payload.pagination?.more) {
    const pageTwoPromise = page.waitForResponse((candidate) => {
      const url = new URL(candidate.url());
      return url.origin === baseURL && url.pathname === workersPath
        && (url.searchParams.get('term') || '') === '' && url.searchParams.get('page') === '2';
    });
    await page.locator('.select2-results__options').last().evaluate((element) => {
      element.scrollTop = element.scrollHeight;
      element.dispatchEvent(new Event('scroll', { bubbles: true }));
    });
    const pageTwoResponse = await pageTwoPromise;
    expect(pageTwoResponse.status()).toBe(200);
    const pageTwo = await pageTwoResponse.json();
    expect(pageTwo.results.length).toBeGreaterThan(0);
    expect(pageTwo.results.some((secondPageWorker) => payload.results.some((firstPageWorker) => firstPageWorker.id === secondPageWorker.id))).toBe(false);
  }

  const dropdownBounds = await page.locator('.select2-dropdown').last().evaluate((element) => {
    const rect = element.getBoundingClientRect();
    return { left: rect.left, right: rect.right, viewport: window.innerWidth, documentWidth: document.documentElement.scrollWidth };
  });
  expect(dropdownBounds.left).toBeGreaterThanOrEqual(0);
  expect(dropdownBounds.right).toBeLessThanOrEqual(dropdownBounds.viewport);
  expect(dropdownBounds.documentWidth).toBeLessThanOrEqual(dropdownBounds.viewport);
  await page.screenshot({
    path: path.join(candidateScreenshotsRoot, `${profileKey}-m02-${locality.key}-initial-list-${width}.png`),
    fullPage: true,
  });
  if (closeAfter) await page.keyboard.press('Escape');
  return payload.results;
}

test('M02 Select2 abre con personal actual por localidad y conserva el alcance al cambiar estación', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA autorizada para ${testInfo.project.name}`);
  const facts = readProfileFacts(profile.key);
  expect(facts.checador_read).toBe(true);
  expect(['encargado-palo-solo', 'direccion-operaciones']).toContain(profile.key);
  const localities = [
    { id: 9, name: 'Autolavado', key: 'autolavado' },
    { id: 2, name: 'Palo Solo', key: 'palo-solo' },
  ].filter((locality) => facts.localities.some((authorized) => authorized.id === locality.id));
  test.skip(localities.length < 2, `BLOCKED: ${profile.key} is not explicitly authorized for both Autolavado and Palo Solo`);

  fs.mkdirSync(candidateScreenshotsRoot, { recursive: true, mode: 0o700 });
  fs.chmodSync(candidateScreenshotsRoot, 0o700);
  const pageErrors = [];
  const consoleErrors = [];
  const localHttpErrors = [];
  const writes = [];
  const contextWrites = [];
  const blockedOrigins = [];
  await installLocalOriginGuard(page, blockedOrigins);
  page.on('pageerror', (error) => pageErrors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) consoleErrors.push(message.text());
  });
  page.on('response', (response) => {
    if (response.status() >= 400 && new URL(response.url()).hostname === 'portal3.test') {
      localHttpErrors.push(`${response.status()} ${response.request().method()} ${response.url()}`);
    }
  });
  page.on('request', (request) => {
    const url = new URL(request.url());
    if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) {
      writes.push(`${request.method()} ${url.pathname}`);
      if (url.pathname === '/api/module-context/set') contextWrites.push(`${request.method()} ${url.pathname}`);
    }
  });

  const pageResponse = await page.goto(reportPath, { waitUntil: 'domcontentloaded' });
  expect(pageResponse?.status()).toBe(200);
  for (const locality of localities) {
    await chooseLocality(page, locality);
    const initialResults = await inspectInitialRoster(page, locality, profile.key, 1440, false);
    expect(initialResults.length).toBeGreaterThan(0);
    const firstName = String(initialResults[0].text).trim();
    const tokens = firstName.split(/\s+/u);

    const searchField = page.locator('.select2-container--open .select2-search__field');
    const nameResponsePromise = page.waitForResponse((response) => isWorkerListResponse(response, tokens[0]));
    await searchField.fill(tokens[0]);
    const nameResponse = await nameResponsePromise;
    expect(nameResponse.status()).toBe(200);
    const namePayload = await nameResponse.json();
    expect(namePayload.results.some((worker) => worker.text === firstName)).toBe(true);

    const surname = tokens[tokens.length - 1];
    const surnameResponsePromise = page.waitForResponse((response) => isWorkerListResponse(response, surname));
    await searchField.fill(surname);
    const surnameResponse = await surnameResponsePromise;
    expect(surnameResponse.status()).toBe(200);
    const surnamePayload = await surnameResponse.json();
    expect(surnamePayload.results.some((worker) => worker.text === firstName)).toBe(true);
    await expect(page.locator('.select2-container--open .select2-results__option').filter({ hasText: firstName }).first()).toBeVisible();
    await page.locator('.select2-container--open .select2-results__option').filter({ hasText: firstName }).first().click();

    const canonicalId = await page.locator('#punch-report-worker').inputValue();
    expect(canonicalId).toBe(String(initialResults[0].id));
    await expect(page.locator('#select2-punch-report-worker-container')).toHaveText(firstName);
    await expect(page.locator('#punch-report-worker option:checked')).toHaveText(firstName);
    await page.screenshot({ path: path.join(candidateScreenshotsRoot, `${profile.key}-m02-${locality.key}-selected-1440.png`), fullPage: true });

    const dataPromise = page.waitForResponse((response) => {
      const url = new URL(response.url());
      return url.origin === baseURL && url.pathname === `${reportPath}/data`
        && url.searchParams.get('worker_id') === canonicalId;
    });
    const navigationPromise = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Consultar' }).click();
    await navigationPromise;
    expect((await dataPromise).status()).toBe(200);
    expect(new URL(page.url()).searchParams.get('worker_id')).toBe(canonicalId);
    await expect(page.locator('#select2-punch-report-worker-container')).toHaveText(firstName);

    for (const width of [1024, 768, 375]) {
      const results = await inspectInitialRoster(page, locality, profile.key, width);
      expect(results.length).toBeGreaterThan(0);
    }
    await chooseLocality(page, localities.find((candidate) => candidate.id !== locality.id));
    expect(new URL(page.url()).searchParams.has('worker_id')).toBe(false);
    await expect(page.locator('#punch-report-worker option:checked')).toHaveText('Todos los colaboradores');
  }

  expect(writes).toEqual(contextWrites);
  expect(pageErrors).toEqual([]);
  expect(consoleErrors).toEqual([]);
  expect(localHttpErrors).toEqual([]);
  testInfo.annotations.push({ type: 'network-policy', description: `External origins were blocked by the test guard: ${[...new Set(blockedOrigins)].join(', ') || 'none'}` });
});
