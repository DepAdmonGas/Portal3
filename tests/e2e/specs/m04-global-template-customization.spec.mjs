import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const route = '/departamento-operativo/recursos-humanos/checador/horarios-turnos';
const postRoute = '/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas-locales/personalizar-global';
const sourceKey = 'LEGACY_IL1_200000_060000';
const qaName = 'QA Personalización Turno Nocturno 20261104';
const artifactDir = '/home/silvinol/.local/state/portal3-e2e/artifacts/candidate-screenshots/m04-global-template-customization-20261104';

async function postCustomization(page, workplaceId = 1) {
  return page.evaluate(async ({ postRoute, sourceKey, qaName, workplaceId }) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || document.querySelector('#m04-template-catalog')?.dataset.csrfToken || '';
    const segments = [{ ordinal: 1, tipo_segmento: 'WORK', hora_inicio_local: '20:00:00', hora_fin_local: '06:00:00', dia_offset_inicio: 0, dia_offset_fin: 1 }];
    try {
      const response = await window.axios.post(postRoute, { workplace_id: workplaceId, source_template_key: sourceKey, source_template_version: 1, template_name: qaName, segments }, {
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
      });
      return { status: response.status, body: response.data };
    } catch (error) { return { status: error.response?.status || 0, body: error.response?.data || {} }; }
  }, { postRoute, sourceKey, qaName, workplaceId });
}

test('Encargado creates one scoped copy of a visible global turn and duplicate retry reads it back', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'encargado');
  mkdirSync(artifactDir, { recursive: true, mode: 0o700 });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(route);
  const catalog = page.locator('#m04-template-catalog');
  await expect(catalog).toHaveAttribute('data-workplace-id', '1');
  await expect(page.locator('#m04-btn-new-template')).toBeVisible();
  const sourceRow = page.locator(`#m04-template-table tbody tr[data-template-key="${sourceKey}"]`);
  await expect(sourceRow).toBeVisible();
  let first;
  const existingLocal = page.locator('#m04-template-table tbody tr').filter({ hasText: qaName });
  if (await existingLocal.count()) {
    const retry = await postCustomization(page);
    expect(retry.status).toBe(200);
    first = retry.body;
  } else {
    await sourceRow.locator('a[data-bs-toggle="dropdown"]').click();
    await page.getByRole('link', { name: 'Personalizar para mi estación' }).click();
    const modal = page.locator('#m04-create-template-modal');
    await expect(modal).toBeVisible();
    await expect(modal).toContainText('El horario global original y las asignaciones actuales permanecerán sin cambios.');
    await page.screenshot({ path: `${artifactDir}/encargado-customization-modal-1440.png`, fullPage: true });
    await page.locator('#m04-turn-name').fill(qaName);
    const [response] = await Promise.all([
      page.waitForResponse(r => r.url().endsWith(postRoute) && r.request().method() === 'POST'),
      modal.getByRole('button', { name: /Guardar turno/ }).click(),
    ]);
    expect(response.status()).toBe(201);
    first = await response.json();
    await expect(modal).toBeHidden();
  }
  expect(first.ok).toBe(true);
  expect(first.template.scope).toBe('STATION');
  expect(first.template.id_estacion).toBe(1);
  expect(first.template.derived_from_global).toEqual({ clave: sourceKey, version: 1 });
  expect(first.template.nombre).toBe(qaName);
  const localRow = page.locator(`#m04-template-table tbody tr[data-template-key="${first.template.clave}"]`);
  await expect(localRow).toBeVisible();
  await expect(localRow).toContainText(qaName);
  await expect(sourceRow).toContainText('Editar personalización');

  if (Number(await localRow.getAttribute('data-template-version')) === 1) {
    await localRow.locator('a[data-bs-toggle="dropdown"]').click();
    await localRow.getByRole('link', { name: /Editar/ }).click();
    const editModal = page.locator('#m04-edit-template-modal');
    await expect(editModal).toBeVisible();
    await expect(page.locator('#m04-edit-turn-name')).toHaveValue(qaName);
    const [versionResponse] = await Promise.all([
      page.waitForResponse(r => r.url().endsWith('/plantillas-locales/versionar') && r.request().method() === 'POST'),
      editModal.getByRole('button', { name: /Guardar cambios/ }).click(),
    ]);
    expect([200, 201]).toContain(versionResponse.status());
    const versionBody = await versionResponse.json();
    expect(versionBody.template.version).toBe(2);
    expect(versionBody.template.derived_from_global).toEqual({ clave: sourceKey, version: 1 });
    await expect(editModal).toBeHidden();
  }
  await page.reload();
  await expect(localRow).toHaveAttribute('data-template-version', '2');

  const duplicate = await postCustomization(page);
  expect(duplicate.status).toBe(200);
  expect(duplicate.body.template.id).toBe(first.template.id);
  expect(duplicate.body.template.clave).toBe(first.template.clave);
  const foreignStation = await postCustomization(page, 2);
  expect(foreignStation.status).toBe(403);
  await page.reload();
  await expect(page.locator(`#m04-template-table tbody tr[data-template-key="${first.template.clave}"]`)).toContainText(qaName);
  for (const width of [1440, 768, 375]) {
    await page.setViewportSize({ width, height: 950 });
    await page.reload();
    await expect(page.locator(`#m04-template-table tbody tr[data-template-key="${first.template.clave}"]`)).toBeVisible();
    await page.screenshot({ path: `${artifactDir}/encargado-${width}.png`, fullPage: true });
  }
  expect(errors).toEqual([]);
});

test('Dirección reads station catalog and has no template write controls', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'direccion-operaciones');
  mkdirSync(artifactDir, { recursive: true, mode: 0o700 });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  for (const width of [1440, 768, 375]) {
    await page.setViewportSize({ width, height: 950 });
    await page.goto(route);
    await expect(page.locator('#m04-template-catalog')).toHaveAttribute('data-workplace-id', '1');
    await expect(page.locator('#m04-btn-new-template')).toHaveCount(0);
    await expect(page.locator('#m04-template-table')).toBeVisible();
    await expect(page.locator('#m04-template-table tbody')).toContainText('QA Personalización Turno Nocturno 20261104');
    await expect(page.locator('#m04-template-table thead')).not.toContainText('Acciones');
    await expect(page.locator('#m04-template-table tbody tr a[data-bs-toggle="dropdown"]')).toHaveCount(0);
    await page.screenshot({ path: `${artifactDir}/direccion-${width}.png`, fullPage: true });
  }
  const deniedWrite = await postCustomization(page);
  expect(deniedWrite.status).toBe(403);
  expect(errors).toEqual([]);
});
