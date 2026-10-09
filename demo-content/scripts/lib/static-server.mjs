// A tiny static file server for packages (dev tools and tests). Serves a folder under a path prefix,
// sets the Content-Security-Policy the API sets for an interactive package (InteractiveCsp) and the
// CORS header the content-origin proxy adds, and counts every request that is not a file of the package.
import { createServer } from "node:http";
import { existsSync, readFileSync, statSync } from "node:fs";
import { extname, join, normalize } from "node:path";

const MIME = {
  ".html": "text/html; charset=utf-8", ".js": "text/javascript; charset=utf-8", ".mjs": "text/javascript; charset=utf-8", ".css": "text/css; charset=utf-8",
  ".json": "application/json", ".svg": "image/svg+xml", ".png": "image/png", ".jpg": "image/jpeg", ".jpeg": "image/jpeg", ".webp": "image/webp",
  ".woff2": "font/woff2", ".woff": "font/woff", ".txt": "text/plain; charset=utf-8", ".md": "text/markdown; charset=utf-8", ".topojson": "application/json", ".geojson": "application/json",
};

/** The CSP the API sets per package version (api/packages/interactive/src/Services/InteractiveCsp.php), network off. */
export const packageCsp = (frameAncestors = "'self'") =>
  `default-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self' data:; connect-src 'self'; worker-src 'self' blob:; frame-src 'none'; frame-ancestors ${frameAncestors}; form-action 'none'; base-uri 'none'; object-src 'none'`;

/**
 * @param {string} dir the package folder
 * @param {{prefix?: string, csp?: boolean, frameAncestors?: string, port?: number}} [options]
 * @returns {Promise<{url: string, port: number, requests: string[], close: () => Promise<void>}>}
 */
export function serveFolder(dir, { prefix = "", csp = true, frameAncestors, port = 0 } = {}) {
  const requests = [];
  const server = createServer((req, res) => {
    const path = decodeURIComponent(new URL(req.url ?? "/", "http://x").pathname);
    requests.push(path);
    const rel = path.startsWith(prefix) ? path.slice(prefix.length) : null;
    const file = rel === null ? null : normalize(join(dir, rel === "" || rel.endsWith("/") ? `${rel}index.html` : rel));
    if (!file || !file.startsWith(normalize(dir)) || !existsSync(file) || !statSync(file).isFile()) return void res.writeHead(404).end("not found");
    res.writeHead(200, {
      "Content-Type": MIME[extname(file)] ?? "application/octet-stream",
      ...(csp ? { "Content-Security-Policy": packageCsp(frameAncestors) } : {}),
      "X-Content-Type-Options": "nosniff",
      "Access-Control-Allow-Origin": "*",
      "Cross-Origin-Resource-Policy": "cross-origin",
      "Referrer-Policy": "no-referrer",
    });
    res.end(readFileSync(file));
  });
  return new Promise((resolve) => {
    server.listen(port, "127.0.0.1", () => {
      const p = server.address().port;
      resolve({ url: `http://127.0.0.1:${p}`, port: p, requests, close: () => new Promise((r) => server.close(() => r())) });
    });
  });
}
