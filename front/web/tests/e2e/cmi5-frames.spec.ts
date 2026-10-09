import { createServer, type Server } from "node:http";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import type { AddressInfo } from "node:net";
import { expect, test } from "@playwright/test";

/**
 * Self-contained check of a cmi5 AU in a sandboxed frame on a same-site content origin (ADR 0046).
 * Three throwaway servers on free ports (app, content origin, tenant API; never :4321) and the demo
 * packages' own cmi5 runtime (api/database/seeds/Demo/assets/cmi5/cmi5-lite.js) show that:
 *  - the AU swaps the one-time launch token in its `fetch` URL for a session token with a plain
 *    cross-origin POST, and sends every xAPI call with that token as `Authorization: Basic`;
 *  - the learner's access token appears in no URL and no cookie reaches the API.
 */
const runtime = readFileSync(join(dirname(fileURLToPath(import.meta.url)), "..", "..", "..", "..", "api", "database", "seeds", "Demo", "assets", "cmi5", "cmi5-lite.js"));

const AU = `<!doctype html><title>au</title><script src="cmi5-lite.js"></script><script>
Cmi5.start().then(function () { return Cmi5.complete(); }).then(function () { return Cmi5.pass(1); });
</script>`;

const ONE_TIME = "one-time-launch-token";
const SESSION = "ulrs1.session.signature";
const SANDBOX = "allow-scripts allow-same-origin allow-forms allow-popups allow-downloads";

interface Call {
  method: string;
  url: string;
  origin: string | undefined;
  cookie: string | undefined;
  authorization: string | undefined;
  body: string;
}

let servers: Server[] = [];
let calls: Call[] = [];
let urls = { app: "", content: "", api: "" };

const listen = (server: Server) => new Promise<number>((resolve) => server.listen(0, "127.0.0.1", () => resolve((server.address() as AddressInfo).port)));

test.beforeAll(async () => {
  const contentServer = createServer((req, res) => {
    const path = new URL(req.url ?? "/", "http://x").pathname;
    const send = (body: Buffer | string, type: string) => void res.writeHead(200, { "Content-Type": type }).end(body);
    if (path === "/cmi5/1/index.html") return send(AU, "text/html");
    if (path === "/cmi5/1/cmi5-lite.js") return send(runtime, "text/javascript");
    res.writeHead(404).end();
  });
  const apiServer = createServer((req, res) => {
    const cors = {
      "Access-Control-Allow-Origin": req.headers.origin ?? "*",
      "Access-Control-Allow-Headers": (req.headers["access-control-request-headers"] as string | undefined) ?? "*",
      "Access-Control-Allow-Methods": "GET,POST,PUT,OPTIONS",
    };
    if (req.method === "OPTIONS") return void res.writeHead(204, cors).end();
    let body = "";
    req.on("data", (chunk) => (body += chunk));
    req.on("end", () => {
      const url = new URL(req.url ?? "/", "http://x");
      calls.push({ method: req.method ?? "", url: url.pathname + url.search, origin: req.headers.origin, cookie: req.headers.cookie, authorization: req.headers.authorization, body });
      if (url.pathname === "/api/cmi5/fetch") {
        if (url.searchParams.get("token") !== ONE_TIME) return void res.writeHead(401, cors).end("{}");
        return void res.writeHead(200, { ...cors, "Content-Type": "application/json" }).end(JSON.stringify({ "auth-token": SESSION }));
      }
      if (req.headers.authorization !== `Basic ${SESSION}`) return void res.writeHead(401, cors).end();
      if (url.pathname.endsWith("/activities/state")) {
        return void res.writeHead(200, { ...cors, "Content-Type": "application/json" }).end(JSON.stringify({ contextTemplate: { context: { extensions: {} } }, launchMode: "Normal" }));
      }
      res.writeHead(200, { ...cors, "Content-Type": "application/json" }).end("[]");
    });
  });
  const appServer = createServer((req, res) => {
    const query = new URL(req.url ?? "/", "http://x").searchParams;
    res.writeHead(200, { "Content-Type": "text/html", "Set-Cookie": "__Host-ulams_session=SECRET; Path=/; HttpOnly" });
    res.end(`<!doctype html><iframe style="width:600px;height:400px" src="${query.get("src")}" sandbox="${query.get("sandbox")}" referrerpolicy="no-referrer"></iframe>`);
  });
  const [contentPort, apiPort, appPort] = await Promise.all([listen(contentServer), listen(apiServer), listen(appServer)]);
  servers = [contentServer, apiServer, appServer];
  urls = { app: `http://acme.app.localhost:${appPort}`, content: `http://acme.content.localhost:${contentPort}`, api: `http://acme.localhost:${apiPort}` };
});

test.afterAll(async () => {
  await Promise.all(servers.map((s) => new Promise((resolve) => s.close(resolve))));
});

test.beforeEach(() => {
  calls = [];
});

const launchUrl = () => {
  const params = new URLSearchParams({
    endpoint: `${urls.api}/trax/api/access/xapi/std`,
    fetch: `${urls.api}/api/cmi5/fetch?token=${ONE_TIME}`,
    actor: JSON.stringify({ objectType: "Agent", account: { homePage: "https://ulams.app", name: "learner@example.test" } }),
    registration: "11111111-2222-4333-8444-555555555555",
    activityId: "https://ulams.app/xapi/activities/course/1/topic/2",
  });
  return `${urls.content}/cmi5/1/index.html?${params}`;
};

test.describe("cmi5 AU in a frame on a same-site content origin", () => {
  test.skip(({ isMobile }) => isMobile, "one viewport is enough");

  test("swaps the launch token for a session token and reports with it, without cookies", async ({ page }) => {
    const src = launchUrl();
    expect(src).not.toMatch(/eyJ/); // no JWT (Passport token) in the launch URL
    await page.goto(`${urls.app}/?src=${encodeURIComponent(src)}&sandbox=${encodeURIComponent(SANDBOX)}`);

    await expect.poll(() => calls.filter((c) => c.url.endsWith("/statements")).length, { timeout: 10_000 }).toBeGreaterThanOrEqual(3);

    const fetchCall = calls.find((c) => c.url.startsWith("/api/cmi5/fetch"));
    expect(fetchCall?.method).toBe("POST");
    expect(fetchCall?.origin).toBe(urls.content);

    const verbs = calls.filter((c) => c.url.endsWith("/statements")).map((c) => (JSON.parse(c.body) as { verb: { id: string } }).verb.id.split("/").pop());
    expect(verbs).toEqual(["initialized", "completed", "passed"]);
    for (const call of calls.filter((c) => !c.url.startsWith("/api/cmi5/fetch"))) {
      expect(call.authorization).toBe(`Basic ${SESSION}`);
      expect(call.origin).toBe(urls.content);
    }
    for (const call of calls) {
      expect(call.cookie, "the app's __Host- cookie must not reach the API from the AU").toBeUndefined();
      expect(call.url).not.toMatch(/eyJ/);
    }
    // every statement carries the registration of the launch
    for (const call of calls.filter((c) => c.url.endsWith("/statements"))) {
      expect((JSON.parse(call.body) as { context: { registration: string } }).context.registration).toBe("11111111-2222-4333-8444-555555555555");
    }
  });

  test("an unknown launch token gets no session and the AU reports nothing", async ({ page }) => {
    const src = launchUrl().replace(ONE_TIME, "stale-token");
    await page.goto(`${urls.app}/?src=${encodeURIComponent(src)}&sandbox=${encodeURIComponent(SANDBOX)}`);
    await expect.poll(() => calls.some((c) => c.url.startsWith("/api/cmi5/fetch")), { timeout: 10_000 }).toBe(true);
    await page.waitForTimeout(1500);
    expect(calls.some((c) => c.url.endsWith("/statements"))).toBe(false);
  });
});
