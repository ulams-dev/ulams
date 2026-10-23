// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mountReviewTime, startReviewTime, type ReviewEnvironment } from "../../src/studio/review-time.ts";

const T0 = Date.UTC(2026, 9, 20, 10, 30, 0);

/** One shared "browser" of channels, so tabs can talk to each other. */
class Hub {
  readonly tabs = new Set<{ id: number; onmessage: ((e: MessageEvent) => void) | null }>();
  make() {
    const tab: { id: number; onmessage: ((e: MessageEvent) => void) | null } = { id: this.tabs.size, onmessage: null };
    this.tabs.add(tab);
    return {
      get onmessage() { return tab.onmessage; },
      set onmessage(fn) { tab.onmessage = fn; },
      postMessage: (data: unknown) => { for (const other of this.tabs) if (other !== tab) other.onmessage?.({ data } as MessageEvent); },
      close: () => { this.tabs.delete(tab); },
    };
  }
}

function env(hub: Hub, over: Partial<ReviewEnvironment> & { posts?: unknown[]; status?: number } = {}): ReviewEnvironment {
  const posts = over.posts ?? [];
  return {
    now: () => Date.now(),
    visible: () => true,
    focused: () => true,
    channel: () => hub.make() as never,
    beacon: (_url, body) => (posts.push(JSON.parse(body)), true),
    fetch: (async (url: string, init?: RequestInit) => {
      if (String(url).endsWith("/status")) return new Response(JSON.stringify({ data: { enabled: true } }), { status: 200 });
      posts.push(JSON.parse(String(init?.body)));
      return new Response("{}", { status: over.status ?? 200 });
    }) as typeof fetch,
    ...over,
  };
}

beforeEach(() => {
  vi.useFakeTimers();
  vi.setSystemTime(T0);
});
afterEach(() => {
  vi.useRealTimers();
  document.body.innerHTML = "";
  delete document.body.dataset.reviewCourse;
});

const press = () => window.dispatchEvent(new KeyboardEvent("keydown", { key: "a" }));

describe("startReviewTime", () => {
  it("reports an active minute once a minute, with the course, the surface and a random code and nothing else", async () => {
    const posts: Array<Record<string, unknown>> = [];
    const run = startReviewTime(12, env(new Hub(), { posts }));
    press();
    await vi.advanceTimersByTimeAsync(60_000);

    expect(posts).toHaveLength(1);
    expect(Object.keys(posts[0]).sort()).toEqual(["course_id", "minutes", "surface", "tab_nonce"]);
    expect(posts[0]).toMatchObject({ course_id: 12, surface: "studio" });
    expect(posts[0].tab_nonce).toMatch(/^[A-Za-z0-9_-]{22}$/);
    expect((posts[0].minutes as string[]).length).toBeGreaterThanOrEqual(1);
    run.stop();
  });

  it("reports nothing without input, with a hidden tab or with the window in the background", async () => {
    const posts: unknown[] = [];
    const quiet = startReviewTime(12, env(new Hub(), { posts }));
    await vi.advanceTimersByTimeAsync(120_000);
    quiet.stop();
    const hidden = startReviewTime(12, env(new Hub(), { posts, visible: () => false }));
    press();
    await vi.advanceTimersByTimeAsync(120_000);
    hidden.stop();
    const blurred = startReviewTime(12, env(new Hub(), { posts, focused: () => false }));
    press();
    await vi.advanceTimersByTimeAsync(120_000);
    blurred.stop();

    expect(posts).toEqual([]);
  });

  it("stops counting 120 seconds after the last input", async () => {
    const posts: Array<{ minutes: string[] }> = [];
    const run = startReviewTime(12, env(new Hub(), { posts }));
    press();
    await vi.advanceTimersByTimeAsync(10 * 60_000);

    const minutes = posts.flatMap((p) => p.minutes);
    expect(new Set(minutes).size).toBe(minutes.length);
    expect(minutes.length).toBeLessThanOrEqual(3);
    run.stop();
  });

  it("makes three tabs of one browser count once: only the leader posts, with every tab's minutes", async () => {
    const hub = new Hub();
    const posts: Array<{ tab_nonce: string; minutes: string[] }> = [];
    const tabs = [1, 2, 3].map(() => startReviewTime(12, env(hub, { posts: posts as unknown[] })));
    await vi.advanceTimersByTimeAsync(6_000); // they hear each other
    press(); // every tab sees the input (one window in this test)
    await vi.advanceTimersByTimeAsync(60_000);

    const leader = [...tabs].sort((a, b) => (a.id < b.id ? -1 : 1))[0];
    expect(new Set(posts.map((p) => p.tab_nonce))).toEqual(new Set([leader.id]));
    const minutes = posts.flatMap((p) => p.minutes);
    expect(new Set(minutes).size).toBe(minutes.length);
    tabs.forEach((t) => t.stop());
  });

  it("stops at once when the server says the setting is off (404)", async () => {
    const posts: unknown[] = [];
    const run = startReviewTime(12, env(new Hub(), { posts, status: 404 }));
    press();
    await vi.advanceTimersByTimeAsync(60_000);
    const sent = posts.length;
    press();
    await vi.advanceTimersByTimeAsync(5 * 60_000);

    expect(sent).toBe(1);
    expect(posts).toHaveLength(1);
    run.stop();
  });

  it("keeps minutes pending when offline and sends them with the next call", async () => {
    const posts: Array<{ minutes: string[] }> = [];
    let offline = true;
    const base = env(new Hub(), { posts: posts as unknown[] });
    const run = startReviewTime(12, { ...base, fetch: (async (url: string, init?: RequestInit) => {
      if (offline) throw new Error("offline");
      return base.fetch(url, init);
    }) as typeof fetch });
    press();
    await vi.advanceTimersByTimeAsync(60_000);
    expect(posts).toHaveLength(0);
    offline = false;
    press();
    await vi.advanceTimersByTimeAsync(60_000);

    expect(posts.length).toBeGreaterThanOrEqual(1);
    run.stop();
  });

  it("hands over what is pending when the page goes away, counting the minute in progress", async () => {
    const posts: Array<{ minutes: string[] }> = [];
    const run = startReviewTime(12, env(new Hub(), { posts: posts as unknown[] }));
    press();
    window.dispatchEvent(new Event("pagehide"));

    expect(posts).toHaveLength(1);
    expect(posts[0].minutes).toEqual([new Date(T0).toISOString()]);
    run.stop();
  });

  it("uses the admin surface when asked", async () => {
    const posts: Array<{ surface: string }> = [];
    const run = startReviewTime(12, env(new Hub(), { posts: posts as unknown[] }), "admin");
    press();
    window.dispatchEvent(new Event("pagehide"));

    expect(posts[0].surface).toBe("admin");
    run.stop();
  });
});

describe("mountReviewTime", () => {
  const page = (course?: number) => {
    document.body.innerHTML = '<p data-review-indicator hidden>Review time is recorded</p><div data-announcer></div>';
    if (course) document.body.dataset.reviewCourse = String(course);
  };

  it("starts and shows the indicator only when the academy records review time", async () => {
    page(12);
    const base = env(new Hub());
    await mountReviewTime({ ...base, fetch: (async () => new Response(JSON.stringify({ data: { enabled: false } }), { status: 200 })) as typeof fetch });
    expect(document.querySelector<HTMLElement>("[data-review-indicator]")!.hidden).toBe(true);
  });

  it("shows the indicator and counts when it is on", async () => {
    page(12);
    const posts: unknown[] = [];
    await mountReviewTime(env(new Hub(), { posts }));
    expect(document.querySelector<HTMLElement>("[data-review-indicator]")!.hidden).toBe(false);
    press();
    window.dispatchEvent(new Event("pagehide"));
    expect(posts).toHaveLength(1);
  });

  it("asks nothing when the page has no course", async () => {
    page();
    const fetcher = vi.fn();
    await mountReviewTime({ ...env(new Hub()), fetch: fetcher as never });
    expect(fetcher).not.toHaveBeenCalled();
  });

  it("starts when a client-driven page learns its course", async () => {
    page();
    const posts: unknown[] = [];
    await mountReviewTime(env(new Hub(), { posts }));
    window.dispatchEvent(new CustomEvent("ulams:review-course", { detail: 7 }));
    await vi.advanceTimersByTimeAsync(10);
    press();
    window.dispatchEvent(new Event("pagehide"));

    expect(posts).toEqual([expect.objectContaining({ course_id: 7 })]);
  });

  it("treats an unreachable status call as off", async () => {
    page(12);
    await mountReviewTime({ ...env(new Hub()), fetch: (async () => { throw new Error("offline"); }) as typeof fetch });
    expect(document.querySelector<HTMLElement>("[data-review-indicator]")!.hidden).toBe(true);
  });
});
