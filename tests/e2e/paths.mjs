import os from 'node:os';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
export const e2eRoot = here;
export const repoRoot = path.resolve(here, '../..');

const defaultStateRoot = process.env.XDG_STATE_HOME
  ? path.join(process.env.XDG_STATE_HOME, 'portal3-e2e')
  : path.join(os.homedir(), '.local', 'state', 'portal3-e2e');

export const stateRoot = path.resolve(process.env.PORTAL3_E2E_STATE_DIR || defaultStateRoot);
const relativeStateRoot = path.relative(repoRoot, stateRoot);
if (relativeStateRoot === '' || (!relativeStateRoot.startsWith(`..${path.sep}`) && relativeStateRoot !== '..' && !path.isAbsolute(relativeStateRoot))) {
  throw new Error('PORTAL3_E2E_STATE_DIR must be outside the repository; cookies and browser state cannot be stored in the checkout.');
}

export const authRoot = path.join(stateRoot, 'auth');
export const artifactsRoot = path.join(stateRoot, 'artifacts');
export const candidateScreenshotsRoot = path.join(artifactsRoot, 'candidate-screenshots');
export const baseURL = 'http://portal3.test';
export const attendancePath = '/departamento-operativo/recursos-humanos/checador/asistencia';

export const qaProfiles = [
  {
    key: 'encargado',
    expectedUserId: 33,
    expectedPositionId: 6,
    rolePattern: /^encargado$/iu,
  },
  {
    key: 'encargado-palo-solo',
    expectedUserId: 52,
    expectedPositionId: 6,
    rolePattern: /^encargado$/iu,
  },
  {
    key: 'direccion-operaciones',
    expectedUserId: 19,
    expectedPositionId: 13,
    rolePattern: /^dirección de operaciones$/iu,
  },
];

export function authStatePath(profileKey) {
  if (!qaProfiles.some((profile) => profile.key === profileKey)) {
    throw new Error(`Unsupported QA profile: ${profileKey}`);
  }
  return path.join(authRoot, `${profileKey}.json`);
}

export function ensurePrivateDirectory(directory) {
  fs.mkdirSync(directory, { recursive: true, mode: 0o700 });
  fs.chmodSync(directory, 0o700);
}
