import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { e2eRoot, qaProfiles, repoRoot } from '../paths.mjs';

export function readProfileFacts(profileKey) {
  const profile = qaProfiles.find(({ key }) => key === profileKey);
  if (!profile) throw new Error('Unsupported E2E profile.');
  let result;
  try {
    result = execFileSync('php', [path.join(e2eRoot, 'scripts/read-profile-facts.php'), profileKey], {
      cwd: repoRoot,
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
      timeout: 15_000,
    });
  } catch {
    throw new Error('Local account and ACL facts could not be read safely.');
  }
  let facts;
  try {
    facts = JSON.parse(result);
  } catch {
    throw new Error('Local account and ACL facts were not valid JSON.');
  }
  if (!facts.ok || facts.user_id !== profile.expectedUserId || facts.position_id !== profile.expectedPositionId) {
    throw new Error(`The active local account or position does not match profile ${profileKey}.`);
  }
  return facts;
}

export function normalizedIdentity(value) {
  return String(value || '').normalize('NFKC').replace(/\s+/g, ' ').trim().toLocaleLowerCase('es-MX');
}
