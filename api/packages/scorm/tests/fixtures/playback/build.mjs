#!/usr/bin/env node
// Builds the playback fixture packages (MIT, written for ulams):
//   node api/packages/scorm/tests/fixtures/playback/build.mjs          rewrite packages/*.zip
//   node api/packages/scorm/tests/fixtures/playback/build.mjs --check  fail when a zip is out of date
// Each package directory holds imsmanifest.xml, index.html and sco.js; `_shared/runner.js` is added
// to every package. The zips are committed because the PHPUnit and browser tests only read them.
import { existsSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { writeZip } from './zip.mjs';

export const here = dirname(fileURLToPath(import.meta.url));
export const packagesDir = join(here, 'packages');

export function packageNames() {
  return readdirSync(here, { withFileTypes: true })
    .filter((entry) => entry.isDirectory() && !entry.name.startsWith('_') && entry.name !== 'packages' && entry.name !== 'golden')
    .map((entry) => entry.name)
    .sort();
}

/** The files that go into one package zip. */
export function packageFiles(name) {
  const files = {};
  for (const file of readdirSync(join(here, name))) {
    files[file] = readFileSync(join(here, name, file));
  }
  files['runner.js'] = readFileSync(join(here, '_shared', 'runner.js'));
  return files;
}

export function buildZip(name) {
  return writeZip(packageFiles(name));
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const check = process.argv.includes('--check');
  let stale = 0;
  mkdirSync(packagesDir, { recursive: true });
  for (const name of packageNames()) {
    const target = join(packagesDir, `${name}.zip`);
    const zip = buildZip(name);
    if (check) {
      if (!existsSync(target) || !readFileSync(target).equals(zip)) {
        console.error(`out of date: ${target}`);
        stale++;
      }
    } else {
      writeFileSync(target, zip);
      console.log(`${name}.zip: ${zip.length} bytes`);
    }
  }
  if (stale) process.exit(1);
}
