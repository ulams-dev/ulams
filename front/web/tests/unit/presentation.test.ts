import { afterEach, describe, expect, it, vi } from "vitest";
import type { Presentation, PresentationState, Tenant, Topic } from "@ulams/sdk";
import { validateDocument } from "@ulams/ui/render-core";
import {
  DEFAULT_PRESENTATION,
  getPresentation,
  hasSuggestion,
  parsePresentationForm,
  profileOf,
  proseNode,
  safeReturn,
  savePresentationChange,
  withInitialHints,
} from "../../src/lib/presentation.ts";
import { topicDoc } from "../../src/lib/page-docs.ts";
import { COFFEE_PROGRAM } from "./fixtures.ts";

const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost", adminUrl: "http://coffee.admin.localhost" } as Tenant;
const STRUGGLING: Presentation = { density: "compact", chunking: "steps", navigation: "linear", hints: 2 };
const state = (over: Partial<PresentationState> = {}): PresentationState => ({
  adaptive: true,
  profile: STRUGGLING,
  derived: STRUGGLING,
  overrides: {},
  why: ["Two lessons in this module were hard"],
  ...over,
});
const answer = (data: unknown, status = 200) => vi.stubGlobal("fetch", async () => new Response(JSON.stringify({ data }), { status }));
afterEach(() => vi.unstubAllGlobals());

describe("getPresentation", () => {
  it("reads the profile of the course with the learner's token", async () => {
    const calls: Array<{ url: string; init: RequestInit }> = [];
    vi.stubGlobal("fetch", async (url: string, init: RequestInit) => {
      calls.push({ url, init });
      return new Response(JSON.stringify({ data: state() }), { status: 200 });
    });
    expect(await getPresentation(tenant, "tok", 5, "pl")).toEqual(state());
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/learner-insights/courses/5/presentation");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBe("Bearer tok");
    expect((calls[0]!.init.headers as Record<string, string>)["X-Locale"]).toBe("pl");
  });

  it("is null when the module is off, the API errors, is slow or answers nonsense, and unknown values fall back to the default", async () => {
    vi.stubGlobal("fetch", async () => new Response("{}", { status: 404 }));
    expect(await getPresentation(tenant, "tok", 5)).toBeNull();
    vi.stubGlobal("fetch", async () => {
      throw new Error("offline");
    });
    expect(await getPresentation(tenant, "tok", 5)).toBeNull();
    answer({ adaptive: true });
    expect(await getPresentation(tenant, "tok", 5)).toBeNull();
    answer({ adaptive: "yes", profile: { density: "huge", chunking: "steps", navigation: 7, hints: 9 }, derived: null, overrides: { hints: 4, density: "compact", other: 1 }, why: ["a", 3] });
    expect(await getPresentation(tenant, "tok", 5)).toEqual({
      adaptive: false,
      profile: { density: "comfortable", chunking: "steps", navigation: "overview", hints: 0 },
      derived: DEFAULT_PRESENTATION,
      overrides: { density: "compact" },
      why: ["a"],
    });
  });

  it("applies the default view without the module", () => {
    expect(profileOf(null)).toEqual(DEFAULT_PRESENTATION);
    expect(profileOf(state({ profile: { ...STRUGGLING, hints: 1 } })).hints).toBe(1);
  });

  it("suggests only when the tenant derives it and it differs from the default view", () => {
    expect(hasSuggestion(state())).toBe(true);
    expect(hasSuggestion(state({ adaptive: false }))).toBe(false);
    expect(hasSuggestion(state({ derived: DEFAULT_PRESENTATION }))).toBe(false);
    expect(hasSuggestion(null)).toBe(false);
  });
});

describe("the Lesson view form", () => {
  const form = (entries: Record<string, string>) => {
    const f = new FormData();
    for (const [k, v] of Object.entries(entries)) f.set(k, v);
    return f;
  };

  it("sends the four groups as a save", () => {
    expect(parsePresentationForm(form({ action: "save", density: "compact", chunking: "steps", navigation: "linear", hints: "3" }))).toEqual({
      kind: "save",
      change: { density: "compact", chunking: "steps", navigation: "linear", hints: 3 },
    });
  });

  it("drops values it does not know and refuses a save with nothing valid", () => {
    expect(parsePresentationForm(form({ action: "save", density: "huge", chunking: "whole", hints: "7" }))).toEqual({ kind: "save", change: { chunking: "whole" } });
    expect(parsePresentationForm(form({ action: "save", hints: "" }))).toBeNull();
    expect(parsePresentationForm(form({ action: "save", hints: "1.5", navigation: "<script>" }))).toBeNull();
    expect(parsePresentationForm(form({ density: "compact" }))).toBeNull();
    expect(parsePresentationForm(form({ action: "erase" }))).toBeNull();
  });

  it("has a reset and a one-click show-the-whole-lesson", () => {
    expect(parsePresentationForm(form({ action: "reset" }))).toEqual({ kind: "reset" });
    expect(parsePresentationForm(form({ action: "whole" }))).toEqual({ kind: "whole" });
  });

  it("calls PUT with the change, null for every setting on reset, and only chunking for the whole lesson", async () => {
    const bodies: unknown[] = [];
    vi.stubGlobal("fetch", async (url: string, init: RequestInit) => {
      expect(init.method).toBe("PUT");
      expect(url).toBe("http://coffee.localhost/api/learner-insights/courses/5/presentation");
      bodies.push(JSON.parse(String(init.body)));
      return new Response(JSON.stringify({ data: state() }), { status: 200 });
    });
    expect(await savePresentationChange(tenant, "tok", 5, { kind: "save", change: { hints: 1 } })).toBe(true);
    expect(await savePresentationChange(tenant, "tok", 5, { kind: "reset" })).toBe(true);
    expect(await savePresentationChange(tenant, "tok", 5, { kind: "whole" })).toBe(true);
    expect(bodies).toEqual([{ hints: 1 }, { density: null, chunking: null, navigation: null, hints: null }, { chunking: "whole" }]);
    vi.stubGlobal("fetch", async () => new Response("{}", { status: 422 }));
    expect(await savePresentationChange(tenant, "tok", 5, { kind: "reset" })).toBe(false);
  });

  it("returns only to the course page or a lesson of the same course", () => {
    expect(safeReturn("/learn/5/12", 5)).toBe("/learn/5/12");
    expect(safeReturn("/learn/5/12?lesson-view=ok#x", 5)).toBe("/learn/5/12");
    expect(safeReturn("/courses/5", 5)).toBe("/courses/5");
    for (const bad of ["/learn/6/12", "//evil.example", "https://evil.example/learn/5/1", "/learn/5/x", "/learn/5/12/../../x", "/\\evil.example", "/learn/5/1\r\nSet-Cookie: a=b", 7, null, undefined]) {
      expect(safeReturn(bad, 5)).toBe("/learn/5");
    }
  });
});

const MD = "*Intro line.*\n\n## Species\n\nTwo of them.\n\n## Altitude\n\nHigh is better.\n\n## Cherries\n\n```\n## inside code\n```\n";
const reading = (value: string): Topic =>
  ({ id: 3, title: "Arabica vs Robusta", lesson_id: 1, topicable_type: "Ulams\\TopicTypes\\Models\\TopicContent\\RichText", topicable: { value } }) as unknown as Topic;
const doc = (topic: Topic, presentation?: Presentation) =>
  topicDoc({ tenant, theme: "platform", course: COFFEE_PROGRAM, lesson: undefined, topic, access: true, nextHref: "/next", ...(presentation ? { presentation, locale: "pl" as const } : {}) });

describe("applying the profile to the lesson", () => {
  it("keeps the reading whole by default", () => {
    expect(doc(reading(MD)).children![0]).toMatchObject({ component: "Prose", props: { markdown: MD } });
  });

  it("turns a reading with at least two sections into parts, in the course language", () => {
    const node = doc(reading(MD), STRUGGLING).children![0]!;
    expect(node.component).toBe("ProseSteps");
    expect(node.props).toMatchObject({ intro: "*Intro line.*", locale: "pl" });
    expect((node.props!.sections as Array<{ heading: string }>).map((s) => s.heading)).toEqual(["Species", "Altitude", "Cherries"]);
    expect(validateDocument(node)).toEqual([]);
  });

  it("leaves a reading with fewer than two sections, or more than twelve, whole", () => {
    expect(doc(reading("## One\n\nonly"), STRUGGLING).children![0]!.component).toBe("Prose");
    const many = Array.from({ length: 13 }, (_, i) => `## S${i}\n\ntext`).join("\n\n");
    expect(proseNode(many, {}, STRUGGLING, "en").component).toBe("Prose");
    expect(proseNode(many.split("## S12")[0]!, {}, STRUGGLING, "en").component).toBe("ProseSteps");
  });

  it("sets the hints shown at the start on every practice activity and nowhere else", () => {
    const nodes = [
      { component: "Callout", props: { text: "x" } },
      { component: "PracticeActivity", props: { intro: "i" } },
    ];
    expect(withInitialHints(nodes, 0)).toBe(nodes);
    const out = withInitialHints(nodes, 2);
    expect(out[0]).toBe(nodes[0]);
    expect(out[1]!.props).toEqual({ intro: "i", initialHints: 2 });
    expect(nodes[1]!.props).toEqual({ intro: "i" });
  });
});
