import { describe, expect, it } from "vitest";
import type { PublicSettings } from "@ulams/sdk";
import { SwrCache } from "../../src/lib/cache.ts";
import { accentCss } from "../../src/lib/accent.ts";
import { EntryLookupError, EntryResolver, currentEntry, entryCookieName, isPlausibleHost, overlaySettings, parseEntry, runWithEntry, safeNext, visualTheme, type EntryContext, type EntryPoint } from "../../src/lib/entry.ts";

const answer = (overrides: Record<string, unknown> = {}) => ({
  success: true,
  data: {
    tenant: "coffee",
    host: "sales.coffee.example.test",
    kind: "department",
    key: "sales",
    label: "Sales academy",
    target: { group_id: 7 },
    theme: { preset: "nightsky", accent: "#c2552d" },
    seo: { canonical: "tenant", index: false },
    locale: "pl",
    ...overrides,
  },
});

const entry = (overrides: Partial<EntryPoint> = {}): EntryPoint => parseEntry(answer(overrides))!;

describe("parseEntry", () => {
  it("reads the platform's answer", () => {
    expect(parseEntry(answer())).toEqual({
      tenant: "coffee",
      host: "sales.coffee.example.test",
      kind: "department",
      key: "sales",
      label: "Sales academy",
      target: { group_id: 7 },
      theme: { preset: "nightsky", accent: "#c2552d" },
      seo: { canonical: "tenant", index: false },
      locale: "pl",
    });
  });

  it("rejects an answer without an academy, a kind, a key or a host", () => {
    for (const bad of [{ tenant: "Bad Slug" }, { tenant: undefined }, { kind: "everything" }, { key: "Bad Key" }, { host: "" }]) {
      expect(parseEntry(answer(bad))).toBeNull();
    }
    expect(parseEntry(null)).toBeNull();
    expect(parseEntry({ data: "x" })).toBeNull();
  });

  it("drops what the front does not know instead of trusting it", () => {
    const e = entry({ theme: { preset: "no-such-theme", accent: "red", logo_alt: "x".repeat(500) }, target: { group_id: -1, course_id: 1.5, role: "Not A Key" }, locale: "en_US" } as never);
    expect(e.theme).toEqual({});
    expect(e.target).toEqual({});
    expect(e.locale).toBeNull();
  });

  it("treats missing indexing as do-not-index and allows a self canonical for a course only", () => {
    expect(entry({ seo: {} } as never).seo.index).toBe(false);
    expect(entry({ seo: { canonical: "self", index: true } } as never).seo.canonical).toBe("tenant");
    expect(entry({ kind: "course", seo: { canonical: "self", index: true } } as never).seo).toEqual({ canonical: "self", index: true });
  });
});

describe("EntryResolver", () => {
  const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } });

  function setup(handler: (url: URL) => Response | Promise<Response>) {
    let now = 0;
    const calls: string[] = [];
    const resolver = new EntryResolver({
      platformApiUrl: "http://api.localhost/",
      fetch: (async (input: RequestInfo | URL) => {
        const url = new URL(String(input));
        calls.push(url.pathname + url.search);
        return handler(url);
      }) as typeof fetch,
      cache: new SwrCache({ now: () => now }),
    });
    return { resolver, calls, advance: (ms: number) => (now += ms) };
  }

  it("asks the platform with the host without its port and in lower case", async () => {
    const { resolver, calls } = setup(() => json(200, answer()));

    expect((await resolver.byHost("Sales.Coffee.Example.Test:4321"))?.tenant).toBe("coffee");
    expect(calls).toEqual(["/api/platform/entry-points/resolve?host=sales.coffee.example.test"]);
  });

  it("caches a hit for 60 seconds and a miss for 10", async () => {
    const { resolver, calls, advance } = setup((url) => (url.searchParams.get("host") === "a.example.test" ? json(200, answer()) : json(404, { success: false })));

    await resolver.byHost("a.example.test");
    await resolver.byHost("a.example.test");
    expect(await resolver.byHost("nope.example.test")).toBeNull();
    await resolver.byHost("nope.example.test");
    expect(calls).toHaveLength(2);

    advance(11_000);
    await resolver.byHost("nope.example.test"); // stale miss: served from memory, refreshed in the background
    await Promise.resolve();
    expect(calls.filter((c) => c.includes("nope"))).toHaveLength(2);
    advance(40_000);
    await resolver.byHost("a.example.test");
    expect(calls.filter((c) => c.includes("a.example"))).toHaveLength(1);
    advance(21_000);
    await resolver.byHost("a.example.test");
    await Promise.resolve();
    expect(calls.filter((c) => c.includes("a.example"))).toHaveLength(2);
  });

  it("shares one request between concurrent callers", async () => {
    const { resolver, calls } = setup(async () => json(200, answer()));

    await Promise.all([resolver.byHost("a.example.test"), resolver.byHost("a.example.test"), resolver.byHost("A.example.test:80")]);
    expect(calls).toHaveLength(1);
  });

  it("fails when the platform cannot say, and does not turn that into 'no such host'", async () => {
    for (const handler of [() => json(500, {}), () => json(429, {}), () => json(200, { data: { nonsense: true } }), () => Promise.reject(new Error("connection refused"))]) {
      const { resolver } = setup(handler);
      await expect(resolver.byHost("a.example.test")).rejects.toBeInstanceOf(EntryLookupError);
    }
  });

  it("serves the last answer while the platform is down", async () => {
    let up = true;
    const { resolver, advance } = setup(() => (up ? json(200, answer()) : json(503, {})));
    await resolver.byHost("a.example.test");

    up = false;
    advance(120_000);
    expect((await resolver.byHost("a.example.test"))?.key).toBe("sales");
  });

  it("does not ask for what cannot be a host, or when it is off", async () => {
    const { resolver, calls } = setup(() => json(200, answer()));
    for (const host of ["", "localhost", "10.0.0.1", "127.0.0.1:4321", "bad host.example.test", "a..b.test", "a_b.example.test", "-a.example.test"]) {
      expect(await resolver.byHost(host)).toBeNull();
    }
    expect(calls).toEqual([]);

    const off = new EntryResolver({ platformApiUrl: "", fetch: (() => Promise.reject(new Error("must not be called"))) as typeof fetch });
    expect(off.enabled).toBe(false);
    expect(await off.byHost("a.example.test")).toBeNull();
    expect(await off.byKey("coffee", "sales")).toBeNull();
  });

  it("looks a key up with the academy, and refuses malformed ones", async () => {
    const { resolver, calls } = setup(() => json(200, answer()));

    expect((await resolver.byKey("coffee", "sales"))?.host).toBe("sales.coffee.example.test");
    expect(calls).toEqual(["/api/platform/entry-points/resolve?tenant=coffee&key=sales"]);
    for (const [tenant, key] of [["Coffee", "sales"], ["coffee", "Sales"], ["coffee", "a/b"], ["co ffee", "x"], ["coffee", ""]] as Array<[string, string]>) {
      expect(await resolver.byKey(tenant, key)).toBeNull();
    }
    expect(calls).toHaveLength(1);
  });
});

describe("isPlausibleHost", () => {
  it("accepts DNS names and refuses addresses and junk", () => {
    expect(isPlausibleHost("sales.coffee.example.test")).toBe(true);
    expect(isPlausibleHost("xn--sales-9ua.example.org")).toBe(true);
    for (const host of ["localhost", "1.2.3.4", "a.b..c", "a b.c", "a.b.c/d", "a@b.c", "x".repeat(64) + ".example.org", "[::1]"]) expect(isPlausibleHost(host)).toBe(false);
  });
});

describe("overlaySettings", () => {
  const settings = { global: { companyName: "Coffee", language: "en" }, theme: { theme: "coffee", accent: "#112233", logoAlt: "Coffee logo", logo: "http://x/logo.png" } } as PublicSettings;

  it("puts the entry point's accent and language over the academy's, and leaves the preset to the look of the page", () => {
    const merged = overlaySettings(settings, entry())!;

    // theme.theme also picks the academy's landing document and copy: an entry point does not change it
    expect(merged.theme).toEqual({ theme: "coffee", accent: "#c2552d", logoAlt: "Coffee logo", logo: "http://x/logo.png" });
    expect(merged.global).toEqual({ companyName: "Coffee", language: "pl" });
  });

  it("drops the academy's accent when only the preset changes: it was chosen for the academy's own preset", () => {
    const merged = overlaySettings(settings, entry({ theme: { preset: "gravity" } }))!;
    expect(merged.theme?.theme).toBe("coffee");
    expect(merged.theme?.accent).toBeUndefined();
  });

  it("keeps the academy's preset and language when the entry point says nothing", () => {
    const merged = overlaySettings(settings, entry({ theme: {}, locale: null }))!;
    expect(merged).toBe(settings);
  });

  it("never changes the academy's own (cached) settings", () => {
    const before = JSON.stringify(settings);
    overlaySettings(settings, entry());
    expect(JSON.stringify(settings)).toBe(before);
  });

  it("passes null and 'no entry point' through", () => {
    expect(overlaySettings(null, entry())).toBeNull();
    expect(overlaySettings(settings, null)).toBe(settings);
  });

  it("keeps an entry point accent readable: it goes through the same contrast adjustment as the academy's", () => {
    // a pale yellow on the light coffee theme would fail 4.5:1 as text colour; the adjusted primary has to pass 5:1
    const css = accentCss("coffee", "#ffee00");
    const primary = /--ulams-color-primary:(#[0-9a-f]{6})/.exec(css)![1]!;
    expect(primary).not.toBe("#ffee00");
  });
});

describe("visualTheme", () => {
  it("is the entry point's preset when it sets one, else the academy's", () => {
    expect(visualTheme("coffee", entry())).toBe("nightsky");
    expect(visualTheme("coffee", entry({ theme: { accent: "#c2552d" } }))).toBe("coffee");
    expect(visualTheme("coffee", null)).toBe("coffee");
  });
});

describe("the entry point of a request", () => {
  const context: EntryContext = { ...entry(), via: "host" };

  it("is visible to everything the request awaits, and to nothing outside it", async () => {
    expect(currentEntry()).toBeNull();
    const seen = await runWithEntry(context, async () => {
      await new Promise((resolve) => setTimeout(resolve, 1));
      return currentEntry();
    });
    expect(seen?.key).toBe("sales");
    expect(currentEntry()).toBeNull();
  });

  it("keeps two concurrent requests apart", async () => {
    const other: EntryContext = { ...entry({ key: "kubernetes" }), via: "go" };
    const read = (value: EntryContext | null, delay: number) => runWithEntry(value, async () => {
      await new Promise((resolve) => setTimeout(resolve, delay));
      return currentEntry()?.key ?? null;
    });

    expect(await Promise.all([read(context, 5), read(other, 1), read(null, 3)])).toEqual(["sales", "kubernetes", null]);
  });
});

describe("safeNext", () => {
  it("allows a path on this site only", () => {
    expect(safeNext("/courses/1?x=1")).toBe("/courses/1?x=1");
    for (const bad of ["//evil.example/x", "https://evil.example", "/\\evil.example", "/a\nb", "javascript:alert(1)", "evil", "", null, undefined, "/" + "a".repeat(600)]) {
      expect(safeNext(bad)).toBe("/");
    }
  });
});

describe("entryCookieName", () => {
  it("is a __Host- cookie over https and the fallback name over plain http", () => {
    expect(entryCookieName(true)).toBe("__Host-ulams_entry");
    expect(entryCookieName(false)).toBe("ulams_entry");
    expect(entryCookieName(false, "dev-")).toBe("dev-ulams_entry");
  });
});
