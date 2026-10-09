import { createServer, type Server } from "node:http";
import { existsSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import type { AddressInfo } from "node:net";
import { expect, test } from "@playwright/test";

/**
 * Self-contained check of the package player against a same-site content origin (ADR 0014, amended
 * 2026-10-09). It starts three throwaway servers on free ports (app, content origin, tenant API; no
 * stack needed, never :4321) and loads the real SCORM player (api/packages/scorm) in a frame:
 *  - with `allow-same-origin` the SCO finds window.API and its CMI reaches the API with the tracking
 *    token and no cookie, although the app set a `__Host-` cookie on the sibling host;
 *  - without it (opaque origin) the SCO cannot reach the player's API: this is why SANDBOX_SCORM keeps
 *    `allow-same-origin` (front/sdk/src/frames.ts).
 */
const root = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "..", "..", "api", "packages", "scorm", "resources");
const player = (file: string) => readFileSync(join(root, "content-player", file));
const scormAgain = () => readFileSync(join(root, "js", "vendor", "scorm-again", "scorm-again.min.js"));

const SCO = `<!doctype html><title>sco</title><script>
function find(w){var n=0;while(!w.API&&w.parent&&w.parent!==w&&n++<10){try{w=w.parent;void w.API}catch(e){return null}}return w.API||null}
var api=null;try{api=find(window)}catch(e){}
if(api){api.LMSInitialize('');api.LMSSetValue('cmi.core.lesson_status','completed');api.LMSSetValue('cmi.core.score.raw','90');api.LMSCommit('')}
</script>`;

const SAME_ORIGIN = "allow-scripts allow-same-origin allow-forms allow-popups allow-downloads";
const OPAQUE = "allow-scripts allow-forms allow-popups allow-downloads";

interface Track {
  url: string;
  origin: string | undefined;
  cookie: string | undefined;
  token: string | string[] | undefined;
  body: string;
}

let servers: Server[] = [];
let tracks: Track[] = [];
let urls = { app: "", content: "", api: "" };

const listen = (server: Server) =>
  new Promise<number>((resolve) => server.listen(0, "127.0.0.1", () => resolve((server.address() as AddressInfo).port)));

test.beforeAll(async () => {
  const contentServer = createServer((req, res) => {
    const path = new URL(req.url ?? "/", "http://x").pathname;
    const send = (body: Buffer | string, type: string) => {
      res.writeHead(200, { "Content-Type": type, "Access-Control-Allow-Origin": "*" });
      res.end(body);
    };
    if (path === "/scorm/_player/player.html") return send(player("player.html"), "text/html");
    if (path === "/scorm/_player/player.js") return send(player("player.js"), "text/javascript");
    if (path === "/scorm/_player/scorm-again.min.js") return send(scormAgain(), "text/javascript");
    if (path === "/scorm/sco/index.html") return send(SCO, "text/html");
    res.writeHead(404).end();
  });
  const apiServer = createServer((req, res) => {
    const cors = {
      "Access-Control-Allow-Origin": req.headers.origin ?? "*",
      "Access-Control-Allow-Headers": "content-type,x-ulams-tracking-token,accept",
      "Access-Control-Allow-Methods": "GET,POST,OPTIONS",
    };
    if (req.method === "OPTIONS") return void res.writeHead(204, cors).end();
    let body = "";
    req.on("data", (chunk) => (body += chunk));
    req.on("end", () => {
      const path = new URL(req.url ?? "/", "http://x").pathname;
      res.writeHead(200, { ...cors, "Content-Type": "application/json" });
      if (req.method === "POST") {
        tracks.push({ url: path, origin: req.headers.origin, cookie: req.headers.cookie, token: req.headers["x-ulams-tracking-token"], body });
        return void res.end("{}");
      }
      res.end(JSON.stringify({ data: { version: "scorm_12", cmi: {}, entry_url: `${urls.content}/scorm/sco/index.html`, title: "SCO", player: {} } }));
    });
  });
  const appServer = createServer((req, res) => {
    const query = new URL(req.url ?? "/", "http://x").searchParams;
    res.writeHead(200, { "Content-Type": "text/html", "Set-Cookie": "__Host-ulams_session=SECRET; Path=/; HttpOnly" });
    res.end(`<!doctype html><iframe style="width:600px;height:400px" src="${query.get("src")}" sandbox="${query.get("sandbox")}"></iframe>`);
  });
  const [contentPort, apiPort, appPort] = await Promise.all([listen(contentServer), listen(apiServer), listen(appServer)]);
  servers = [contentServer, apiServer, appServer];
  // *.localhost resolves to loopback in Chromium; the three hosts are same-site, like production
  urls = { app: `http://acme.app.localhost:${appPort}`, content: `http://acme.content.localhost:${contentPort}`, api: `http://acme.localhost:${apiPort}` };
});

test.afterAll(async () => {
  await Promise.all(servers.map((s) => new Promise((resolve) => s.close(resolve))));
});

test.beforeEach(() => {
  tracks = [];
});

const open = async (page: import("@playwright/test").Page, sandbox: string) => {
  const src = `${urls.content}/scorm/_player/player.html#api=${encodeURIComponent(urls.api)}&sco=abc&token=tok`;
  await page.goto(`${urls.app}/?src=${encodeURIComponent(src)}&sandbox=${encodeURIComponent(sandbox)}`);
};

test.describe("SCORM player in a frame on a same-site content origin", () => {
  test.skip(({ isMobile }) => isMobile, "one viewport is enough");
  test.skip(() => !existsSync(join(root, "js", "vendor", "scorm-again", "scorm-again.min.js")), "scorm-again is not vendored");

  test("plays and tracks with allow-same-origin, sending the tracking token and no cookie", async ({ page }) => {
    await open(page, SAME_ORIGIN);
    await expect.poll(() => tracks.some((t) => t.body.includes("completed")), { timeout: 10_000 }).toBe(true);
    for (const track of tracks) {
      expect(track.url).toBe("/api/scorm/content/abc/track");
      expect(track.origin).toBe(urls.content);
      expect(track.token).toBe("tok");
      expect(track.cookie, "the app's __Host- cookie must not reach the content origin or the API").toBeUndefined();
    }
  });

  test("without allow-same-origin the SCO cannot find window.API (why the flag stays)", async ({ page }) => {
    await open(page, OPAQUE);
    await page.waitForTimeout(3000);
    expect(tracks.some((t) => t.body.includes("completed"))).toBe(false);
  });
});
