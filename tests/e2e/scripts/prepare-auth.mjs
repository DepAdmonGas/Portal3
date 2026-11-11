import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { authStatePath, baseURL, ensurePrivateDirectory, qaProfiles } from '../paths.mjs';
import { installLocalOriginGuard, verifyPortalSession } from './session-check.mjs';

const profileKey = process.argv[2];
const profile = qaProfiles.find((item) => item.key === profileKey);
if (!profile) {
  console.error('Uso: npm --prefix tests/e2e run auth -- encargado|direccion-operaciones');
  process.exit(2);
}

const outputPath = authStatePath(profile.key);
ensurePrivateDirectory(path.dirname(outputPath));
console.log(`Se abrirá Chrome aislado para iniciar sesión manualmente en ${baseURL}/login.`);
console.log(`Inicia sesión con la cuenta QA autorizada para ${profile.key}; no compartas credenciales en el chat.`);

const browser = await chromium.launch({ channel: 'chrome', headless: false });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
const blockedOrigins = [];
const loginHttp = [];
try {
  await installLocalOriginGuard(page, blockedOrigins);
  page.on('request', (request) => {
    const url = new URL(request.url());
    if (url.origin === baseURL && request.method() === 'POST' && url.pathname === '/login/acceso') {
      loginHttp.push({ method: request.method(), path: url.pathname, status: null, success: null, type: null });
    }
  });
  page.on('response', async (response) => {
    const url = new URL(response.url());
    if (url.origin !== baseURL || url.pathname !== '/login/acceso' || response.request().method() !== 'POST') return;
    const item = loginHttp.at(-1);
    if (!item) return;
    item.status = response.status();
    try {
      const body = await response.json();
      item.success = body?.success === true;
      item.type = typeof body?.type === 'string' ? body.type : null;
    } catch {}
  });
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  // The local auth layout references Alpine and Axios only from CDNs. Use the
  // already bundled same-origin copies for this isolated diagnostic; the route
  // guard still aborts every external request.
  await page.addScriptTag({ url: `${baseURL}/assets/libs/alpinejs/dist/alpinejs.min.js` });
  await page.addScriptTag({ url: `${baseURL}/assets/libs/axios/dist/axios.min.js` });
  await page.waitForFunction(() => Boolean(window.Alpine && window.axios));
  console.log('AUTH_LOCAL_DEPENDENCIES alpine=/assets/libs/alpinejs/dist/alpinejs.min.js axios=/assets/libs/axios/dist/axios.min.js');
  await page.waitForFunction(() => {
    const role = document.querySelector('.fixed-profile .john-title span')?.textContent?.trim() || '';
    const error = [...document.querySelectorAll('[role="alert"]')]
      .some((element) => element.offsetParent !== null
        && element.textContent.trim().length > 0
        && (element.classList.contains('alert-danger') || element.classList.contains('alert-warning')));
    return role.length > 0 || error;
  }, undefined, { timeout: 10 * 60 * 1000 });
  let authenticatedRole = await page.locator('.fixed-profile .john-title span').count();
  if (!authenticatedRole && loginHttp.at(-1)?.success === true) {
    await page.waitForFunction(() => Boolean(document.querySelector('.fixed-profile .john-title span')?.textContent?.trim()), undefined, { timeout: 30_000 });
    authenticatedRole = await page.locator('.fixed-profile .john-title span').count();
  }
  if (!authenticatedRole) {
    const response = loginHttp.at(-1);
    const visibleMessage = (await page.locator('[role="alert"]:visible').last().innerText().catch(() => '')).trim();
    const errorKind = /usuario o contrase(?:ñ|n)a incorrectos/i.test(visibleMessage)
      ? 'generic_credentials_rejected'
      : visibleMessage ? 'visible_login_error' : 'login_response_missing';
    console.log(`AUTH_HTTP_TRACE ${JSON.stringify(loginHttp.map(({ method, path, status, success, type }) => ({ method, path, status, success, type })))}`);
    throw new Error(`login_not_authenticated result=${errorKind} http=${response?.status ?? 'no_post_response'}`);
  }
  const roleText = (await page.locator('.fixed-profile .john-title span').innerText()).trim();
  if (!profile.rolePattern.test(roleText)) throw new Error(`El puesto autenticado no coincide con el perfil ${profile.key}. No se guardó la sesión.`);
  console.log(`AUTH_HTTP_TRACE ${JSON.stringify(loginHttp.map(({ method, path, status, success, type }) => ({ method, path, status, success, type })))}`);
  const access = await verifyPortalSession(page, profile.key, { blockedOrigins });
  if (!access.valid) throw new Error(`La sesión no tiene acceso verificado a Asistencia de Interlomas (${access.reason}). No se guardó.`);
  const state = await context.storageState();
  fs.writeFileSync(outputPath, JSON.stringify(state, null, 2), { mode: 0o600 });
  fs.chmodSync(outputPath, 0o600);
  console.log(`Sesión local para ${profile.key} guardada fuera del repositorio: ${outputPath}`);
  console.log('El archivo contiene estado de autenticación sensible; no lo compartas ni lo agregues a Git.');
  if (blockedOrigins.length > 0) {
    console.log(`DEPENDENCIES_BLOCKED_LOCAL_ONLY count=${blockedOrigins.length}; no se permitió ninguna conexión externa.`);
  }
} catch (error) {
  console.error(`No se guardó una sesión: ${error.message}`);
  process.exitCode = 1;
} finally {
  await context.close();
  await browser.close();
}
