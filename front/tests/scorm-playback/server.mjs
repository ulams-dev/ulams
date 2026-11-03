// Static server and mock LMS API for the React player SCORM playback specs.
//   node tests/scorm-playback/server.mjs --app front|admin --port 4610
// It serves the harness page (built with vite on start), the app's own service worker and its
// modules (front/public or admin/public), the fixture zips, and the three LMS endpoints the
// player calls. Everything is local: no network is needed.
import { createReadStream, existsSync, readFileSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { dirname, extname, join, normalize, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { readZip } from '../../../api/packages/scorm/tests/fixtures/playback/zip.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..', '..', '..');
export const packagesDir = join(root, 'api', 'packages', 'scorm', 'tests', 'fixtures', 'playback', 'packages');
const scormPackage = join(root, 'api', 'packages', 'scorm');
// what ScormContentService::publishPlayer puts on the content origin under scorm/_player/
const PLAYER_FILES = {
  'player.html': join(scormPackage, 'resources', 'content-player', 'player.html'),
  'player.js': join(scormPackage, 'resources', 'content-player', 'player.js'),
  'scorm-again.min.js': process.env.SCORM_AGAIN_DIR
    ? join(resolve(process.env.SCORM_AGAIN_DIR), 'dist', 'scorm-again.min.js')
    : join(scormPackage, 'resources', 'js', 'vendor', 'scorm-again', 'scorm-again.min.js'),
};
export const TRACKING_TOKEN = 'fixture-token';

const TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.zip': 'application/zip',
  '.json': 'application/json',
  '.map': 'application/json',
};

/** The response of GET /api/scorm/show/{uuid}: the SCO settings the player hands to scorm-again. */
export function showResponse(origin, uuid) {
  const [fixture, query = ''] = uuid.split('~');
  const entry = `index.html${query ? `?${query}` : ''}`;
  return {
    success: true,
    data: {
      uuid,
      identifier: 'item1',
      title: fixture,
      entry_url: entry,
      entry_url_zip: `${origin}/zips/${fixture}.zip`,
      entry_url_absolute: `${origin}/zips/${fixture}/${entry}`,
      version: fixture.startsWith('scorm2004') ? 'scorm_2004' : 'scorm_12',
      token: null,
      lmsUrl: `${origin}/api/scorm/track`,
      player: {
        autoCommit: false,
        lmsCommitUrl: false,
        xhrHeaders: { Authorization: null },
        logLevel: 1,
        autoProgress: false,
        cmi: {},
      },
    },
  };
}

/** Same text as `filler()` in the fixtures' runner.js. */
function filler(length) {
  const unit = 'ulams-suspend-data-0123456789|';
  let out = '';
  while (out.length < length) out += unit;
  return out.slice(0, length);
}

/**
 * The `cmi` of GET /api/scorm/content/{sco}: what Scorm12FieldStrategy / Scorm2004FieldStrategy::getCmiData
 * return for a tracking row, with a canned row. `fresh` is a learner's first launch, `resume` a learner who
 * suspended the first session of the fixture, `mastery` is `fresh` plus a mastery score, which the API does
 * not send today (the strategies have no field for it); it documents what the player would do if it did.
 */
export function launchCmi(fixture, variant) {
  const is2004 = fixture.startsWith('scorm2004');
  if (is2004) {
    const fresh = {
      learner_id: '42', progress_measure: '0', 'score.raw': '', 'score.min': '', 'score.max': '', 'score.scaled': '',
      success_status: 'unknown', completion_status: 'unknown', session_time: 'PT0S', total_time: 'PT0S',
      entry: 'ab-initio', suspend_data: '', credit: 'credit', exit: '', location: '', mode: 'normal',
    };
    if (variant === 'resume') {
      return {
        ...fresh, progress_measure: '0.75', 'score.raw': '85', 'score.min': '0', 'score.max': '100',
        'score.scaled': '0.85', completion_status: 'incomplete', total_time: 'PT1M30S', entry: 'resume',
        suspend_data: filler(8192), exit: 'suspend', location: 'chapter-2/page-3',
      };
    }
    return fresh;
  }
  const fresh = {
    suspend_data: '', 'core.student_id': '42', 'core.lesson_location': '', 'core.credit': 'credit',
    'core.lesson_status': 'not attempted', 'core.entry': 'ab-initio', 'core.lesson_mode': 'normal', 'core.exit': '',
    'core.score.raw': '', 'core.score.min': '', 'core.score.max': '', 'core.total_time': '0000:00:00.00',
  };
  if (variant === 'resume') {
    return {
      ...fresh, suspend_data: filler(4096), 'core.lesson_location': 'chapter-2/page-3', 'core.lesson_status': 'incomplete',
      'core.entry': 'resume', 'core.exit': 'suspend', 'core.score.raw': '85', 'core.score.min': '0',
      'core.score.max': '100', 'core.total_time': '0000:01:30.00',
    };
  }
  if (variant === 'mastery') return { ...fresh, 'student_data.mastery_score': '70' };
  return fresh;
}

/** The response of GET /api/scorm/content/{sco} (ScormContentService::launchData). sco = fixture~variant~query. */
export function launchResponse(sco) {
  const [fixture, variant = 'fresh', query = ''] = sco.split('~');
  const version = fixture.startsWith('scorm2004') ? 'scorm_2004' : 'scorm_12';
  const entry = variant === 'foreign-entry'
    ? 'http://localhost:9/scorm/elsewhere/index.html'
    : `/scorm/${version}/${fixture}/index.html${query ? `?${query}` : ''}`;
  return {
    success: true,
    message: 'Launch data',
    data: {
      uuid: sco,
      title: fixture,
      version,
      entry_url: entry,
      cmi: launchCmi(fixture, variant),
      player: { logLevel: 1, autoProgress: true },
    },
  };
}

export function startServer({ app, port, appDir, publicDir }) {
  const requests = [];
  const server = createServer((req, res) => {
    const url = new URL(req.url ?? '/', 'http://localhost');
    const send = (status, body, type = 'application/json') => {
      res.writeHead(status, { 'Content-Type': type, 'Cache-Control': 'no-store' });
      res.end(body);
    };
    const sendFile = (file) => {
      if (!existsSync(file) || !statSync(file).isFile()) return send(404, 'not found', 'text/plain');
      res.writeHead(200, { 'Content-Type': TYPES[extname(file)] ?? 'application/octet-stream', 'Cache-Control': 'no-store' });
      if (req.method === 'HEAD') return res.end();
      return createReadStream(file).pipe(res);
    };
    const inside = (base, relative) => {
      const file = normalize(join(base, relative));
      return file.startsWith(base) ? file : join(base, '__invalid__');
    };

    if (url.pathname === '/__mock/requests') return send(200, JSON.stringify([...requests].sort()));
    if (url.pathname === '/__mock/reset') {
      requests.length = 0;
      return send(200, '{}');
    }
    if (url.pathname.startsWith('/scorm/_player/')) {
      const file = PLAYER_FILES[url.pathname.slice('/scorm/_player/'.length)];
      return file ? sendFile(file) : send(404, 'not found', 'text/plain');
    }
    // the package files, as the content origin serves them: /scorm/{version}/{package}/{file}
    const packageFile = url.pathname.match(/^\/scorm\/(scorm_12|scorm_2004)\/([\w-]+)\/(.+)$/);
    if (packageFile) {
      const zip = join(packagesDir, `${packageFile[2]}.zip`);
      const entries = existsSync(zip) ? readZip(readFileSync(zip)) : {};
      const data = entries[packageFile[3]];
      if (!data) return send(404, 'not found', 'text/plain');
      res.writeHead(200, { 'Content-Type': TYPES[extname(packageFile[3])] ?? 'application/octet-stream', 'Cache-Control': 'no-store' });
      return res.end(req.method === 'HEAD' ? undefined : data);
    }
    if (url.pathname.startsWith('/api/')) {
      requests.push(`${req.method} ${decodeURIComponent(url.pathname)}`);
      const content = url.pathname.match(/^\/api\/scorm\/content\/([^/]+)(\/track)?$/);
      if (content) {
        if (req.headers['x-ulams-tracking-token'] !== TRACKING_TOKEN) return send(401, '{"success":false,"message":"Invalid or expired tracking token"}');
        if (content[2]) {
          if (req.method !== 'POST') return send(405, '{}');
          req.resume();
          return send(200, '{"success":true}');
        }
        const sco = decodeURIComponent(content[1]);
        if (sco.split('~')[1] === 'expired') return send(401, '{"success":false}');
        return send(200, JSON.stringify(launchResponse(sco)));
      }
      const show = url.pathname.match(/^\/api\/scorm\/show\/(.+)$/);
      if (show) return send(200, JSON.stringify(showResponse(`http://${req.headers.host}`, decodeURIComponent(show[1]))));
      if (url.pathname.startsWith('/api/scorm/zip/')) return send(200, '{}');
      return send(404, '{}');
    }
    if (url.pathname.startsWith('/zips/')) return sendFile(inside(packagesDir, url.pathname.slice(6)));
    if (url.pathname === '/service-worker-scorm.js') return sendFile(join(publicDir, 'service-worker-scorm.js'));
    if (url.pathname.startsWith('/modules/')) return sendFile(inside(join(publicDir, 'modules'), url.pathname.slice(9)));
    if (url.pathname === '/' || url.pathname === '/index.html') return sendFile(join(appDir, 'index.html'));
    return sendFile(inside(appDir, url.pathname));
  });
  return new Promise((resolveStart) => {
    server.listen(port, '127.0.0.1', () => resolveStart({ server, app, requests }));
  });
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const args = process.argv.slice(2);
  const option = (name, fallback) => (args.includes(name) ? args[args.indexOf(name) + 1] : fallback);
  const app = option('--app', 'front');
  const port = Number(option('--port', '4610'));
  if (!['front', 'admin'].includes(app)) throw new Error('--app must be front or admin');
  const { buildHarness } = await import('./build-app.mjs');
  const appDir = await buildHarness(app);
  await startServer({ app, port, appDir, publicDir: join(root, app, 'public') });
  console.log(`scorm playback harness (${app}) on http://127.0.0.1:${port}/`);
}
