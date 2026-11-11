import fs from 'node:fs';
import { spawnSync } from 'node:child_process';
import { chromium } from '@playwright/test';
import { authStatePath, e2eRoot, qaProfiles, repoRoot } from '../paths.mjs';
import { verifyPortalSession } from './session-check.mjs';

const phpGate = (authenticatedPass = false) => spawnSync('php', ['tests/portal3_ui_quality_gate.php'], {
  stdio: 'inherit',
  cwd: repoRoot,
  env: authenticatedPass ? { ...process.env, PORTAL3_AUTHENTICATED_E2E_PASS: '1' } : process.env,
});

const validateStoredSession = async (profileKey) => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const context = await browser.newContext({ storageState: authStatePath(profileKey) });
    const page = await context.newPage();
    return await verifyPortalSession(page, profileKey);
  } catch {
    return { valid: false, reason: 'session_state_unreadable' };
  } finally {
    await browser.close();
  }
};

const verifiedProfiles = [];
const blockedProfiles = [];
const failedProfiles = [];

for (const profile of qaProfiles) {
  const statePath = authStatePath(profile.key);
  let status = fs.existsSync(statePath)
    ? await validateStoredSession(profile.key)
    : { valid: false, reason: 'session_missing' };

  if (!status.valid) {
    if (process.env.PORTAL3_E2E_SKIP_INTERACTIVE_AUTH === '1') {
      console.log(`E2E_BLOCKED profile=${profile.key} authentication_not_prepared_interactive_auth_skipped`);
      blockedProfiles.push(profile.key);
      continue;
    }
    console.log(`AUTH_RENEWAL profile=${profile.key} reason=${status.reason}; opening isolated local login.`);
    const renewal = spawnSync(process.execPath, [`${e2eRoot}/scripts/prepare-auth.mjs`, profile.key], {
      stdio: 'inherit',
      cwd: e2eRoot,
    });
    if (renewal.status !== 0) {
      console.log(`E2E_BLOCKED profile=${profile.key} authentication_or_access_not_verified`);
      blockedProfiles.push(profile.key);
      continue;
    }
    status = await validateStoredSession(profile.key);
    if (!status.valid) {
      console.log(`E2E_BLOCKED profile=${profile.key} post_login_validation=${status.reason}`);
      blockedProfiles.push(profile.key);
      continue;
    }
  }
  console.log(`AUTH_PROFILE_VERIFIED profile=${profile.key} identity_role_acl_scope=yes`);
  verifiedProfiles.push(profile.key);
}

for (const profileKey of verifiedProfiles) {
  const result = spawnSync('npm', ['--prefix', 'tests/e2e', 'run', 'test:attendance', '--', '--project', profileKey], {
    stdio: 'inherit',
    cwd: repoRoot,
  });
  if (result.status === 0) {
    console.log(`BROWSER_PROFILE_PASS profile=${profileKey}`);
  } else {
    console.log(`BROWSER_PROFILE_FAIL profile=${profileKey} exit=${result.status ?? 'unknown'}`);
    failedProfiles.push(profileKey);
  }
}

const allProfilesPassed = verifiedProfiles.length === qaProfiles.length
  && blockedProfiles.length === 0
  && failedProfiles.length === 0;
const gate = phpGate(allProfilesPassed);
const passedCount = verifiedProfiles.length - failedProfiles.length;
console.log(`E2E_PROFILE_SUMMARY PASS=${passedCount} FAIL=${failedProfiles.length} BLOCKED=${blockedProfiles.length}`);

if (gate.status !== 0 || failedProfiles.length > 0) process.exit(1);
if (!allProfilesPassed) process.exit(2);
process.exit(0);
