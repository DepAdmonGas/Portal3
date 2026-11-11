import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';

const configurationPath = '/departamento-operativo/recursos-humanos/checador/configuracion';
const policyPath = '/departamento-operativo/recursos-humanos/checador/configuracion/politicas-asistencia';

test('M11: Axios maneja la validación real y los errores HTTP sin escribir políticas', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || !fs.existsSync(authStatePath(profile.key)), `BLOCKED: falta sesión QA para ${testInfo.project.name}`);
  const evidenceRoot = path.join(candidateScreenshotsRoot, `m11-final-policy-ajax-${Date.now()}-${profile.key}`);
  ensurePrivateDirectory(evidenceRoot);

  const externalRequests = [];
  const policyResponses = [];
  await page.route('**/*', async route => {
    const request = route.request();
    const url = new URL(request.url());
    if (['http:', 'https:'].includes(url.protocol) && url.hostname !== 'portal3.test') {
      externalRequests.push(url.origin);
      await route.abort('blockedbyclient');
      return;
    }
    await route.continue();
  });
  const responseListener = response => {
    if (response.request().method() === 'POST' && new URL(response.url()).pathname === policyPath) policyResponses.push(response);
  };
  page.on('response', responseListener);
  await page.goto(configurationPath, { waitUntil: 'domcontentloaded' });
  const form = page.locator(`#attendance-policy-v2 form[action="${policyPath}"]`);
  await expect(form).toBeVisible();
  const card = page.locator('#attendance-policy-v2');
  const before = JSON.parse(await card.getAttribute('data-history'));
  expect(await card.getAttribute('data-history-complete')).toBe('true');
  expect(before.map(item => item.policy_version)).toEqual(expect.arrayContaining([1, 2, 3]));
  for (const policy of before.filter(item => [1, 2].includes(item.policy_version))) {
    expect(policy.retardo_tolerance_minutes).toBe(30);
    expect(policy.incidence_deadline_days).toBe(6);
  }
  const current = JSON.parse(await card.getAttribute('data-current-policy'));
  const next = JSON.parse(await card.getAttribute('data-next-policy'));
  const currentDate = await card.getAttribute('data-current-date');
  if (!current?.id) await expect(card.getByRole('heading', { name: 'Configuración vigente', exact: true })).toHaveCount(0);
  const upcoming = before.filter(item => item.effective_from_local_date > currentDate)
    .sort((a, b) => a.effective_from_local_date.localeCompare(b.effective_from_local_date))[0];
  expect(next.id).toBe(upcoming.id);
  await expect(card.locator('table tbody tr')).toHaveCount(before.length);
  await expect(card).not.toContainText(/legacy|versión V2|API V2|versionado/i);

  // Exercise the real controller's validation branch before it calls the API writer.
  const date = form.locator('[name="effective_from_local_date"]');
  await date.evaluate(element => { element.type = 'text'; });
  await date.fill('2026-02-30');
  await form.locator('[name="retardo_tolerance_minutes"]').fill('30');
  await form.locator('[name="incidence_deadline_days"]').fill('6');
  await form.locator('[name="confirm_policy"]').check();
  await form.getByRole('button', { name: 'Guardar configuración' }).click();
  await expect.poll(() => policyResponses.length).toBe(1);
  const response = policyResponses[0];
  expect(response.status()).toBe(422);
  expect(response.headers()['content-type']).toContain('application/json');
  expect(response.headers()['cache-control']).toContain('no-store');
  expect(await response.json()).toMatchObject({ ok: false, code: 'effective_date_invalid' });
  await expect(page.locator('#attendance-policy-v2 [role="alert"]')).toContainText('fecha local válida');

  // Server-scope check with an out-of-scope locality; ACL must reject before the API writer.
  await form.locator('[name="workplace_id"]').evaluate(element => { element.value = '999999'; });
  await date.fill('2027-01-01');
  await form.getByRole('button').click();
  await expect.poll(() => policyResponses.length).toBe(2);
  const outOfScopeBody = await policyResponses[1].json();
  expect(policyResponses[1].status()).toBe(422);
  expect(outOfScopeBody).toMatchObject({ ok: false, code: 'workplace_required' });
  await expect(page.locator('#attendance-policy-v2 [role="alert"]')).toContainText('Selecciona una localidad autorizada');

  // Isolated browser responses exercise client messaging and the in-flight lock only.
  let simulatedStatus = 401;
  let simulatedRequests = 0;
  await page.route(`**${policyPath}`, async route => {
    simulatedRequests++;
    const status = simulatedStatus;
    await new Promise(resolve => setTimeout(resolve, status === 409 ? 350 : 10));
    if (status === 200) {
      await route.fulfill({ status, contentType: 'application/json', body: JSON.stringify({
        ok: true,
        message: 'Configuración guardada y confirmada (respuesta simulada).',
        current_local_date: '2026-11-03',
        policy: { id: 999999, policy_version: 99, effective_from_local_date: '2026-11-07', retardo_tolerance_minutes: 45, incidence_deadline_days: 8, actor_portal_user_id: profile.expectedPositionId === 6 ? 33 : 19 },
        latest_scheduled_policy: { id: 999999, policy_version: 99, effective_from_local_date: '2026-11-07', retardo_tolerance_minutes: 45, incidence_deadline_days: 8, actor_portal_user_id: profile.expectedPositionId === 6 ? 33 : 19 },
        next_idempotency_key: 'isolated-test-key',
      }) });
      return;
    }
    const message = status === 422 ? 'Revisa la fecha y los valores permitidos.' : 'Respuesta simulada.';
    await route.fulfill({ status, contentType: 'application/json', body: JSON.stringify({ ok: false, message }) });
  });
  await date.fill('2026-11-20');
  const expectedMessages = new Map([
    [401, 'Tu sesión expiró'],
    [403, 'No tienes permiso'],
    [409, 'entra en conflicto'],
    [422, 'Revisa la fecha'],
    [503, 'El servidor no pudo confirmar'],
  ]);
  for (const [status, message] of expectedMessages) {
    simulatedStatus = status;
    await form.getByRole('button').click();
    await expect(page.locator('#attendance-policy-v2 [role="alert"]')).toContainText(message);
  }
  simulatedStatus = 409;
  const beforeDuplicateAttempt = simulatedRequests;
  await form.getByRole('button').click();
  await expect(form.getByRole('button')).toBeDisabled();
  await form.evaluate(element => element.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
  await expect(page.locator('#attendance-policy-v2 [role="alert"]')).toContainText('entra en conflicto');
  expect(simulatedRequests - beforeDuplicateAttempt).toBe(1);

  simulatedStatus = 200;
  await date.fill('2026-11-07');
  await form.getByRole('button').click();
  await expect(page.locator('#attendance-policy-v2 .alert-success')).toContainText('respuesta simulada');
  await expect(page.locator('#attendance-policy-v2')).toContainText('2026-11-07');
  await expect(page.locator('#attendance-policy-v2')).toContainText('45 min');

  await page.reload({ waitUntil: 'domcontentloaded' });
  const after = JSON.parse(await page.locator('#attendance-policy-v2').getAttribute('data-history'));
  expect(after.map(item => item.id).sort()).toEqual(before.map(item => item.id).sort());
  for (const width of [1440, 768, 375]) {
    await page.setViewportSize({ width, height: 950 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width + 1);
    if (width === 375) {
      const historyScroller = page.locator('#attendance-policy-v2 .table-responsive');
      const dimensions = await historyScroller.evaluate(element => ({
        clientWidth: element.clientWidth,
        scrollWidth: element.scrollWidth,
      }));
      if (dimensions.scrollWidth > dimensions.clientWidth) {
        const lastHeader = page.getByRole('columnheader', { name: 'Registró' });
        await lastHeader.scrollIntoViewIfNeeded();
        const visibleInScroller = await lastHeader.evaluate((element, selector) => {
          const container = document.querySelector(selector);
          const cell = element.getBoundingClientRect();
          const bounds = container.getBoundingClientRect();
          return cell.right > bounds.left && cell.left < bounds.right;
        }, '#attendance-policy-v2 .table-responsive');
        expect(visibleInScroller).toBe(true);
        await historyScroller.evaluate(element => { element.scrollLeft = 0; });
      }
    }
    await page.screenshot({ path: path.join(evidenceRoot, `${profile.key}-configuration-${width}.png`), fullPage: true });
  }
  expect(externalRequests.every(origin => origin === 'https://cdn.jsdelivr.net')).toBe(true);
  page.off('response', responseListener);
});
