// Static server for dist/ with a configurable Content-Security-Policy, to see what the editor needs.
import http from 'node:http';
import { readFileSync, existsSync } from 'node:fs';
import { extname, join } from 'node:path';
const types = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json' };
export function serve(csp, port) {
  const s = http.createServer((req, res) => {
    const p = join('dist', req.url === '/' ? 'index.html' : req.url.split('?')[0]);
    if (!existsSync(p)) { res.writeHead(404); return res.end(); }
    const h = { 'content-type': types[extname(p)] ?? 'application/octet-stream' };
    if (csp) h['content-security-policy'] = csp;
    res.writeHead(200, h); res.end(readFileSync(p));
  });
  return new Promise((r) => s.listen(port, () => r(s)));
}
