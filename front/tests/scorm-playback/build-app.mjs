// Builds the harness page with vite (a dependency of the legacy front). The page imports
// front/src/lib/scorm-player/player.tsx and `scorm-again` from node_modules, so it runs the code and
// the library version the apps ship. Output goes to front/test-results/scorm-playback/<app>.
import react from '@vitejs/plugin-react';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { build } from 'vite';

const here = dirname(fileURLToPath(import.meta.url));
const front = resolve(here, '..', '..');

export async function buildHarness(app) {
  const outDir = join(front, 'test-results', 'scorm-playback', app);
  await build({
    root: join(here, 'app'),
    configFile: false,
    logLevel: 'warn',
    base: './',
    plugins: [react()],
    // the page lives under tests/ but imports from src/ and node_modules
    server: { fs: { allow: [front, resolve(front, '..')] } },
    build: { outDir, emptyOutDir: true, sourcemap: false, minify: false, target: 'es2020' },
  });
  return outDir;
}
