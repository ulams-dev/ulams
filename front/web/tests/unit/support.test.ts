import { afterEach, describe, expect, it, vi } from "vitest";
import type { SupportItem, Tenant } from "@ulams/sdk";
import { validateDocument } from "@ulams/ui/render-core";
import { matchBffRule } from "../../src/lib/bff.ts";
import { learnerLocale } from "../../src/lib/locale.ts";
import { getSupport, supportCardId, supportDocs, supportIsLong } from "../../src/lib/support.ts";

const ID = "01JBCDEFGHJKMNPQRSTVWXYZ01";
const ID2 = "01JBCDEFGHJKMNPQRSTVWXYZ02";
const F1 = "frg_aaaaaaaaaaaa";
const F2 = "frg_bbbbbbbbbbbb";
const PRACTICE = { stem: "Which ratio?", options: [{ text: "1:16", correct: true }, { text: "1:2", correct: false }], explanation: "1:16 is the standard." };

const item = (over: Partial<SupportItem> = {}): SupportItem => ({
  assignmentId: ID,
  kind: "explanation",
  status: "ready",
  reasons: ["You answered this question wrongly 2 times"],
  why: "You answered this question wrongly 2 times",
  feedback: null,
  document: {
    summary: "A short explanation.",
    blocks: [
      { component: "Prose", props: { title: "Another way", markdown: "Text" }, citations: [F1, F2] },
      { component: "PracticeQuestion", props: { title: "Try it", markdown: "Use it", practice: PRACTICE }, citations: [F1] },
    ],
  },
  citations: [{ fragmentId: F1, label: "§1 Brewing basics" }, { fragmentId: F2, label: "§2 Grind size" }],
  aiLabel: "AI-generated, based on this course's sources",
  ...over,
});

describe("supportDocs", () => {
  it("makes one SupportCard per item whose children are the stored blocks, each followed by its sources", () => {
    const [card] = supportDocs([item()], "en");
    expect(card).toMatchObject({
      component: "SupportCard",
      id: `support-${ID}`,
      props: { assignmentId: ID, status: "ready", summary: "A short explanation.", reasons: ["You answered this question wrongly 2 times"], aiLabel: "AI-generated, based on this course's sources", locale: "en" },
    });
    expect(card!.props).not.toHaveProperty("feedback");
    expect(card!.children!.map((c) => c.component)).toEqual(["Prose", "CitationList", "PracticeQuestion", "CitationList", "FeynmanReflection"]);
    expect(card!.children![4]).toMatchObject({ id: `support-${ID}-reflect`, props: { assignmentId: ID, locale: "en" } });
    expect(card!.children![0]!.props).toEqual({ title: "Another way", markdown: "Text" });
    expect(card!.children![1]!.props).toEqual({ items: [{ label: "§1 Brewing basics" }, { label: "§2 Grind size" }], locale: "en" });
    expect(card!.children![2]!.props).toMatchObject({ title: "Try it", practice: PRACTICE, locale: "en" });
    expect(card!.children![3]!.props).toEqual({ items: [{ label: "§1 Brewing basics" }], locale: "en" });
  });

  it("only blocks with strings of their own get the interface language, so the closed props of the others stay valid", () => {
    const [card] = supportDocs([item()], "pl");
    expect(card!.children![0]!.props).not.toHaveProperty("locale");
    expect(card!.children![2]!.props).toHaveProperty("locale", "pl");
    expect(validateDocument(card!)).toEqual([]);
  });

  it("gives every node a unique id, so two cards and their blocks never clash", () => {
    const docs = supportDocs([item(), item({ assignmentId: ID2 })], "en");
    const ids: string[] = [];
    const walk = (n: { id?: string; children?: unknown[] }) => {
      if (n.id) ids.push(n.id);
      for (const c of n.children ?? []) walk(c as never);
    };
    docs.forEach(walk);
    expect(ids).toHaveLength(2 + 10);
    expect(new Set(ids).size).toBe(ids.length);
  });

  it("numbers the cards only when a lesson has several, so every region has its own name", () => {
    expect(supportDocs([item()], "en")[0]!.props).not.toHaveProperty("position");
    const docs = supportDocs([item(), item({ assignmentId: ID2, status: "generating", document: null }), item({ assignmentId: ID, document: null })], "en");
    expect(docs.map((d) => [d.props!.position, d.props!.total])).toEqual([[1, 2], [2, 2]]);
    for (const doc of docs) expect(validateDocument(doc)).toEqual([]);
  });

  it("carries the saved feedback and never more than six reasons", () => {
    const [card] = supportDocs([item({ feedback: "not_helpful", reasons: Array.from({ length: 9 }, (_, i) => `r${i}`) })], "en");
    expect(card!.props).toMatchObject({ feedback: "not_helpful" });
    expect((card!.props!.reasons as string[]).length).toBe(6);
    expect(validateDocument(card!)).toEqual([]);
  });

  it("a generating item is only a status: no blocks, no controls", () => {
    const [card] = supportDocs([item({ status: "generating", document: null, citations: [] })], "zh");
    expect(card).toEqual({ component: "SupportCard", id: `support-${ID}`, props: { assignmentId: ID, status: "generating", locale: "zh" } });
    expect(validateDocument(card!)).toEqual([]);
  });

  it("skips an item without a document and a block outside the remediation catalogue", () => {
    expect(supportDocs([item({ document: null })], "en")).toEqual([]);
    const [card] = supportDocs([item({ document: { summary: "s", blocks: [{ component: "Hero", props: { title: "x" }, citations: [F1] }, { component: "WorkedExample", props: { title: "t", markdown: "m" }, citations: [] }] } })], "en");
    expect(card!.children!.map((c) => c.component)).toEqual(["WorkedExample", "FeynmanReflection"]);
  });

  it("shows no source list for a citation the API did not resolve, and each source once", () => {
    const [card] = supportDocs([item({ document: { summary: "s", blocks: [{ component: "Prose", props: { markdown: "m" }, citations: ["frg_cccccccccccc"] }, { component: "Prose", props: { markdown: "n" }, citations: [F1, F1] }] } })], "en");
    expect(card!.children!.map((c) => c.component)).toEqual(["Prose", "Prose", "CitationList", "FeynmanReflection"]);
    expect((card!.children![2]!.props!.items as unknown[]).length).toBe(1);
  });

  it("citations are labels as text: no link is invented", () => {
    const [card] = supportDocs([item()], "en");
    for (const child of card!.children!.filter((c) => c.component === "CitationList")) {
      for (const source of child.props!.items as Array<Record<string, unknown>>) expect(source).not.toHaveProperty("href");
    }
  });

  it("asks for a reflection only on a ready card, never on a generating one", () => {
    const [ready] = supportDocs([item()], "pl");
    expect(ready!.children!.at(-1)!.props).toMatchObject({ assignmentId: ID, locale: "pl" });
    const [generating] = supportDocs([item({ status: "generating", document: null, citations: [] })], "en");
    expect(generating!.children).toBeUndefined();
  });

  it("offers the header link only when the card is long", () => {
    expect(supportIsLong(supportDocs([item()], "en"))).toBe(true);
    const short = item({ document: { summary: "s", blocks: [{ component: "Prose", props: { markdown: "m" }, citations: [F1] }] } });
    expect(supportIsLong(supportDocs([short], "en"))).toBe(false);
    expect(supportCardId({ assignmentId: ID })).toBe(`support-${ID}`);
  });
});

describe("getSupport", () => {
  const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost", adminUrl: "http://coffee.admin.localhost" } as Tenant;
  afterEach(() => vi.unstubAllGlobals());

  it("asks the API with the learner's token and locale, and returns the items", async () => {
    const calls: Array<{ url: string; headers: Record<string, string> }> = [];
    vi.stubGlobal("fetch", async (url: string, init: RequestInit) => {
      calls.push({ url, headers: init.headers as Record<string, string> });
      return new Response(JSON.stringify({ data: { enabled: true, items: [item()] } }), { status: 200 });
    });
    const items = await getSupport(tenant, "tok", 5, 12, "pl");
    expect(items).toHaveLength(1);
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/learner-insights/courses/5/topics/12/support");
    expect(calls[0]!.headers.Authorization).toBe("Bearer tok");
    expect(calls[0]!.headers["X-Locale"]).toBe("pl");
  });

  it("means no card when the module is off for the learner, the API errors or the answer is slow", async () => {
    vi.stubGlobal("fetch", async () => new Response(JSON.stringify({ data: { enabled: false, items: [] } }), { status: 200 }));
    expect(await getSupport(tenant, "tok", 5, 12, "en")).toEqual([]);
    vi.stubGlobal("fetch", async () => new Response("{}", { status: 404 }));
    expect(await getSupport(tenant, "tok", 5, 12, "en")).toEqual([]);
    vi.stubGlobal("fetch", async () => {
      throw new Error("offline");
    });
    expect(await getSupport(tenant, "tok", 5, 12, "en")).toEqual([]);
  });
});

describe("BFF rules for extra help", () => {
  it("forwards the support read, feedback, hide and reflection, and nothing else of Learner Insights", () => {
    expect(matchBffRule("GET", "/api/learner-insights/courses/5/topics/12/support")).not.toBeNull();
    expect(matchBffRule("POST", `/api/learner-insights/assignments/${ID}/feedback`)).not.toBeNull();
    expect(matchBffRule("POST", `/api/learner-insights/assignments/${ID}/hide`)).not.toBeNull();
    expect(matchBffRule("POST", `/api/learner-insights/assignments/${ID}/reflection`)).not.toBeNull();
    expect(matchBffRule("GET", `/api/learner-insights/assignments/${ID}/reflection`)).toBeNull();
    expect(matchBffRule("POST", "/api/learner-insights/assignments/short/reflection")).toBeNull();
    expect(matchBffRule("POST", `/api/learner-insights/assignments/${ID}/reflection/x`)).toBeNull();
    expect(matchBffRule("POST", "/api/learner-insights/courses/5/topics/12/support")).toBeNull();
    expect(matchBffRule("GET", `/api/learner-insights/assignments/${ID}/feedback`)).toBeNull();
    expect(matchBffRule("DELETE", `/api/learner-insights/assignments/${ID}/hide`)).toBeNull();
    expect(matchBffRule("POST", "/api/learner-insights/assignments/short/hide")).toBeNull();
    expect(matchBffRule("POST", `/api/learner-insights/assignments/${ID}/hide/../../me`)).toBeNull();
    expect(matchBffRule("GET", "/api/learner-insights/courses/x/topics/1/support")).toBeNull();
    // the learner's own data and the admin analytics are not reachable from the browser
    expect(matchBffRule("GET", "/api/learner-insights/me")).toBeNull();
    expect(matchBffRule("DELETE", "/api/learner-insights/me")).toBeNull();
    expect(matchBffRule("GET", "/api/admin/learner-insights/courses/5/learners")).toBeNull();
  });
});

describe("learnerLocale", () => {
  it("follows the course language: pl or zh by code, else English", () => {
    expect(learnerLocale("pl")).toBe("pl");
    expect(learnerLocale("pl-PL")).toBe("pl");
    expect(learnerLocale("pl_PL")).toBe("pl");
    expect(learnerLocale("PL")).toBe("pl");
    expect(learnerLocale("zh-Hans")).toBe("zh");
    expect(learnerLocale("Polish")).toBe("pl");
    expect(learnerLocale("en")).toBe("en");
    expect(learnerLocale("de")).toBe("en");
    expect(learnerLocale("plain")).toBe("en");
  });

  it("uses the tenant default only when the course has no language", () => {
    expect(learnerLocale(null, "pl")).toBe("pl");
    expect(learnerLocale("", "zh")).toBe("zh");
    expect(learnerLocale("en", "pl")).toBe("en");
    expect(learnerLocale(undefined, undefined)).toBe("en");
  });
});
