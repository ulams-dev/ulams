// A throwaway lesson host and content origin for the package tests (never :4321, no stack needed).
// The content server sets the package CSP like the API does; the app server serves the host page.
import { build } from "esbuild";
import { createServer } from "node:http";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { serveFolder } from "../../scripts/lib/static-server.mjs";

const here = dirname(fileURLToPath(import.meta.url));

let hostJs;
async function hostBundle() {
  hostJs ??= (await build({ entryPoints: [join(here, "host-page.ts")], bundle: true, format: "esm", write: false, platform: "browser", logLevel: "silent" })).outputFiles[0].text;
  return hostJs;
}

/**
 * @param {string} packageDir folder holding the package (index.html, ulams-interactive.json, ...)
 * @param {{prefix?: string}} [options]
 */
export async function startHarness(packageDir, { prefix = "/interactive/k/v1/" } = {}) {
  const js = await hostBundle();
  const appRequests = [];
  const app = createServer((req, res) => {
    const url = new URL(req.url ?? "/", "http://x");
    appRequests.push(url.pathname);
    if (url.pathname === "/host.js") return void res.writeHead(200, { "Content-Type": "text/javascript" }).end(js);
    if (url.pathname === "/host") {
      res.writeHead(200, { "Content-Type": "text/html; charset=utf-8", "Set-Cookie": "app_session=SECRET; Path=/; HttpOnly" });
      return void res.end(
        `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Lesson host</title></head><body style="margin:0;background:#fff"><main><h1 style="font:600 18px sans-serif;margin:8px">Interactive lesson</h1><iframe id="frame" title="Interactive package" sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox" style="width:100%;height:calc(100vh - 56px);border:0;display:block"></iframe></main><script type="module" src="/host.js"></script></body></html>`,
      );
    }
    res.writeHead(404).end();
  });
  const appPort = await new Promise((r) => app.listen(0, "127.0.0.1", () => r(app.address().port)));
  const appOrigin = `http://127.0.0.1:${appPort}`;
  const content = await serveFolder(packageDir, { prefix, frameAncestors: appOrigin });
  return {
    appOrigin,
    contentOrigin: content.url,
    /** URL of the host page; params become the `init` message (chrome, display, locale, reducedMotion, showcase=1, startStep, from, to). */
    hostUrl(params = {}, entry = "index.html") {
      const q = new URLSearchParams({ src: `${content.url}${prefix}${entry}`, ...params });
      return `${appOrigin}/host?${q}`;
    },
    /** Paths requested from the content origin outside the package prefix (must stay empty). */
    strayContentRequests: () => content.requests.filter((p) => !p.startsWith(prefix)),
    contentRequests: content.requests,
    appRequests,
    close: async () => {
      await content.close();
      await new Promise((r) => app.close(() => r()));
    },
  };
}

/** Waits until the host has logged a message of this type (optionally matching), returns it. */
export async function waitForMessage(page, type, match = {}, timeout = 30_000) {
  const handle = await page.waitForFunction(
    ({ type, match }) => window.__log.find((m) => m.type === type && Object.entries(match).every(([k, v]) => m[k] === v)),
    { type, match },
    { timeout },
  );
  return handle.jsonValue();
}

export const messages = (page, type) => page.evaluate((t) => window.__log.filter((m) => !t || m.type === t), type);

/** Unzips a package zip into a fresh temp folder, so the test plays exactly what would be uploaded. */
export async function unzipPackage(zip) {
  const { execFileSync } = await import("node:child_process");
  const { mkdtempSync } = await import("node:fs");
  const { tmpdir } = await import("node:os");
  const dir = mkdtempSync(join(tmpdir(), "ulams-package-"));
  execFileSync("unzip", ["-q", zip, "-d", dir]);
  return dir;
}
