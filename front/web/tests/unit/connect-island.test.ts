// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";

const SESSION = "01m4fj5zsgfxhzh3cantwevncw";

interface Call {
  method: string;
  url: string;
  body: unknown;
}

function stubApi(connect: () => Response, connectors: unknown[] = []) {
  if (typeof AbortSignal.timeout !== "function") (AbortSignal as unknown as { timeout: () => AbortSignal }).timeout = () => new AbortController().signal;
  const calls: Call[] = [];
  vi.stubGlobal("fetch", async (url: string, init?: RequestInit) => {
    const method = init?.method ?? "GET";
    calls.push({ method, url, body: typeof init?.body === "string" ? JSON.parse(init.body) : undefined });
    if (method === "GET" && url.endsWith("/connectors")) return new Response(JSON.stringify({ success: true, data: connectors }));
    if (method === "POST" && url.endsWith("/oauth/google/start")) return new Response(JSON.stringify({ success: true, data: { url: "https://accounts.google.com/o/oauth2/v2/auth?state=abc" } }));
    if (method === "POST" && url.endsWith("/sessions")) return new Response(JSON.stringify({ success: true, data: { session: { id: SESSION } } }), { status: 201 });
    if (method === "DELETE" && url.endsWith(`/sessions/${SESSION}`)) return new Response(JSON.stringify({ success: true, data: { id: SESSION } }));
    if (url.endsWith(`/sessions/${SESSION}/sources/connect`)) return connect();
    return new Response("{}", { status: 404 });
  });
  return calls;
}

const gitResult = (secret: string | null) =>
  new Response(JSON.stringify({
    success: true,
    data: {
      connection: { id: "c1", connector: "git", config: { host: "github", repository: "ulams-dev/docs" }, webhookUrl: "http://api.test/api/living-course/webhooks/w1", secretsSet: [] },
      source: { id: "src1", name: "ulams-dev/docs", title: null },
      webhookSecret: secret,
    },
  }), { status: 201 });

async function mount(navigate = vi.fn()) {
  // the module mounts itself when the page has the section; add it after the import to mount once, with the injected navigate
  document.body.innerHTML = `<div data-announcer></div>`;
  vi.resetModules();
  const { mountConnect } = await import("../../src/studio/connect.ts");
  document.body.insertAdjacentHTML("afterbegin", `<section data-studio-connect></section>`);
  const root = document.querySelector<HTMLElement>("[data-studio-connect]")!;
  mountConnect(root, { navigate });
  return { root, navigate };
}

const type = (root: HTMLElement, selector: string, value: string) => {
  const el = root.querySelector<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>(selector)!;
  el.value = value;
  el.dispatchEvent(new Event("input", { bubbles: true }));
};
const submit = (root: HTMLElement, selector: string) => root.querySelector<HTMLFormElement>(selector)!.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));

afterEach(() => {
  vi.unstubAllGlobals();
  window.sessionStorage.clear();
  window.history.replaceState(null, "", "/");
});

const accountConnectors = (configured = true) => [
  { key: "git", label: "Git repository", configSchema: {}, secretFields: ["token"], webhooks: true },
  { key: "google-drive", label: "Google Drive", configSchema: {}, secretFields: ["refresh_token"], webhooks: false, oauth: { provider: "google", configured } },
];

describe("connect forms island", () => {
  it("renders both forms with labelled fields", async () => {
    const { root } = await mount();
    expect(root.querySelector("[data-git-form] h2")?.textContent).toBe("Connect a repository");
    expect(root.querySelector("[data-url-form] h2")?.textContent).toBe("Add web pages");
    for (const control of root.querySelectorAll("input, select, textarea")) {
      expect(root.querySelector(`label[for="${control.id}"]`), control.outerHTML).not.toBeNull();
    }
    // the server address is for Gitea, Forgejo and self-hosted GitLab only
    const server = root.querySelector<HTMLInputElement>("[name=base_url]")!;
    expect(server.closest<HTMLElement>(".st-field")!.hidden).toBe(true);
    const host = root.querySelector<HTMLSelectElement>("[name=host]")!;
    host.value = "gitea";
    host.dispatchEvent(new Event("change"));
    expect(server.closest<HTMLElement>(".st-field")!.hidden).toBe(false);
  });

  it("refuses a malformed repository before any request", async () => {
    const calls = stubApi(() => gitResult(null));
    const { root } = await mount();
    type(root, "[name=repository]", "docs");
    submit(root, "[data-git-form]");
    expect(root.querySelector("[data-git-form] [role=alert]")?.textContent).toMatch(/owner\/name/);
    expect(calls.filter((c) => c.method !== "GET")).toHaveLength(0);
  });

  it("connects a repository, shows the secret once and continues into the interview", async () => {
    const calls = stubApi(() => gitResult("s3cret-value"));
    const { root, navigate } = await mount();
    type(root, "[name=repository]", "https://github.com/ulams-dev/docs");
    type(root, "[name=paths]", "docs/**/*.md");
    type(root, "[name=token]", "ghp_abc");
    submit(root, "[data-git-form]");
    expect(root.querySelector("[data-git-form] [role=status]")?.textContent).toMatch(/Checking the repository/);
    await vi.waitFor(() => expect(root.querySelector("[data-connected]")!.hasAttribute("hidden")).toBe(false));
    const connect = calls.find((c) => c.url.endsWith("/sources/connect"))!;
    expect(connect.body).toEqual({
      connector: "git",
      config: { host: "github", repository: "ulams-dev/docs", paths: ["docs/**/*.md"] },
      secrets: { token: "ghp_abc" },
      schedule: "daily",
    });
    expect(root.querySelector<HTMLInputElement>("[data-secret] input")!.value).toBe("s3cret-value");
    expect(root.querySelector("[data-secret-warning]")?.textContent).toMatch(/only once/);
    expect(root.querySelector<HTMLInputElement>("[data-webhook-url]")!.value).toContain("/webhooks/w1");
    expect(root.querySelector("[data-hints]")?.textContent).toContain("Just the push event");
    expect(root.querySelector<HTMLAnchorElement>("[data-continue]")!.getAttribute("href")).toBe(`/studio/s/${SESSION}`);
    expect(navigate).not.toHaveBeenCalled();
  });

  it("goes straight to the builder for web pages (no webhook secret)", async () => {
    const calls = stubApi(
      () => new Response(JSON.stringify({ success: true, data: { connection: { id: "c2", connector: "url", config: {}, webhookUrl: null }, source: { id: "src2", name: "docs.example.com" }, webhookSecret: null } }), { status: 201 })
    );
    const { root, navigate } = await mount();
    type(root, "[name=urls]", "https://docs.example.com/a\nhttps://docs.example.com/b");
    type(root, "[name=selector]", "main");
    submit(root, "[data-url-form]");
    await vi.waitFor(() => expect(navigate).toHaveBeenCalledWith(`/studio/s/${SESSION}`));
    expect(calls.find((c) => c.url.endsWith("/sources/connect"))!.body).toEqual({
      connector: "url", config: { urls: ["https://docs.example.com/a", "https://docs.example.com/b"], selector: "main" }, schedule: "daily",
    });
  });

  it("shows the API's refusal as it is, removes the empty session and lets the author try again", async () => {
    const calls = stubApi(() => new Response(JSON.stringify({ success: false, message: "The page must not point to private addresses: https://127.0.0.1/" }), { status: 422 }));
    const { root, navigate } = await mount();
    type(root, "[name=urls]", "https://127.0.0.1/");
    submit(root, "[data-url-form]");
    await vi.waitFor(() => expect(root.querySelector("[data-url-form] [role=alert]")?.textContent).toBe("The page must not point to private addresses: https://127.0.0.1/"));
    expect(calls.some((c) => c.method === "DELETE" && c.url.endsWith(`/sessions/${SESSION}`))).toBe(true);
    expect(navigate).not.toHaveBeenCalled();
    expect(root.querySelector<HTMLButtonElement>("[data-url-form] button[type=submit]")!.disabled).toBe(false);
    expect(root.querySelector("[data-url-form] [role=status]")?.textContent).toBe("");
  });

  it("says when the API cannot be reached", async () => {
    stubApi(() => {
      throw new TypeError("offline");
    });
    const { root } = await mount();
    type(root, "[name=repository]", "ulams-dev/docs");
    submit(root, "[data-git-form]");
    await vi.waitFor(() => expect(root.querySelector("[data-git-form] [role=alert]")?.textContent).toMatch(/unreachable/i));
  });

  describe("Google Drive and Notion", () => {
    const driveResult = () => new Response(JSON.stringify({ success: true, data: { connection: { id: "c3", connector: "google-drive", config: { fileId: "x" }, webhookUrl: null }, source: { id: "src3", name: "Brewing guide" }, webhookSecret: null } }), { status: 201 });

    it("stays hidden unless an admin turned a connector on, and then offers only those", async () => {
      stubApi(() => driveResult());
      const { root } = await mount();
      await new Promise((r) => setTimeout(r, 10));
      expect(root.querySelector<HTMLElement>("[data-account-form]")!.hidden).toBe(true);

      stubApi(() => driveResult(), accountConnectors());
      const second = await mount();
      await vi.waitFor(() => expect(second.root.querySelector<HTMLElement>("[data-account-form]")!.hidden).toBe(false));
      expect([...second.root.querySelectorAll<HTMLOptionElement>("[name=account_source] option")].map((o) => o.value)).toEqual(["google-drive"]);
      expect(second.root.querySelector("[data-account-form] h2")?.textContent).toBe("Connect Google Drive or Notion");
      for (const control of second.root.querySelectorAll("[data-account-form] input, [data-account-form] select")) {
        expect(second.root.querySelector(`label[for="${control.id}"]`), control.outerHTML).not.toBeNull();
      }
    });

    it("says so and disables the button when the sign-in is not set up", async () => {
      stubApi(() => driveResult(), accountConnectors(false));
      const { root } = await mount();
      await vi.waitFor(() => expect(root.querySelector("[data-account-form] [role=status]")?.textContent).toMatch(/not set up/));
      expect(root.querySelector<HTMLButtonElement>("[data-account-form] button[type=submit]")!.disabled).toBe(true);
    });

    it("sends the browser to the sign-in and remembers the form without any token", async () => {
      const calls = stubApi(() => driveResult(), accountConnectors());
      const { root, navigate } = await mount();
      await vi.waitFor(() => expect(root.querySelector<HTMLElement>("[data-account-form]")!.hidden).toBe(false));
      type(root, "[name=account_address]", "nope");
      submit(root, "[data-account-form]");
      expect(root.querySelector("[data-account-form] [role=alert]")?.textContent).toMatch(/Paste the address of the Google Docs/);
      expect(calls.filter((c) => c.method === "POST")).toHaveLength(0);

      type(root, "[name=account_address]", "https://docs.google.com/document/d/1AbCdEfGhIjKlMnOp/edit");
      submit(root, "[data-account-form]");
      await vi.waitFor(() => expect(navigate).toHaveBeenCalledWith("https://accounts.google.com/o/oauth2/v2/auth?state=abc"));
      expect(calls.find((c) => c.url.endsWith("/oauth/google/start"))!.body).toEqual({ sessionId: SESSION });
      const stored = String(window.sessionStorage.getItem("ulams.studio.oauth-pending"));
      expect(JSON.parse(stored)).toMatchObject({ sessionId: SESSION, values: { source: "google-drive", address: "https://docs.google.com/document/d/1AbCdEfGhIjKlMnOp/edit" } });
      expect(stored).not.toMatch(/token|grant/i);
    });

    it("finishes the connection when the sign-in returns with a grant", async () => {
      window.sessionStorage.setItem("ulams.studio.oauth-pending", JSON.stringify({ sessionId: SESSION, values: { source: "google-drive", address: "1AbCdEfGhIjKlMnOp", children: false, schedule: "weekly" } }));
      window.history.replaceState(null, "", "/studio/new?provider=google&grant=0123abcd");
      const calls = stubApi(() => driveResult(), accountConnectors());
      const { navigate } = await mount();
      await vi.waitFor(() => expect(navigate).toHaveBeenCalledWith(`/studio/s/${SESSION}`));
      expect(calls.find((c) => c.url.endsWith("/sources/connect"))!.body).toEqual({ connector: "google-drive", config: { fileId: "1AbCdEfGhIjKlMnOp" }, secrets: { grant: "0123abcd" }, schedule: "weekly" });
      expect(calls.filter((c) => c.method === "POST" && c.url.endsWith("/sessions"))).toHaveLength(0);
      expect(window.location.search).toBe("");
      expect(window.sessionStorage.getItem("ulams.studio.oauth-pending")).toBeNull();
    });

    it("shows a refused or cancelled sign-in and removes the empty session", async () => {
      window.sessionStorage.setItem("ulams.studio.oauth-pending", JSON.stringify({ sessionId: SESSION, values: { source: "google-drive", address: "1AbCdEfGhIjKlMnOp", children: false, schedule: "daily" } }));
      window.history.replaceState(null, "", "/studio/new?provider=google&oauth_error=" + encodeURIComponent("The sign-in was cancelled or refused. Start again."));
      const calls = stubApi(() => driveResult(), accountConnectors());
      const { root, navigate } = await mount();
      await vi.waitFor(() => expect(root.querySelector("[data-account-form] [role=alert]")?.textContent).toBe("The sign-in was cancelled or refused. Start again."));
      expect(calls.some((c) => c.method === "DELETE" && c.url.endsWith(`/sessions/${SESSION}`))).toBe(true);
      expect(navigate).not.toHaveBeenCalled();
    });
  });
});
