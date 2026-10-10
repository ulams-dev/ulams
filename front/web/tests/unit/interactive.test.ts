import { afterEach, describe, expect, it, vi } from "vitest";
import type { Course, Tenant, Topic } from "@ulams/sdk";
import { matchBffRule } from "../../src/lib/bff.ts";
import { fetchShowcase, interactiveLaunch, interactiveNode, showcaseProps, interactivePreview, isImmersive, pickLocale, type InteractiveLaunch } from "../../src/lib/interactive.ts";
import { completionMode, topicDoc } from "../../src/lib/page-docs.ts";

const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost" } as Tenant;
const course = { id: 7, language: "pl" } as Course;

const launch = (over: Partial<InteractiveLaunch["topic"]> = {}, manifest: Partial<InteractiveLaunch["manifest"]> = {}): InteractiveLaunch => ({
  url: "https://content.test/interactive/k/v1/index.html",
  version: 1,
  manifest: {
    title: { en: "Gravity" },
    steps: [
      { id: "intro", title: { en: "Intro", pl: "Wstęp" }, text: { en: "Start", pl: "Początek" }, poster: "https://content.test/p.webp" },
      { id: "last", title: { en: "Last" }, text: { en: "End" } },
    ],
    capabilities: { reducedMotion: true },
    requires: ["webgl"],
    locales: ["en", "pl"],
    defaultLocale: "en",
    licence: "MIT",
    attribution: "The authors",
    source: { url: "https://example.com/g" },
    ...manifest,
  },
  topic: { start_step: "intro", end_step: "last", completion_rule: "on_range_end", pass_score: null, display: "background", height: 700, text: "Hello", ...over },
});

afterEach(() => vi.unstubAllGlobals());

describe("pickLocale", () => {
  it("uses the course language when the package has it, else its default", () => {
    expect(pickLocale({ locales: ["en", "pl"], defaultLocale: "en" }, "pl")).toBe("pl");
    expect(pickLocale({ locales: ["en", "pl"], defaultLocale: "en" }, "PL-pl")).toBe("pl");
    expect(pickLocale({ locales: ["en"], defaultLocale: "en" }, "pl")).toBe("en");
    expect(pickLocale({ locales: ["en"], defaultLocale: "en" }, null)).toBe("en");
  });
});

describe("interactiveNode", () => {
  it("builds the catalogue node with the steps in the course language and the topic's settings", () => {
    const node = interactiveNode(launch(), { title: "Too slow", course, topicId: 42, preview: false });
    expect(node.component).toBe("InteractiveLesson");
    expect(node.props).toMatchObject({
      src: "https://content.test/interactive/k/v1/index.html",
      title: "Too slow",
      topicId: 42,
      courseId: 7,
      display: "background",
      height: 700,
      startStep: "intro",
      endStep: "last",
      text: "Hello",
      requires: ["webgl"],
      reducedMotionSupported: true,
      locale: "pl",
      licence: "MIT",
      attribution: "The authors",
      sourceUrl: "https://example.com/g",
    });
    expect((node.props as { steps: unknown[] }).steps).toEqual([
      { id: "intro", title: "Wstęp", text: "Początek", poster: "https://content.test/p.webp" },
      { id: "last", title: "Last", text: "End" },
    ]);
  });

  it("leaves the ids out in a preview so nothing is tracked", () => {
    const props = interactiveNode(launch(), { title: "T", course, topicId: 42, preview: true }).props as Record<string, unknown>;
    expect(props.topicId).toBeUndefined();
    expect(props.courseId).toBeUndefined();
  });

  it("explains an error or a missing launch in a callout", () => {
    expect(interactiveNode({ error: "Nope" }, { title: "T", course, topicId: 1, preview: false })).toMatchObject({ component: "Callout", props: { text: "Nope" } });
    expect(interactiveNode(null, { title: "T", course, topicId: 1, preview: false }).component).toBe("Callout");
  });

  it("only a package that says so handles reduced motion itself", () => {
    const props = interactiveNode(launch({}, { capabilities: {} }), { title: "T", course, topicId: 1, preview: false }).props as Record<string, unknown>;
    expect(props.reducedMotionSupported).toBe(false);
  });
});

describe("isImmersive", () => {
  it("is the full-bleed layout only for a launched package in background mode", () => {
    expect(isImmersive(launch())).toBe(true);
    expect(isImmersive(launch({ display: "inline" }))).toBe(false);
    expect(isImmersive({ error: "x" })).toBe(false);
    expect(isImmersive(null)).toBe(false);
  });
});

describe("launches", () => {
  it("posts the launch with the learner's token, server side", async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: launch() }) });
    vi.stubGlobal("fetch", fetchMock);
    const result = await interactiveLaunch(tenant, "tok", 42);
    expect("url" in result && result.url).toContain("/interactive/");
    expect(fetchMock.mock.calls[0]![0]).toBe("http://coffee.localhost/api/interactive/launches/42");
    expect(fetchMock.mock.calls[0]![1].method).toBe("POST");
    expect(fetchMock.mock.calls[0]![1].headers.Authorization).toBe("Bearer tok");
  });

  it("returns the API's explanation, or a generic one when it cannot be reached", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: false, json: async () => ({ message: "Forbidden" }) }));
    expect(await interactiveLaunch(tenant, "tok", 1)).toEqual({ error: "Forbidden" });
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("down")));
    expect(await interactiveLaunch(tenant, "tok", 1)).toEqual({ error: "This interactive cannot be opened right now." });
  });

  it("previews a pinned version with the topic's own settings and absolute poster URLs", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: { url: "https://content.test/interactive/k/v2/index.html", version: 2, manifest: { ...launch().manifest, steps: [{ id: "a", title: { en: "A" }, text: { en: "a" }, poster: "posters/a.webp" }] } } }),
    });
    vi.stubGlobal("fetch", fetchMock);
    const result = await interactivePreview(tenant, "author", { value: 9, version: 2, display: "background", height: 500, end_step: "a" });
    expect(fetchMock.mock.calls[0]![0]).toBe("http://coffee.localhost/api/admin/interactive/9/preview?version=2");
    expect(result).toMatchObject({ version: 2, topic: { display: "background", height: 500, end_step: "a", start_step: null } });
    expect("manifest" in result && result.manifest.steps[0]!.poster).toBe("https://content.test/interactive/k/v2/posters/a.webp");

    fetchMock.mockClear();
    await interactivePreview(tenant, "author", { value: 9, version: 2, follow_latest: true });
    expect(fetchMock.mock.calls[0]![0]).toBe("http://coffee.localhost/api/admin/interactive/9/preview");
  });
});

describe("topic document and completion", () => {
  const topic = { id: 42, title: "Too slow", topicable_type: "Ulams\\Interactive\\Models\\InteractiveTopic", topicable: { value: 9, text: "Own text" } } as unknown as Topic;
  const input = { tenant, theme: "coffee" as const, course, lesson: undefined, topic, access: true, nextHref: "/next" };

  it("is completed by the package, through ulams:complete", () => {
    expect(completionMode(topic)).toBe("external");
  });

  it("renders the lesson node, or the callout and the topic's text when the launch failed", () => {
    const ok = topicDoc({ ...input, interactive: launch() });
    expect(ok.children?.map((c) => c.component)).toEqual(["InteractiveLesson"]);
    const failed = topicDoc({ ...input, interactive: { error: "Switched off" } });
    expect(failed.children?.map((c) => c.component)).toEqual(["Callout", "Prose"]);
    const preview = topicDoc({ ...input, interactive: null, preview: true });
    expect(preview.children?.[0]).toMatchObject({ component: "Callout", props: { title: "Not launched in preview" } });
  });
});

describe("BFF", () => {
  it("forwards only the events call of an Interactive topic", () => {
    expect(matchBffRule("POST", "/api/interactive/topics/42/events")?.writesProgress).toBe(true);
    expect(matchBffRule("POST", "/api/interactive/launches/42")).toBeNull();
    expect(matchBffRule("GET", "/api/interactive/topics/42/events")).toBeNull();
    expect(matchBffRule("POST", "/api/interactive/topics/x/events")).toBeNull();
    expect(matchBffRule("POST", "/api/admin/interactive")).toBeNull();
  });
});

describe("landing showcase", () => {
  afterEach(() => vi.unstubAllGlobals());

  it("builds hero props with the localised text and no tracking ids", () => {
    const props = showcaseProps(launch(), "pl");
    expect(props).toMatchObject({ src: "https://content.test/interactive/k/v1/index.html", title: "Gravity", locale: "pl", licence: "MIT", requires: ["webgl"], reducedMotionSupported: true });
    expect((props.steps as Array<{ title: string }>)[0]!.title).toBe("Wstęp");
    expect(props).not.toHaveProperty("topicId");
    expect(props).not.toHaveProperty("courseId");
  });

  it("loops the steps and the still the manifest names, and links to the lesson", () => {
    const props = showcaseProps(
      launch({}, { showcase: { steps: ["intro", "last"], poster: "https://content.test/p/showcase.webp" } }),
      "pl",
      "/learn/7"
    );
    expect(props.showcase).toEqual({ steps: ["intro", "last"], poster: "https://content.test/p/showcase.webp" });
    expect(props.href).toBe("/learn/7");
    expect(props.tryLabel).toBe("Wypróbuj");
    expect(showcaseProps(launch(), "en").tryLabel).toBe("Try it");
    expect(showcaseProps(launch())).not.toHaveProperty("href");
  });

  it("falls back to the first steps and the poster of the first for a package without a showcase", () => {
    const props = showcaseProps(launch());
    expect((props.showcase as { steps: string[] }).steps[0]).toBe("intro");
    expect((props.showcase as { poster?: string }).poster).toBe("https://content.test/p.webp");
  });

  it("does not pass the topic's step range or a fixed height: the loop is the package's own", () => {
    const props = showcaseProps(launch({ start_step: "intro", end_step: "intro", display: "inline", height: 720 }));
    for (const key of ["startStep", "endStep", "height"]) expect(props).not.toHaveProperty(key);
  });

  it("fetches the public endpoint without credentials and treats any failure as none", async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: launch() }) });
    vi.stubGlobal("fetch", fetchMock);
    expect((await fetchShowcase(tenant))?.url).toContain("content.test");
    expect(fetchMock.mock.calls[0]![0]).toBe("http://coffee.localhost/api/interactive/showcase");
    expect(JSON.stringify(fetchMock.mock.calls[0]![1])).not.toContain("Authorization");

    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: false, json: async () => ({}) }));
    expect(await fetchShowcase(tenant)).toBeNull();
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("down")));
    expect(await fetchShowcase(tenant)).toBeNull();
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: { url: 5 } }) }));
    expect(await fetchShowcase(tenant)).toBeNull();
  });
});
