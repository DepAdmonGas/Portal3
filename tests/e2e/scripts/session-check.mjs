import { attendancePath, baseURL, qaProfiles } from '../paths.mjs';
import { normalizedIdentity, readProfileFacts } from './profile-facts.mjs';

export async function installLocalOriginGuard(page, blockedOrigins = []) {
  await page.route('**/*', async (route) => {
    const parsed = new URL(route.request().url());
    if (['http:', 'https:'].includes(parsed.protocol) && parsed.hostname !== 'portal3.test') {
      blockedOrigins.push(parsed.origin);
      await route.abort('blockedbyclient');
      return;
    }
    await route.continue();
  });
}

export async function verifyPortalSession(page, profileKey, options = {}) {
  const profile = qaProfiles.find(({ key }) => key === profileKey);
  if (!profile) return { valid: false, reason: 'unsupported_profile' };
  let facts;
  try {
    facts = readProfileFacts(profileKey);
  } catch {
    return { valid: false, reason: 'local_account_acl_unavailable' };
  }
  const expectedLocalityId = profileKey === 'encargado-palo-solo' ? 2 : 1;
  const expectedLocalityName = profileKey === 'encargado-palo-solo' ? 'Palo Solo' : 'Interlomas';
  if (!facts.checador_read || !facts.localities.some((item) => item.id === expectedLocalityId && item.name === expectedLocalityName)) {
    return { valid: false, reason: 'checador_or_interlomas_acl_denied' };
  }
  const blockedOrigins = Array.isArray(options.blockedOrigins) ? options.blockedOrigins : [];
  if (!Array.isArray(options.blockedOrigins)) await installLocalOriginGuard(page, blockedOrigins);
  let response;
  try {
    response = await page.goto(`${baseURL}${attendancePath}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  } catch {
    return { valid: false, reason: 'attendance_unreachable' };
  }
  if (!response || response.status() >= 400) return { valid: false, reason: 'attendance_http_error' };
  if (new URL(page.url()).pathname === '/login') return { valid: false, reason: 'session_expired' };

  const roleElement = page.locator('.fixed-profile .john-title span');
  if (!await roleElement.count()) return { valid: false, reason: 'authenticated_shell_missing' };
  const roleText = (await roleElement.first().innerText()).trim();
  if (!profile.rolePattern.test(roleText)) return { valid: false, reason: 'role_mismatch' };
  const visibleName = page.locator('.fixed-profile .john-title h6');
  if (!await visibleName.count()
    || normalizedIdentity(await visibleName.first().innerText()) !== normalizedIdentity(facts.display_name_prefix)) {
    return { valid: false, reason: 'account_identity_mismatch' };
  }

  const attendanceHeading = page.getByRole('heading', { name: 'Asistencia del día' });
  if (!await attendanceHeading.count() || !await attendanceHeading.first().isVisible()) {
    return { valid: false, reason: 'checador_read_denied' };
  }
  const selector = page.locator('#module-station-selector-checador');
  if (await selector.count() && profileKey !== 'encargado-palo-solo') {
    const interlomasOption = selector.locator('option').filter({ hasText: /^Interlomas(?:\s|$)/i });
    if (await interlomasOption.count() !== 1) return { valid: false, reason: 'interlomas_option_missing' };
    const value = await interlomasOption.getAttribute('value');
    if (!value) return { valid: false, reason: 'interlomas_option_invalid' };
    if (await selector.inputValue() !== value) {
      const navigation = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30_000 });
      await selector.selectOption(value);
      try { await navigation; } catch { return { valid: false, reason: 'interlomas_context_not_saved' }; }
    }
  } else if (profile.expectedPositionId === 13) {
    return { valid: false, reason: 'explicit_station_selector_missing' };
  }
  if (!(await page.locator('body').innerText()).includes(expectedLocalityName)) return { valid: false, reason: 'interlomas_scope_missing' };
  return {
    valid: true,
    reason: 'authorized',
    roleText,
    facts,
    externalDependenciesBlocked: [...new Set(blockedOrigins)],
  };
}
