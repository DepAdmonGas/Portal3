import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { readProfileFacts, normalizedIdentity } from '../scripts/profile-facts.mjs';

const configurationPath = '/departamento-operativo/recursos-humanos/checador/configuracion';
const positionPostPath = `${configurationPath}/puestos`;
const readerPostPath = `${configurationPath}/perfiles-lector/actualizar`;
const evidenceRoot = path.join(candidateScreenshotsRoot, `m11-authorized-noop-writes-${Date.now()}`);

const installWriteGuard = async (page, expectedPath) => {
  const blockedOrigins = [];
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
      && !(request.method() === 'POST' && url.origin === 'http://portal3.test' && url.pathname === expectedPath)) {
      await route.abort('blockedbyclient');
      return;
    }
    await route.continue();
  });
  page.on('request', (request) => {
    const url = new URL(request.url());
    if (request.method() === 'POST') postTrace.push({ method: request.method(), origin: url.origin, path: url.pathname });
  });
  return { blockedOrigins, postTrace };
};

test('M11: Encargado re-saves only the current reader username; blank secret preserves the credential', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || profile.key !== 'encargado', 'Only the station Encargado can edit this station reader profile.');
  test.skip(!fs.existsSync(authStatePath('encargado')), 'BLOCKED: missing authenticated Encargado session.');
  const facts = readProfileFacts('encargado');
  expect(facts.user_id).toBe(33);
  expect(facts.position_id).toBe(6);
  expect(facts.localities.map(({ id }) => id)).toEqual([1]);
  expect(facts.reader_profile_read).toBe(true);
  expect(facts.reader_profile_edit).toBe(true);
  expect(facts.position_catalog_read).toBe(false);
  expect(facts.m11_local_write_gate).toBe(true);
  ensurePrivateDirectory(evidenceRoot);
  const { postTrace } = await installWriteGuard(page, readerPostPath);

  const initial = await page.goto(configurationPath, { waitUntil: 'domcontentloaded' });
  expect(initial?.status()).toBe(200);
  await expect(page.locator('.fixed-profile .john-title span')).toHaveText(profile.rolePattern);
  const card = page.locator('#reader-access');
  await expect(card).toHaveAttribute('data-save-url', readerPostPath);
  await card.getByRole('button', { name: 'Editar', exact: true }).click();
  const modal = card.locator('#m11-reader-profile-modal');
  await expect(modal).toBeVisible();
  const form = card.locator(`form[action$="${readerPostPath}"]`).first();
  await expect(form).toBeVisible();
  const usernameInput = form.locator('input[name="username"]');
  const originalUsername = await usernameInput.inputValue();
  expect(originalUsername.trim().length).toBeGreaterThan(0);
  const passwordInput = form.locator('input[name="new_password"]');
  const confirmationInput = form.locator('input[name="new_password_confirm"]');
  await expect(passwordInput).toHaveValue('');
  await expect(confirmationInput).toHaveValue('');

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.screenshot({ path: path.join(evidenceRoot, 'encargado-reader-before-1440.png'), fullPage: true });
  await page.setViewportSize({ width: 375, height: 900 });
  await page.screenshot({ path: path.join(evidenceRoot, 'encargado-reader-edit-modal-375.png'), fullPage: true });
  const postPromise = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return response.request().method() === 'POST' && url.origin === 'http://portal3.test' && url.pathname === readerPostPath;
  });
  await form.getByRole('button', { name: 'Guardar cambios', exact: false }).click();
  const response = await postPromise;
  expect(response.status()).toBe(200);
  const body = await response.json();
  expect(body.ok).toBe(true);
  expect(body.profile).toMatchObject({ id: Number(await form.locator('input[name="profile_id"]').inputValue()), username: originalUsername });
  expect(postTrace).toEqual([{ method: 'POST', origin: 'http://portal3.test', path: readerPostPath }]);
  await expect(modal).toBeHidden();
  await expect(card.locator('.alert-success[role="status"]')).toHaveText('Usuario del lector actualizado.');
  await expect(modal.locator('input[name="new_password"]')).toHaveValue('');
  await expect(modal.locator('input[name="new_password_confirm"]')).toHaveValue('');

  await page.reload({ waitUntil: 'domcontentloaded' });
  const reloadedCard = page.locator('#reader-access');
  await reloadedCard.getByRole('button', { name: 'Editar', exact: true }).click();
  const reloadedModal = reloadedCard.locator('#m11-reader-profile-modal');
  const reloadedForm = reloadedModal.locator(`form[action$="${readerPostPath}"]`).first();
  await expect(reloadedModal).toBeVisible();
  await expect(reloadedForm.locator('input[name="username"]')).toHaveValue(originalUsername);
  await expect(reloadedForm.locator('input[name="new_password"]')).toHaveValue('');
  await expect(reloadedForm.locator('input[name="new_password_confirm"]')).toHaveValue('');
  expect(normalizedIdentity(await page.locator('.fixed-profile .john-title span').innerText())).toBe(normalizedIdentity('Encargado'));
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.screenshot({ path: path.join(evidenceRoot, 'encargado-reader-after-reload-1440.png'), fullPage: true });
  await page.setViewportSize({ width: 375, height: 900 });
  await page.screenshot({ path: path.join(evidenceRoot, 'encargado-reader-after-reload-375.png'), fullPage: true });
  testInfo.annotations.push({ type: 'candidate-screenshots', description: evidenceRoot });
  testInfo.annotations.push({ type: 'write-scope', description: 'Same existing username, no new password submitted, Interlomas reader profile only.' });
});

test('M11: Dirección re-saves an existing institutional position unchanged', async ({ page }, testInfo) => {
  const profile = qaProfiles.find(({ key }) => key === testInfo.project.name);
  test.skip(!profile || profile.key !== 'direccion-operaciones', 'Only Dirección can edit the existing institutional positions catalog.');
  test.skip(!fs.existsSync(authStatePath('direccion-operaciones')), 'BLOCKED: missing authenticated Dirección session.');
  const facts = readProfileFacts('direccion-operaciones');
  expect(facts.user_id).toBe(19);
  expect(facts.position_id).toBe(13);
  expect(facts.position_catalog_read).toBe(true);
  expect(facts.position_catalog_edit).toBe(true);
  expect(facts.reader_profile_edit).toBe(false);
  expect(facts.m11_local_write_gate).toBe(true);
  ensurePrivateDirectory(evidenceRoot);
  const { postTrace } = await installWriteGuard(page, positionPostPath);

  const initial = await page.goto(configurationPath, { waitUntil: 'domcontentloaded' });
  expect(initial?.status()).toBe(200);
  await expect(page.locator('.fixed-profile .john-title span')).toHaveText(profile.rolePattern);
  const positionCard = page.locator('#people-positions');
  const form = positionCard.locator(`form[action$="${positionPostPath}"]`).filter({ has: page.locator('input[name="position_id"]') }).first();
  await expect(form).toBeVisible();
  const nameInput = form.locator('input[name="position_name"]');
  const currentName = await nameInput.inputValue();
  expect(currentName.trim().length).toBeGreaterThan(0);
  const positionId = await form.locator('input[name="position_id"]').inputValue();
  expect(Number(positionId)).toBeGreaterThan(0);

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.screenshot({ path: path.join(evidenceRoot, 'direction-positions-before-1440.png'), fullPage: true });
  await page.setViewportSize({ width: 375, height: 900 });
  await page.screenshot({ path: path.join(evidenceRoot, 'direction-positions-before-375.png'), fullPage: true });
  const postPromise = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return response.request().method() === 'POST' && url.origin === 'http://portal3.test' && url.pathname === positionPostPath;
  });
  await form.getByRole('button', { name: 'Guardar', exact: true }).click();
  const response = await postPromise;
  expect(response.status()).toBe(303);
  expect(postTrace).toEqual([{ method: 'POST', origin: 'http://portal3.test', path: positionPostPath }]);
  await expect(page.locator('.alert-success[role="status"]')).toContainText('Puesto actualizado correctamente.');
  await expect(positionCard.locator(`input#position-${positionId}`)).toHaveValue(currentName);

  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(page.locator(`#people-positions input#position-${positionId}`)).toHaveValue(currentName);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.screenshot({ path: path.join(evidenceRoot, 'direction-positions-after-reload-1440.png'), fullPage: true });
  await page.setViewportSize({ width: 375, height: 900 });
  await page.screenshot({ path: path.join(evidenceRoot, 'direction-positions-after-reload-375.png'), fullPage: true });
  testInfo.annotations.push({ type: 'candidate-screenshots', description: evidenceRoot });
  testInfo.annotations.push({ type: 'write-scope', description: `Existing position ID ${positionId} re-saved with the same current name; no new catalog entry.` });
});
