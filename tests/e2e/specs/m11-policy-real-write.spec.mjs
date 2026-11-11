import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { readProfileFacts, normalizedIdentity } from '../scripts/profile-facts.mjs';

const configurationPath = '/departamento-operativo/recursos-humanos/checador/configuracion';
const policyPostPath = `${configurationPath}/politicas-asistencia`;
const evidenceRoot = path.join(candidateScreenshotsRoot, `m11-policy-real-write-${Date.now()}`);
const qaEffectiveDate = '2026-11-07';

test('M11: Dirección guarda una versión prospectiva 30/6 y confirma su readback real', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || profile.key !== 'direccion-operaciones', 'Only Dirección is authorized for this controlled configuration write.');
  test.skip(!fs.existsSync(authStatePath('direccion-operaciones')), 'BLOCKED: missing authenticated Dirección session.');
  const facts = readProfileFacts('direccion-operaciones');
  expect(facts.user_id).toBe(19);
  expect(facts.position_id).toBe(13);
  expect(facts.localities.some(({ id }) => id === 1)).toBe(true);
  expect(facts.attendance_policy_read).toBe(true);
  expect(facts.attendance_policy_edit).toBe(true);
  expect(facts.attendance_policy_write_gate).toBe(true);
  expect(facts.m11_local_write_gate).toBe(true);
  ensurePrivateDirectory(evidenceRoot);

  const unexpectedWrites = [];
  const blockedOrigins = [];
  const pageErrors = [];
  const consoleErrors = [];
  const postTrace = [];
  await page.route('**/*', async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    if (['http:', 'https:'].includes(url.protocol) && url.hostname !== 'portal3.test') {
      blockedOrigins.push(url.origin);
      await route.abort('blockedbyclient');
      return;
    }
    if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())
      && !(request.method() === 'POST' && url.origin === 'http://portal3.test' && url.pathname === policyPostPath)) {
      unexpectedWrites.push(`${request.method()} ${url.pathname}`);
      await route.abort('blockedbyclient');
      return;
    }
    await route.continue();
  });
  page.on('request', (request) => {
    const url = new URL(request.url());
    if (request.method() === 'POST') postTrace.push({ method: request.method(), origin: url.origin, path: url.pathname });
  });
  page.on('pageerror', (error) => pageErrors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().includes('net::ERR_BLOCKED_BY_CLIENT')) consoleErrors.push(message.text());
  });

  const initial = await page.goto(configurationPath, { waitUntil: 'domcontentloaded' });
  expect(initial?.status()).toBe(200);
  await expect(page.locator('.fixed-profile .john-title span')).toHaveText(profile.rolePattern);
  expect(normalizedIdentity(await page.locator('.fixed-profile .john-title h6').innerText()))
    .toBe(normalizedIdentity(facts.display_name_prefix));
  await expect(page.getByRole('heading', { name: 'Configuración de Checador', exact: true })).toHaveCount(1);

  const policyCard = page.locator('#attendance-policy-v2');
  await expect(policyCard).toBeVisible();
  await expect(policyCard.getByRole('heading', { name: 'Configuración de asistencia', exact: true })).toBeVisible();
  await expect(policyCard).toContainText('Retardos e incidencias');
  const currentDate = await policyCard.getAttribute('data-current-date');
  const initialHistory = JSON.parse(await policyCard.getAttribute('data-history'));
  const targetExisting = initialHistory.find((item) => item.effective_from_local_date === qaEffectiveDate);
  let expectedPolicy;

  if (targetExisting) {
    // Re-running the E2E never creates a duplicate or replaces an existing row.
    expect(targetExisting.workplace_id).toBe(1);
    expect(targetExisting.actor_portal_user_id).toBe(19);
    expect(targetExisting.retardo_tolerance_minutes).toBe(30);
    expect(targetExisting.incidence_deadline_days).toBe(6);
    expect(targetExisting.source).toBe('PORTAL3_ADMIN');
    expectedPolicy = targetExisting;
  } else {
    const latest = initialHistory.reduce((winner, item) => (
      !winner || item.effective_from_local_date > winner.effective_from_local_date ? item : winner
    ), null);
    expect(latest, 'An existing policy version is required before adding the QA version.').toBeTruthy();
    expect(latest.workplace_id).toBe(1);
    expect(latest.retardo_tolerance_minutes).toBe(30);
    expect(latest.incidence_deadline_days).toBe(6);
    expect(qaEffectiveDate > String(currentDate)).toBe(true);
    expect(qaEffectiveDate > latest.effective_from_local_date).toBe(true);

    const editor = page.locator('#attendance-policy-edit-modal');
    await policyCard.getByRole('button', { name: 'Editar configuración', exact: false }).click();
    await expect(editor).toBeVisible();
    const form = editor.locator(`form[action$="${policyPostPath}"]`);
    await expect(form).toBeVisible();
    await expect(form.locator('#attendance-policy-effective-from')).toHaveAttribute('min', /.+/);
    await form.locator('#attendance-policy-effective-from').fill(qaEffectiveDate);
    await form.locator('#attendance-policy-tolerance').fill('30');
    await form.locator('#attendance-policy-deadline').fill('6');
    await form.locator('#attendance-policy-confirm').check();

    for (const width of [1440, 768, 375]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.screenshot({ path: path.join(evidenceRoot, `direction-policy-before-${width}.png`), fullPage: true });
    }

    const responsePromise = page.waitForResponse((response) => {
      const url = new URL(response.url());
      return response.request().method() === 'POST' && url.origin === 'http://portal3.test' && url.pathname === policyPostPath;
    });
    await form.getByRole('button', { name: 'Guardar cambios', exact: false }).click();
    const response = await responsePromise;
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.ok).toBe(true);
    expect(body.policy).toMatchObject({
      workplace_id: 1,
      effective_from_local_date: qaEffectiveDate,
      retardo_tolerance_minutes: 30,
      incidence_deadline_days: 6,
      actor_portal_user_id: 19,
      source: 'PORTAL3_ADMIN',
    });
    expect(Number(body.policy.policy_version)).toBe(Number(latest.policy_version) + 1);
    expect(body.latest_scheduled_policy).toMatchObject({
      id: body.policy.id,
      policy_version: body.policy.policy_version,
      effective_from_local_date: qaEffectiveDate,
    });
    expectedPolicy = body.policy;
    expect(postTrace).toEqual([{ method: 'POST', origin: 'http://portal3.test', path: policyPostPath }]);
    expect(unexpectedWrites).toEqual([]);

    await expect(editor).toBeHidden();
    await expect(policyCard.locator('.alert-success[role="status"]')).toContainText(/guardad|confirmad/i);
    await expect(policyCard).toContainText('Próxima configuración');
    await expect(policyCard).toContainText(qaEffectiveDate);
    await expect(policyCard).toContainText('30');
    await expect(policyCard).toContainText('6');
  }

  await page.reload({ waitUntil: 'domcontentloaded' });
  const reloadedCard = page.locator('#attendance-policy-v2');
  const reloadedHistory = JSON.parse(await reloadedCard.getAttribute('data-history'));
  const persisted = reloadedHistory.find((item) => item.effective_from_local_date === qaEffectiveDate);
  expect(persisted).toMatchObject({
    id: expectedPolicy.id,
    workplace_id: 1,
    policy_version: expectedPolicy.policy_version,
    effective_from_local_date: qaEffectiveDate,
    retardo_tolerance_minutes: 30,
    incidence_deadline_days: 6,
    actor_portal_user_id: 19,
    source: 'PORTAL3_ADMIN',
  });
  expect(reloadedHistory.filter((item) => [1, 2, 3].includes(Number(item.policy_version))).map((item) => ({
    version: Number(item.policy_version),
    effective: item.effective_from_local_date,
    tolerance: Number(item.retardo_tolerance_minutes),
    deadline: Number(item.incidence_deadline_days),
  }))).toEqual(expect.arrayContaining([
    { version: 1, effective: '2026-11-04', tolerance: 30, deadline: 6 },
    { version: 2, effective: '2026-11-05', tolerance: 30, deadline: 6 },
    { version: 3, effective: '2026-11-06', tolerance: 30, deadline: 6 },
  ]));

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.screenshot({ path: path.join(evidenceRoot, 'direction-policy-after-reload-1440.png'), fullPage: true });
  await page.setViewportSize({ width: 768, height: 1000 });
  await page.screenshot({ path: path.join(evidenceRoot, 'direction-policy-after-reload-768.png'), fullPage: true });
  await page.setViewportSize({ width: 375, height: 900 });
  await page.screenshot({ path: path.join(evidenceRoot, 'direction-policy-after-reload-375.png'), fullPage: true });

  expect(pageErrors).toEqual([]);
  expect(consoleErrors).toEqual([]);
  expect(unexpectedWrites).toEqual([]);
  expect(postTrace.length).toBe(targetExisting ? 0 : 1);
  testInfo.annotations.push({ type: 'candidate-screenshots', description: evidenceRoot });
  testInfo.annotations.push({ type: 'database-readback', description: 'Portal3 writer verified the exact row through the authorized API readback before returning success; reloaded Portal3 history confirms persistence.' });
  testInfo.annotations.push({ type: 'qa-scope', description: `One prospective Interlomas version at ${qaEffectiveDate}, preserving authorized values 30/6 and existing versions 1–3.` });
  testInfo.annotations.push({ type: 'external-network-policy', description: [...new Set(blockedOrigins)].join(', ') || 'external origins blocked or unused' });
});
