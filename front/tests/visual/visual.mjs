#!/usr/bin/env node
// Visual-regression harness for front (learner app) and admin.
//
//   node front/tests/visual/visual.mjs prepare
//   node front/tests/visual/visual.mjs capture --out <dir> [--only <regex>] [--targets a,b] [--viewports desktop,mobile]
//   node front/tests/visual/visual.mjs compare --base <dir> --after <dir> [--report <dir>] [--threshold 0.5]
//
// See README.md next to this file.
import path from 'node:path';
import { parseArgs } from 'node:util';
import { captureAll } from './lib/capture.mjs';
import { compareDirs } from './lib/compare.mjs';
import { buildConfig, api, apiLogin, credentials, VISUAL_PAGE, PLATFORM_API } from './pages.mjs';

const VIEWPORTS = {
  desktop: { name: 'desktop', width: 1440, height: 900 },
  mobile: { name: 'mobile', width: 390, height: 844, isMobile: true },
};

const { positionals, values } = parseArgs({
  allowPositionals: true,
  options: {
    out: { type: 'string' },
    base: { type: 'string' },
    after: { type: 'string' },
    report: { type: 'string' },
    threshold: { type: 'string', default: '0.5' },
    'pixel-threshold': { type: 'string', default: '0.1' },
    only: { type: 'string' },
    targets: { type: 'string' },
    viewports: { type: 'string', default: 'desktop,mobile' },
    locale: { type: 'string', default: 'pl-PL' },
    'fixed-time': { type: 'string', default: process.env.VISUAL_FIXED_TIME ?? '' },
  },
});

const cmd = positionals[0];

async function prepare() {
  // Enrols each target's student in the course the harness screenshots and makes sure a
  // static page exists. Idempotent; touches only the dev databases.
  const config = await buildConfig();
  for (const t of config.targets.filter((x) => x.name.startsWith('front-'))) {
    const slug = t.name.replace(/^front-/, '');
    const adminCred = slug === 'platform' ? credentials.platformAdmin : credentials.tenant(slug, 'admin');
    try {
      const adminToken = await apiLogin(t.apiUrl, adminCred);
      const studentToken = await apiLogin(t.apiUrl, t.studentCred);
      const me = await api(t.apiUrl, 'GET', '/api/profile/me', { token: studentToken });
      const studentId = me.json?.data?.id;
      if (t.ids.courseId && studentId) {
        const r = await api(t.apiUrl, 'POST', `/api/admin/courses/${t.ids.courseId}/access/add`, {
          token: adminToken,
          body: { users: [studentId] },
        });
        console.log(`${t.name}: enrol user ${studentId} in course ${t.ids.courseId} -> ${r.status}`);
      } else {
        console.log(`${t.name}: skip enrol (course ${t.ids.courseId}, user ${studentId})`);
      }
      const pages = (await api(t.apiUrl, 'GET', '/api/admin/pages?per_page=100', { token: adminToken })).json?.data ?? [];
      if (!pages.some((p) => p.slug === VISUAL_PAGE.slug)) {
        const r = await api(t.apiUrl, 'POST', '/api/admin/pages', { token: adminToken, body: VISUAL_PAGE });
        console.log(`${t.name}: create static page "${VISUAL_PAGE.slug}" -> ${r.status}`);
      } else {
        console.log(`${t.name}: static page "${VISUAL_PAGE.slug}" exists`);
      }
    } catch (e) {
      console.log(`${t.name}: prepare failed: ${e.message}`);
    }
  }
  if (!config.targets.some((t) => t.apiUrl === PLATFORM_API)) console.log('platform target disabled');
}

async function capture() {
  if (!values.out) throw new Error('--out <dir> is required');
  const viewports = values.viewports.split(',').map((v) => {
    if (!VIEWPORTS[v]) throw new Error(`unknown viewport ${v}`);
    return VIEWPORTS[v];
  });
  const config = await buildConfig();
  const results = await captureAll(config, {
    outDir: path.resolve(values.out),
    viewports,
    only: values.only,
    targets: values.targets?.split(','),
    locale: values.locale,
    fixedTime: values['fixed-time'] || undefined,
  });
  const ok = results.filter((r) => r.status === 'ok').length;
  console.log(`\ncaptured ${ok}/${results.length} screenshots into ${path.resolve(values.out)}`);
  for (const r of results.filter((x) => x.status !== 'ok')) console.log(`  ${r.status}: ${r.file} ${r.error ?? ''}`);
}

function compare() {
  if (!values.base || !values.after) throw new Error('--base and --after are required');
  const reportDir = path.resolve(values.report ?? path.join(path.dirname(path.resolve(values.after)), 'report'));
  const { summary, rows } = compareDirs({
    baseDir: path.resolve(values.base),
    afterDir: path.resolve(values.after),
    reportDir,
    threshold: Number(values.threshold),
    pixelThreshold: Number(values['pixel-threshold']),
  });
  for (const r of rows.filter((x) => x.status !== 'same')) {
    console.log(`  ${r.status.padEnd(13)} ${(r.diffPercent ?? 0).toFixed(3).padStart(8)}%  ${r.rel}`);
  }
  console.log(
    `\ncompared ${summary.compared}, above ${summary.threshold}%: ${summary.changed}, max ${summary.maxDiffPercent.toFixed(3)}%, mean ${summary.meanDiffPercent.toFixed(3)}%`,
  );
  console.log(`report: ${path.join(reportDir, 'report.html')}`);
  process.exitCode = summary.changed ? 1 : 0;
}

const commands = { prepare, capture, compare };
if (!commands[cmd]) {
  console.error('usage: visual.mjs <prepare|capture|compare> [options]  (see front/tests/visual/README.md)');
  process.exit(2);
}
await commands[cmd]();
