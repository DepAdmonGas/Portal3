import { defineConfig } from '@playwright/test';
import { authStatePath, candidateScreenshotsRoot, ensurePrivateDirectory } from './paths.mjs';

ensurePrivateDirectory(candidateScreenshotsRoot);

export default defineConfig({
  testDir: './specs',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: 'list',
  timeout: 90_000,
  use: {
    baseURL: 'http://portal3.test',
    channel: 'chrome',
    headless: true,
    viewport: { width: 1440, height: 1000 },
    ignoreHTTPSErrors: false,
    trace: 'retain-on-failure',
  },
  outputDir: candidateScreenshotsRoot,
  projects: [
    { name: 'encargado', use: { storageState: authStatePath('encargado') } },
    { name: 'encargado-palo-solo', use: { storageState: authStatePath('encargado-palo-solo') } },
    { name: 'direccion-operaciones', use: { storageState: authStatePath('direccion-operaciones') } },
  ],
});
