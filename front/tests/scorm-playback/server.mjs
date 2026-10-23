// Static server and mock LMS API for the React player SCORM playback specs.
//   node tests/scorm-playback/server.mjs --app front|admin --port 4610
// It serves the harness page (built with vite on start), the app's own service worker and its
// modules (front/public or admin/public), the fixture zips, and the three LMS endpoints the
// player calls. Everything is local: no network is needed.
import { createReadStream, existsSync, readFileSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { dirname, extname, join, normalize, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..', '..', '..');
export const packagesDir = join(root, 'api', 'packages', 'scorm', 'tests', 'fixtures', 'playback', 'packages');

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

    if (url.pathname === '/__mock/requests') return send(200, JSON.stringify(requests));
    if (url.pathname === '/__mock/reset') {
      requests.length = 0;
      return send(200, '{}');
    }
    if (url.pathname.startsWith('/api/')) {
      requests.push(`${req.method} ${url.pathname}`);
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
