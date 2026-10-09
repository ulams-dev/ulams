import AxeBuilder from "@axe-core/playwright";
import { execFileSync } from "node:child_process";
import { createServer, type IncomingMessage, type Server, type ServerResponse } from "node:http";
import { mkdtempSync, readFileSync, existsSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, extname, join } from "node:path";
import { fileURLToPath } from "node:url";
import type { AddressInfo } from "node:net";
import { expect, test, type Page } from "@playwright/test";

/**
 * Self-contained check of the Interactive lesson (ADR 0086, 0087) against a tiny sample package. It starts
 * two throwaway servers on free ports (the content origin, which sets the package CSP like the API does,
 * and the app, which serves a page with the real InteractiveLesson component and records the BFF calls;
 * no stack needed, never :4321) and checks:
 *  - the package runs in an opaque origin: no cookies, no storage, no request to the app or the API;
 *  - the bridge round trip (ready, stepChanged, goToStep), the events reaching the BFF and completing the topic;
 *  - background mode: the stepper, "Explore freely" and the way back by keyboard;
 *  - reduced motion (poster and text), no WebGL, no answer within 10 s;
 *  - axe (WCAG 2.2 AA) in both modes, with and without reduced motion.
 */
const here = dirname(fileURLToPath(import.meta.url));
const front = join(here, "..", "..", "..");
const fixture = join(here, "..", "fixtures", "interactive", "minimal");

const MIME: Record<string, string> = { ".html": "text/html; charset=utf-8", ".js": "text/javascript; charset=utf-8", ".json": "application/json", ".png": "image/png" };

interface Hit {
  method: string;
  path: string;
  origin: string | undefined;
  cookie: string | undefined;
  authorization: string | undefined;
  body: string;
}

let servers: Server[] = [];
let content = "";
let app = "";
let hits: Hit[] = [];
let rendered: Record<string, string> = {};
let bridgeJs = "";
let elementJs = "";
let css = "";

const listen = (server: Server) => new Promise<number>((resolve) => server.listen(0, "127.0.0.1", () => resolve((server.address() as AddressInfo).port)));
const readBody = (req: IncomingMessage) => new Promise<string>((resolve) => {
  let body = "";
  req.on("data", (c) => (body += c));
  req.on("end", () => resolve(body));
});

/** The CSP the API sets per package version (api/packages/interactive InteractiveCsp). */
const packageCsp = () =>
  `default-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self' data:; connect-src 'self'; worker-src 'self' blob:; frame-src 'none'; frame-ancestors ${app}; form-action 'none'; base-uri 'none'; object-src 'none'`;

function serveContent(req: IncomingMessage, res: ServerResponse): void {
  hits.push({ method: req.method ?? "GET", path: `content:${req.url}`, origin: req.headers.origin, cookie: req.headers.cookie, authorization: req.headers.authorization, body: "" });
  const path = new URL(req.url ?? "/", "http://x").pathname;
  const prefix = "/interactive/k/v1/";
  const relative = path.startsWith(prefix) ? path.slice(prefix.length) : "";
  const file = relative === "vendor/interactive-bridge.js" ? null : join(fixture, relative);
  const body = relative === "vendor/interactive-bridge.js" ? bridgeJs : file && relative && !relative.includes("..") && existsSync(file) ? readFileSync(file) : null;
  if (body === null) return void res.writeHead(404).end();
  res.writeHead(200, {
    "Content-Type": MIME[extname(relative)] ?? "application/octet-stream",
    "Content-Security-Policy": packageCsp(),
    "X-Content-Type-Options": "nosniff",
    "Cross-Origin-Resource-Policy": "cross-origin",
    // the proxy's header: players in sandboxed frames (opaque origin) load their sub-resources cross-origin
    "Access-Control-Allow-Origin": "*",
    "Referrer-Policy": "no-referrer",
  });
  res.end(body);
}

async function serveApp(req: IncomingMessage, res: ServerResponse): Promise<void> {
  const url = new URL(req.url ?? "/", "http://x");
  const body = req.method === "POST" ? await readBody(req) : "";
  hits.push({ method: req.method ?? "GET", path: url.pathname, origin: req.headers.origin, cookie: req.headers.cookie, authorization: req.headers.authorization, body });
  if (url.pathname === "/element.js") return void res.writeHead(200, { "Content-Type": MIME[".js"] }).end(elementJs);
  if (url.pathname === "/bff/api/interactive/topics/5/events" && req.method === "POST") {
    const events = (JSON.parse(body) as { events: Array<{ type: string }> }).events;
    res.writeHead(200, { "Content-Type": "application/json" });
    return void res.end(JSON.stringify({ success: true, data: { status: events.some((e) => e.type === "complete") ? 1 : 2, progress: {} } }));
  }
  if (url.pathname === "/page") {
    const variant = url.searchParams.get("v") ?? "inline";
    const html = (rendered[variant] ?? "").replace(/<script type="module" src="[^"]*\?astro[^"]*"><\/script>/g, "");
    res.writeHead(200, { "Content-Type": MIME[".html"], "Set-Cookie": "app_session=SECRET; Path=/; HttpOnly" });
    return void res.end(`<!doctype html><html lang="en" data-theme="coffee"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Interactive lesson</title><style>${css}</style></head><body><header style="padding:12px"><a href="/">Course</a></header><main id="main">${html}</main><script>document.addEventListener("ulams:complete", () => { window.__completed = (window.__completed || 0) + 1; });</script><script type="module" src="/element.js"></script></body></html>`);
  }
  res.writeHead(404).end();
}

test.beforeAll(async () => {
  const esbuild = await import("esbuild");
  execFileSync("node", ["scripts/bundle.mjs"], { cwd: join(front, "interactive-bridge") });
  bridgeJs = readFileSync(join(front, "interactive-bridge", "dist", "interactive-bridge.js"), "utf8");
  elementJs = (await esbuild.build({ entryPoints: [join(front, "ui", "src", "elements", "interactive.ts")], bundle: true, format: "esm", platform: "browser", write: false })).outputFiles[0]!.text;
  const component = readFileSync(join(front, "ui", "src", "components", "InteractiveLesson.astro"), "utf8");
  // the component's styles first: in a real page the order of the sheets is not the component's to choose, so
  // the shared button and base rules must not be able to override its state rules
  css = [/<style is:global>([\s\S]*?)<\/style>/.exec(component)![1], readFileSync(join(front, "ui", "src", "styles", "base.css"), "utf8"), readFileSync(join(front, "ui", "src", "styles", "themes", "coffee.css"), "utf8")].join("\n");

  const contentServer = createServer(serveContent);
  const appServer = createServer((req, res) => void serveApp(req, res));
  const [contentPort, appPort] = await Promise.all([listen(contentServer), listen(appServer)]);
  servers = [contentServer, appServer];
  // *.localhost resolves to loopback in Chromium; app and content origin are same-site, like production
  content = `http://acme.content.localhost:${contentPort}`;
  app = `http://acme.app.localhost:${appPort}`;

  const props = (entry: string, display: string, extra: Record<string, unknown> = {}) => ({
    component: "InteractiveLesson",
    props: {
      src: `${content}/interactive/k/v1/${entry}`,
      title: "Sample interactive",
      topicId: 5,
      courseId: 1,
      display,
      height: 420,
      steps: [
        { id: "step-a", title: "Step A", text: "Text of step A.", poster: `${content}/interactive/k/v1/posters/a.png` },
        { id: "step-b", title: "Step B", text: "Text of step B." },
        { id: "step-c", title: "Step C", text: "Text of step C." },
      ],
      text: "Use the **buttons** to move between the steps.",
      licence: "MIT",
      attribution: "Written for the Playwright test.",
      sourceUrl: "https://example.com/src",
      ...extra,
    },
  });
  const outFile = join(mkdtempSync(join(tmpdir(), "ulams-interactive-")), "pages.json");
  execFileSync("npx", ["vitest", "run", "tests/render-fixture.test.ts"], {
    cwd: join(front, "ui"),
    env: {
      ...process.env,
      ULAMS_RENDER_OUT: outFile,
      ULAMS_RENDER_IN: JSON.stringify({
        inline: props("index.html", "inline"),
        background: props("index.html", "background"),
        silent: props("silent.html", "inline"),
        webgl: props("index.html", "inline", { requires: ["webgl"] }),
      }),
    },
    stdio: "pipe",
  });
  rendered = JSON.parse(readFileSync(outFile, "utf8")) as Record<string, string>;
});

test.afterAll(async () => {
  await Promise.all(servers.map((s) => new Promise((resolve) => s.close(resolve))));
});

test.beforeEach(() => {
  hits = [];
});

const open = async (page: Page, variant: string) => {
  await page.goto(`${app}/page?v=${variant}`);
};
const frameOf = (page: Page) => page.frames().find((f) => f.url().startsWith(content));
const ready = (page: Page) => expect(page.locator("ulams-interactive")).toHaveAttribute("state", "ready");
const eventsPosted = () => hits.filter((h) => h.path === "/bff/api/interactive/topics/5/events").flatMap((h) => (JSON.parse(h.body) as { events: Array<Record<string, unknown>> }).events);

test.describe("sample package in the inline frame", () => {
  test.skip(({ isMobile }) => isMobile, "one viewport is enough for the protocol checks");

  test("runs in an opaque origin: no cookies, no storage, no request to the app", async ({ page }) => {
    await open(page, "inline");
    await ready(page);
    const frame = frameOf(page)!;
    const result = await frame.evaluate(async (target) => {
      const attempt = async (fn: () => unknown) => {
        try {
          await fn();
          return "allowed";
        } catch (e) {
          return (e as Error).name;
        }
      };
      return {
        origin: window.origin,
        cookie: await attempt(() => document.cookie),
        local: await attempt(() => localStorage.getItem("x")),
        session: await attempt(() => sessionStorage.getItem("x")),
        indexedDb: await attempt(() => indexedDB.open("x")),
        fetchApp: await attempt(async () => (await fetch(`${target}/bff/api/interactive/topics/5/events`, { method: "POST", body: "{}" })).status),
        parent: await attempt(() => window.parent.document.title),
        top: await attempt(() => (window.top!.location.href = "http://example.com/")),
      };
    }, app);
    expect(result.origin).toBe("null");
    expect(result.cookie).toBe("SecurityError");
    expect(result.local).toBe("SecurityError");
    expect(result.session).toBe("SecurityError");
    expect(result.fetchApp).not.toBe("allowed");
    expect(result.parent).toBe("SecurityError");
    expect(result.top).toBe("SecurityError");
    // the CSP stops the request itself; whatever happened, it never carried the app's cookie
    expect(hits.filter((h) => h.path === "/bff/api/interactive/topics/5/events" && h.body === "{}")).toEqual([]);
    expect(hits.filter((h) => h.path.startsWith("content:")).every((h) => h.cookie === undefined && h.authorization === undefined)).toBe(true);
  });

  test("the frame is sandboxed, gets no token in its URL and sends the init message once", async ({ page }) => {
    await open(page, "inline");
    await ready(page);
    const frame = page.locator("ulams-interactive iframe");
    expect(await frame.getAttribute("sandbox")).toBe("allow-scripts allow-popups allow-popups-to-escape-sandbox");
    expect(await frame.getAttribute("src")).toBe(`${content}/interactive/k/v1/index.html`);
    const init = await frameOf(page)!.evaluate(() => (window as unknown as { __init: Record<string, unknown> }).__init);
    expect(init).toMatchObject({ locale: "en", display: "inline", chrome: "full", reducedMotion: false, startStep: "step-a" });
    expect(Object.keys(init.theme as object).length).toBeGreaterThan(3);
    expect(JSON.stringify(init)).not.toMatch(/token|authorization|secret/i);
  });

  test("steps, announcements and completion reach the BFF with the learner's session only", async ({ page }) => {
    await open(page, "inline");
    await ready(page);
    const frame = frameOf(page)!;
    await frame.locator("#next").click();
    await expect(page.locator("[data-live]")).toHaveText("Step 2: Step B");
    await frame.locator("#next").click();
    await expect(page.locator("[data-live]")).toHaveText("Step 3: Step C");
    await expect.poll(() => eventsPosted().some((e) => e.type === "complete"), { timeout: 10_000 }).toBe(true);
    await expect.poll(() => page.evaluate(() => (window as unknown as { __completed?: number }).__completed ?? 0)).toBe(1);

    const types = eventsPosted().map((e) => e.type);
    expect(types).toContain("stepChanged");
    expect(eventsPosted().filter((e) => e.type === "stepChanged").map((e) => e.step)).toEqual(expect.arrayContaining(["step-b", "step-c"]));
    for (const post of hits.filter((h) => h.path === "/bff/api/interactive/topics/5/events")) {
      expect(post.body).not.toContain("nonce");
      expect(post.origin).toBe(app);
    }
  });

  test("the text version lists every step in the range, and the licence is shown", async ({ page }) => {
    await open(page, "inline");
    await page.getByText("Text version of this interactive").click();
    await expect(page.locator("[data-step]")).toHaveCount(3);
    await expect(page.getByText("Text of step B.").first()).toBeVisible();
    await page.getByText("About this interactive").click();
    await expect(page.getByText("Written for the Playwright test.")).toBeVisible();
  });

  test("a package that never answers falls back to the text after ten seconds", async ({ page }) => {
    await page.clock.install();
    await open(page, "silent");
    await page.clock.fastForward(10_500);
    await expect(page.locator("ulams-interactive")).toHaveAttribute("fallback", "timeout");
    await expect(page.locator("[data-notice]")).toContainText("did not start");
    await expect(page.getByText("Text version of this interactive")).toBeVisible();
  });

  test("without WebGL the poster and the text replace a package that needs it", async ({ page }) => {
    await page.addInitScript(() => {
      const original = HTMLCanvasElement.prototype.getContext;
      HTMLCanvasElement.prototype.getContext = function (this: HTMLCanvasElement, type: string, ...rest: unknown[]) {
        return type.startsWith("webgl") ? null : (original as (...a: unknown[]) => unknown).call(this, type, ...rest);
      } as typeof original;
    });
    await open(page, "webgl");
    await expect(page.locator("ulams-interactive")).toHaveAttribute("fallback", "webgl");
    expect(await page.locator("ulams-interactive iframe").getAttribute("src")).toBeNull();
    await expect(page.locator("[data-poster]")).toBeVisible();
    await expect(page.locator("[data-notice]")).toContainText("cannot show this 3D view");
  });
});

test.describe("background mode", () => {
  test.skip(({ isMobile }) => isMobile, "keyboard flow on desktop");

  test("the stepper moves the package, Explore freely hands over the focus and Escape comes back", async ({ page }) => {
    await open(page, "background");
    await ready(page);
    const root = page.locator("ulams-interactive");
    await expect(root.locator("[data-counter]")).toHaveText("Step 1 of 3");

    // the stage fills the viewport behind the card
    const stage = await root.locator(".u-ix__stage").boundingBox();
    expect(stage!.width).toBeGreaterThanOrEqual(page.viewportSize()!.width - 1);
    expect(stage!.height).toBeGreaterThanOrEqual(page.viewportSize()!.height - 1);

    // the way back only shows while exploring (the shared .u-btn rule must not win over it)
    await expect(root.getByRole("button", { name: "Back to the lesson" })).toBeHidden();

    // goToStep round trip: Next step in the card, the package shows it and answers stepChanged
    await root.getByRole("button", { name: "Next step" }).click();
    await expect(frameOf(page)!.locator("#step")).toHaveText("Step B");
    await expect(root.locator("[data-counter]")).toHaveText("Step 2 of 3");
    await expect(page.locator("[data-live]")).toHaveText("Step 2: Step B");

    // keyboard only: Tab reaches the stepper buttons, the frame is not in the tab order while the card shows
    await page.locator("body").focus();
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    const focused: string[] = [];
    for (let i = 0; i < 8; i++) {
      await page.keyboard.press("Tab");
      focused.push(await page.evaluate(() => (document.activeElement?.tagName === "IFRAME" ? "IFRAME" : (document.activeElement?.textContent ?? "").trim().slice(0, 24))));
    }
    expect(focused).toContain("Previous step");
    expect(focused).toContain("Next step");
    expect(focused).toContain("Explore freely");
    expect(focused).not.toContain("IFRAME");

    await root.getByRole("button", { name: "Explore freely" }).focus();
    await page.keyboard.press("Enter");
    await expect(root).toHaveAttribute("explore", "true");
    await expect(root.locator(".u-ix__overlay")).toBeHidden();
    expect(await page.evaluate(() => document.activeElement?.tagName)).toBe("IFRAME");
    await expect(root.getByRole("button", { name: "Back to the lesson" })).toBeVisible();

    // the keys of a frame never reach this page, so Escape is pressed with the focus on the page
    await page.evaluate(() => (document.activeElement as HTMLElement).blur());
    await page.keyboard.press("Escape");
    await expect(root).not.toHaveAttribute("explore", "true");
    await expect(root.locator(".u-ix__overlay")).toBeVisible();
    await expect(root.getByRole("button", { name: "Explore freely" })).toBeFocused();
  });

  test("the card keeps AA contrast over any frame content (worst case: pure white or pure black behind it)", async ({ page }) => {
    await open(page, "background");
    await ready(page);
    const ratios = await page.locator(".u-ix__overlay").evaluate((el) => {
      const canvas = document.createElement("canvas").getContext("2d")!;
      const rgba = (css: string): number[] => {
        canvas.clearRect(0, 0, 1, 1);
        canvas.fillStyle = "#000";
        canvas.fillStyle = css;
        canvas.fillRect(0, 0, 1, 1);
        const d = canvas.getImageData(0, 0, 1, 1).data;
        return [d[0]!, d[1]!, d[2]!, d[3]! / 255];
      };
      const luminance = (c: number[]) => {
        const [r, g, b] = c.slice(0, 3).map((v) => {
          const x = v! / 255;
          return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4;
        });
        return 0.2126 * r! + 0.7152 * g! + 0.0722 * b!;
      };
      const style = getComputedStyle(el);
      const card = rgba(style.backgroundColor);
      const text = rgba(style.color);
      return [0, 255].map((behind) => {
        const blended = [0, 1, 2].map((i) => card[i]! * card[3]! + behind * (1 - card[3]!));
        const [a, b] = [luminance(blended), luminance(text)];
        return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
      });
    });
    for (const ratio of ratios) expect(ratio).toBeGreaterThanOrEqual(4.5);
  });
});

for (const reduced of [false, true]) {
  test.describe(`axe, reduced motion ${reduced ? "on" : "off"}`, () => {
    test.use({ contextOptions: { reducedMotion: reduced ? "reduce" : "no-preference" } });

    for (const variant of ["inline", "background"]) {
      test(`${variant} has no WCAG 2.2 AA violations`, async ({ page }) => {
        await open(page, variant);
        if (reduced) await expect(page.locator("ulams-interactive")).toHaveAttribute("fallback", "reduced-motion");
        else await ready(page);
        const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).exclude("iframe").analyze();
        expect(results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`)).toEqual([]);
      });
    }
  });
}

test.describe("reduced motion", () => {
  test.use({ contextOptions: { reducedMotion: "reduce" } });
  test.skip(({ isMobile }) => isMobile, "one viewport is enough");

  test("shows the step's poster and text, and plays the animation on request", async ({ page }) => {
    await open(page, "inline");
    const root = page.locator("ulams-interactive");
    await expect(root).toHaveAttribute("fallback", "reduced-motion");
    expect(await root.locator("iframe").getAttribute("src")).toBeNull();
    await expect(root.locator("[data-poster]")).toBeVisible();
    expect(await root.locator("[data-poster]").getAttribute("alt")).toBe("Text of step A.");
    await root.getByRole("button", { name: "Play the animation anyway" }).click();
    await ready(page);
    expect((await frameOf(page)!.evaluate(() => (window as unknown as { __init: { reducedMotion: boolean } }).__init)).reducedMotion).toBe(true);
  });
});
