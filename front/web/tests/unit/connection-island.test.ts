// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";

const S = "01m4fj5zsgfxhzh3cantwevncw";

interface Call {
  method: string;
  path: string;
  body: unknown;
}

function stubApi(overrides: { connection?: Record<string, unknown>; checkStatus?: number; updateFails?: string; connector?: string } = {}) {
  if (typeof AbortSignal.timeout !== "function") (AbortSignal as unknown as { timeout: () => AbortSignal }).timeout = () => new AbortController().signal;
  const calls: Call[] = [];
  const git = (overrides.connector ?? "git") === "git";
  const connection: Record<string, unknown> = {
    id: "c1", connector: overrides.connector ?? "git", schedule: "daily", status: "active", autoAnalyse: true, settings: {},
    config: git ? { host: "github", repository: "ulams-dev/docs", branch: "main", paths: ["docs/**/*.md"] } : { urls: ["https://docs.example.com/a"] },
    syncedRevision: { id: "rev1", number: 1 }, latestRevision: { id: "rev1", number: 1, status: "ingested" },
    lastCheckedAt: "2026-10-20T09:00:00+00:00", nextCheckAt: "2026-10-21T09:00:00+00:00", lastChangeAt: null, failureCount: 0, lastError: null,
    secretsSet: [], webhookUrl: "http://api.test/api/living-course/webhooks/w1", ...overrides.connection,
  };
  const rev = (n: number) => ({ id: `rev${n}`, sourceId: "src1", number: n, origin: "git", originRef: null, trigger: "initial", triggeredBy: 1, status: "ingested", fragmentCount: 1, tokens: 1, title: null, name: null, files: null, counts: null, error: null, detectedAt: "2026-10-20T09:00:00+00:00", synced: true, latest: true });
  vi.stubGlobal("fetch", async (url: string, init?: RequestInit) => {
    const method = init?.method ?? "GET";
    const path = url.replace("/studio/api/living-course", "");
    calls.push({ method, path, body: typeof init?.body === "string" ? JSON.parse(init.body) : undefined });
    const ok = (data: unknown, status = 200) => new Response(JSON.stringify({ success: true, data }), { status });
    if (path === `/sessions/${S}/sources`) return ok([{ id: "src1", name: "ulams-dev/docs", status: "ready", kind: "markdown", size: 1, tokens: 1, fragments: 1, title: null, revisionCount: 1, connection }]);
    if (path === "/sources/src1/revisions") return ok([rev(1)]);
    if (path === "/revisions/rev1/changes") return ok({ from: null, to: rev(1), counts: null, changes: [] });
    if (path === "/connections/c1/check") {
      if (overrides.checkStatus === 409) return new Response(JSON.stringify({ success: false, message: "This source is paused. Resume it before checking." }), { status: 409 });
      // the queue runs the check at once: the next read shows it
      connection.lastCheckedAt = "2026-10-20T10:00:00+00:00";
      connection.latestRevision = { id: "rev2", number: 2, status: "ingested" };
      return ok({ queued: true, connectionId: "c1" }, 202);
    }
    if (path === "/connections/c1/webhook-secret") return ok({ webhookSecret: "new-secret-1" });
    if (path === "/connections/c1" && method === "PUT") {
      const body = JSON.parse(String(init?.body));
      if (overrides.updateFails) return new Response(JSON.stringify({ success: false, message: overrides.updateFails }), { status: 422 });
      if (body.status) connection.status = body.status;
      if (body.schedule) connection.schedule = body.schedule;
      if (body.secrets?.token) connection.secretsSet = ["token"];
      return ok(connection);
    }
    return new Response("{}", { status: 404 });
  });
  return { calls, connection };
}

async function mount(): Promise<HTMLElement> {
  document.body.innerHTML = `<div data-studio-sources data-session="${S}"><div data-sources></div></div><div data-announcer></div>`;
  vi.resetModules();
  const { mountSources } = await import("../../src/studio/sources.ts");
  const root = document.querySelector<HTMLElement>("[data-studio-sources]")!;
  mountSources(root);
  await vi.waitFor(() => expect(root.querySelector("[data-connection-panel]"), root.innerHTML).not.toBeNull());
  await vi.waitFor(() => expect(root.querySelector("[data-changes] .st-empty")).not.toBeNull());
  return root;
}

const button = (root: HTMLElement, text: string) => [...root.querySelectorAll("button")].find((b) => b.textContent === text)!;

afterEach(() => vi.unstubAllGlobals());

describe("connection panel of a connected source", () => {
  it("shows what the source reads from, its state, last and next check", async () => {
    stubApi();
    const root = await mount();
    const panel = root.querySelector<HTMLElement>("[data-connection-panel]")!;
    expect(panel.textContent).toContain("Repository");
    expect(panel.textContent).toContain("ulams-dev/docs");
    expect(panel.textContent).toContain("Branch");
    expect(panel.textContent).toContain("main");
    expect(panel.textContent).toContain("docs/**/*.md");
    expect(panel.querySelector("[data-conn-state]")?.textContent).toBe("Connection active");
    expect(panel.querySelector("[data-conn-state] svg")).not.toBeNull();
    expect(panel.querySelector("[data-last-checked]")?.textContent).not.toBe("Never");
    expect(panel.querySelector<HTMLSelectElement>("[data-schedule]")!.value).toBe("daily");
    // the card says how often it checks, in words
    expect(root.textContent).toContain("Every day");
  });

  it("shows a failed connection with its last error", async () => {
    stubApi({ connection: { lastError: "Branch docs was not found.", failureCount: 3 } });
    const root = await mount();
    expect(root.querySelector("[data-conn-state]")?.textContent).toBe("Connection error");
    expect(root.querySelector("[data-conn-last-error]")?.textContent).toBe("Last error: Branch docs was not found.");
  });

  it("checks now, says it is queued, then refreshes the card and the revisions", async () => {
    const { calls } = stubApi();
    const root = await mount();
    button(root, "Check now").click();
    expect(root.querySelector("[data-conn-status]")?.textContent).toMatch(/Check queued/);
    await vi.waitFor(() => expect(root.querySelector("[data-conn-status]")?.textContent).toBe("The check found a new revision."));
    expect(calls.filter((c) => c.path === "/connections/c1/check")).toHaveLength(1);
    expect(root.textContent).toContain("Revision 2");
  });

  it("shows the API's refusal when a check cannot start", async () => {
    stubApi({ checkStatus: 409 });
    const root = await mount();
    button(root, "Check now").click();
    await vi.waitFor(() => expect(root.querySelector("[data-conn-error]")?.textContent).toBe("This source is paused. Resume it before checking."));
  });

  it("pauses and resumes; a paused source cannot be checked", async () => {
    const { calls } = stubApi();
    const root = await mount();
    button(root, "Pause checking").click();
    await vi.waitFor(() => expect(root.querySelector("[data-conn-state]")?.textContent).toBe("Connection paused"));
    expect(calls.find((c) => c.method === "PUT")!.body).toEqual({ status: "paused" });
    expect([...root.querySelectorAll("button")].some((b) => b.textContent === "Check now")).toBe(false);
    expect(root.querySelector("[data-next-check]")?.textContent).toBe("Paused");
    button(root, "Resume checking").click();
    await vi.waitFor(() => expect(root.querySelector("[data-conn-state]")?.textContent).toBe("Connection active"));
  });

  it("changes the schedule and puts it back when the API refuses", async () => {
    const ok = stubApi();
    let root = await mount();
    let select = root.querySelector<HTMLSelectElement>("[data-schedule]")!;
    select.value = "weekly";
    select.dispatchEvent(new Event("change"));
    await vi.waitFor(() => expect(root.querySelector("[data-conn-status]")?.textContent).toBe("Schedule saved."));
    expect(ok.calls.find((c) => c.method === "PUT")!.body).toEqual({ schedule: "weekly" });
    expect(root.textContent).toContain("Every week");

    vi.unstubAllGlobals();
    stubApi({ updateFails: "The schedule is not valid." });
    root = await mount();
    select = root.querySelector<HTMLSelectElement>("[data-schedule]")!;
    select.value = "hourly";
    select.dispatchEvent(new Event("change"));
    await vi.waitFor(() => expect(root.querySelector("[data-conn-error]")?.textContent).toBe("The schedule is not valid."));
    expect(select.value).toBe("daily");
  });

  it("never shows the token: it says Token set and takes a replacement", async () => {
    const { calls } = stubApi({ connection: { secretsSet: ["token"] } });
    const root = await mount();
    expect(root.querySelector("[data-token-state]")?.textContent).toBe("Access token: Token set");
    const input = root.querySelector<HTMLInputElement>("[data-token]")!;
    expect(input.type).toBe("password");
    expect(input.value).toBe("");
    expect(root.innerHTML).not.toContain("ghp_");
    input.value = "ghp_new";
    root.querySelector<HTMLFormElement>("[data-token-form]")!.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
    await vi.waitFor(() => expect(root.querySelector("[data-conn-status]")?.textContent).toBe("Token saved."));
    expect(calls.find((c) => c.method === "PUT")!.body).toEqual({ secrets: { token: "ghp_new" } });
    expect(root.querySelector<HTMLInputElement>("[data-token]")!.value).toBe("");
  });

  it("offers the webhook URL and rotates the secret only after a confirmation, showing it once", async () => {
    const { calls } = stubApi();
    const root = await mount();
    expect(root.querySelector<HTMLInputElement>("[data-webhook-url]")!.value).toBe("http://api.test/api/living-course/webhooks/w1");
    expect(root.querySelector("[data-secret]")).toBeNull();
    expect(root.querySelector("[data-hints]")?.textContent).toContain("Just the push event");
    button(root, "Rotate secret").click();
    expect(calls.some((c) => c.path.endsWith("/webhook-secret"))).toBe(false);
    expect(root.querySelector("[data-rotate-box]")?.textContent).toMatch(/stops working at once/);
    button(root, "Rotate and show the new secret").click();
    await vi.waitFor(() => expect(root.querySelector<HTMLInputElement>("[data-secret] input")?.value).toBe("new-secret-1"));
    expect(root.querySelector("[data-secret-warning]")?.textContent).toMatch(/only once/);
    expect(calls.find((c) => c.path.endsWith("/webhook-secret"))!.method).toBe("POST");
  });

  it("has no webhook or token for web pages", async () => {
    stubApi({ connector: "url", connection: { webhookUrl: null } });
    const root = await mount();
    expect(root.querySelector("[data-webhook]")).toBeNull();
    expect(root.querySelector("[data-token-form]")).toBeNull();
    expect(root.querySelector("[data-connection-panel]")?.textContent).toContain("docs.example.com/a");
  });

  it("shows no connection panel for an uploaded source", async () => {
    stubApi({ connector: "upload" });
    document.body.innerHTML = `<div data-studio-sources data-session="${S}"><div data-sources></div></div><div data-announcer></div>`;
    vi.resetModules();
    const { mountSources } = await import("../../src/studio/sources.ts");
    const root = document.querySelector<HTMLElement>("[data-studio-sources]")!;
    mountSources(root);
    await vi.waitFor(() => expect(root.querySelector("[data-source-block]")).not.toBeNull());
    expect(root.querySelector("[data-connection-panel]")).toBeNull();
    expect([...root.querySelectorAll("button")].some((b) => b.textContent === "Check now")).toBe(false);
  });
});
