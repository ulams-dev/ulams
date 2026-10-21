#!/usr/bin/env node
/**
 * Size budget of the visual e-mail editor (ADR 0107, plan M3).
 *
 * The editor (GrapesJS + grapesjs-mjml + mjml-browser) is a lazy chunk. This script reads the
 * production build in dist/, finds the files that carry the editor, and fails when
 *   - the gzip size of those files exceeds the budget (700 KB), or
 *   - any of them is loaded by index.html, which would put the editor in the main bundle, or
 *   - no file carries the editor at all (the marker is stale or the build changed).
 *
 * Runs as `postbuild` of the admin, so every CI build checks it. `yarn budget` runs it alone.
 */
const fs = require('node:fs');
const path = require('node:path');
const zlib = require('node:zlib');

/** 700 KB gzip, the budget the owner accepted in ADR 0107. */
const BUDGET_BYTES = 700 * 1024;
/** Text that only the editor chunks contain: a class of the GrapesJS style manager, the plugin banner. */
const MARKERS = ['gjs-padding-v-top', '/*! grapesjs-mjml'];

const gzipSize = (buffer) => zlib.gzipSync(buffer, { level: 9 }).length;

/**
 * @param {{ name: string, content: Buffer }[]} files built .js and .css files
 * @param {string} indexHtml content of dist/index.html
 */
const checkBudget = (files, indexHtml = '') => {
  const editor = files.filter((file) => {
    const text = file.content.toString('utf8');
    return MARKERS.some((marker) => text.includes(marker));
  });
  // CSS of the editor is named after its chunk; count the stylesheet that carries the grapes rules.
  const css = files.filter(
    (file) => file.name.endsWith('.css') && file.content.toString('utf8').includes('.gjs-'),
  );
  const counted = [...new Set([...editor, ...css])];
  const total = counted.reduce((sum, file) => sum + gzipSize(file.content), 0);
  const problems = [];
  if (editor.length === 0) problems.push('no built file contains the visual editor');
  const inMain = editor.filter((file) => indexHtml.includes(path.basename(file.name)));
  if (inMain.length) {
    problems.push(
      `the editor is part of the entry bundle: ${inMain.map((f) => f.name).join(', ')}`,
    );
  }
  if (total > BUDGET_BYTES) {
    problems.push(
      `editor chunk is ${(total / 1024).toFixed(0)} KB gzip, budget is ${BUDGET_BYTES / 1024} KB`,
    );
  }
  return { total, files: counted.map((f) => f.name), problems };
};

const walk = (dir) =>
  fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    return entry.isDirectory() ? walk(full) : [full];
  });

const run = (dist = path.join(__dirname, '..', 'dist')) => {
  if (!fs.existsSync(dist)) {
    console.error(`editor budget: ${dist} does not exist, run the build first`);
    return 1;
  }
  const files = walk(dist)
    .filter((file) => /\.(js|css)$/.test(file) && !file.endsWith('.map'))
    .map((file) => ({ name: path.relative(dist, file), content: fs.readFileSync(file) }));
  const indexPath = path.join(dist, 'index.html');
  const indexHtml = fs.existsSync(indexPath) ? fs.readFileSync(indexPath, 'utf8') : '';
  const result = checkBudget(files, indexHtml);
  console.log(
    `editor budget: ${(result.total / 1024).toFixed(0)} KB gzip of ${BUDGET_BYTES / 1024} KB in ${
      result.files.length
    } file(s): ${result.files.join(', ')}`,
  );
  result.problems.forEach((problem) => console.error(`editor budget: ${problem}`));
  return result.problems.length ? 1 : 0;
};

if (require.main === module) process.exit(run(process.argv[2]));

module.exports = { BUDGET_BYTES, checkBudget, gzipSize };
