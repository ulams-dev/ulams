import { describe, expect, it, vi } from "vitest";
import type { Tenant } from "@ulams/sdk";
import {
  applyChoice,
  flashMessage,
  flashQuery,
  getVariantFamily,
  getVariantState,
  isMultiple,
  orderedScores,
  parseChooseForm,
  parseFamily,
  parsePlacementForm,
  parseQuestions,
  parseState,
  pickerView,
  placementResult,
  readFlash,
  scorePlacement,
  type VariantFamily,
  type VariantState,
} from "../../src/lib/variants.ts";

const item = (courseId: number, over: Record<string, unknown> = {}) => ({
  courseId,
  title: `Course ${courseId}`,
  label: ["", "Beginner", "Intermediate", "Expert"][courseId - 10] ?? `V${courseId}`,
  level: ["", "beginner", "intermediate", "expert"][courseId - 10] ?? null,
  role: null,
  isDefault: courseId === 11,
  description: `For people ${courseId}`,
  duration: "3 hours",
  ...over,
});
const family = { groupId: 4, defaultCourseId: 11, variants: [item(11), item(12), item(13, { role: "Barista" })] };
const stateRaw = (over: Record<string, unknown> = {}) => ({
  ...family,
  current: null,
  effectiveCourseId: 11,
  enrolled: true,
  canChange: true,
  lockedReason: null,
  placement: { enabled: true, available: false, lastAttempt: null },
  hint: null,
  ...over,
});
const state = (over: Record<string, unknown> = {}): VariantState => parseState(stateRaw(over))!;
const fam = parseFamily(family) as VariantFamily;

describe("parsing what the API answers", () => {
  it("reads the picker data and refuses a family of fewer than one usable variant", () => {
    expect(parseFamily(family)?.variants.map((v) => v.label)).toEqual(["Beginner", "Intermediate", "Expert"]);
    expect(parseFamily(null)).toBeNull();
    expect(parseFamily({ ...family, variants: [] })).toBeNull();
    expect(parseFamily({ groupId: "x", defaultCourseId: 11, variants: family.variants })).toBeNull();
  });

  it("drops a variant without an id or a title and falls back to the title for a missing label", () => {
    const parsed = parseFamily({ ...family, variants: [item(11, { label: null }), { courseId: 12 }, item(13, { title: "" })] });
    expect(parsed?.variants).toHaveLength(1);
    expect(parsed?.variants[0]?.label).toBe("Course 11");
  });

  it("reads the state, treating anything unknown as the safe value", () => {
    const s = parseState(stateRaw({ current: { courseId: 12, source: "rule", reason: { key: "rule", params: { name: "Sales" } }, byOrganisation: true, decidedAt: "2026-10-20T10:00:00+00:00" }, lockedReason: "weird", canChange: "yes" }));
    expect(s?.current?.byOrganisation).toBe(true);
    expect(s?.canChange).toBe(false);
    expect(s?.lockedReason).toBeNull();
    expect(parseState({ ...family, effectiveCourseId: null })?.effectiveCourseId).toBe(11);
    expect(parseState({ ...family, enrolled: undefined })?.enrolled).toBe(false);
  });

  it("reads the placement questions and tells multiple choice from single choice", () => {
    const questions = parseQuestions({
      questions: [
        { id: 7, type: "multiple", stem: "Pick all", options: [{ id: "a", text: "A" }, { id: "b", text: "B" }] },
        { id: 8, type: "truefalse", stem: "True?", options: [{ id: "t", text: "True" }, { id: "f", text: "False" }] },
        { id: 9, type: "single", stem: "No options", options: [{ id: "a", text: "A" }] },
        { id: 10, type: "single", stem: "", options: [{ id: "a", text: "A" }, { id: "b", text: "B" }] },
      ],
    });
    expect(questions.map((q) => q.id)).toEqual(["7", "8"]);
    expect(questions.map(isMultiple)).toEqual([true, false]);
    expect(parseQuestions(null)).toEqual([]);
  });

  it("orders the scores beginner, intermediate, expert", () => {
    const attempt = { id: "a".repeat(26), scores: { expert: { correct: 1, total: 4 }, beginner: { correct: 3, total: 3 }, intermediate: { correct: 5, total: 6 } }, suggestedCourseId: 12, reason: null, createdAt: null };
    expect(orderedScores(attempt).map((s) => s.level)).toEqual(["beginner", "intermediate", "expert"]);
  });
});

describe("the picker view", () => {
  it("is nothing for a course without variants or with a single one", () => {
    expect(pickerView(null, null, 11, "en")).toBeNull();
    expect(pickerView({ ...fam, variants: [fam.variants[0]!] }, null, 11, "en")).toBeNull();
  });

  it("is a read-only list for a visitor, with no current marker and no placement link", () => {
    const view = pickerView(fam, null, 12, "en")!;
    expect(view.mode).toBe("visitor");
    expect(view.items.some((i) => i.current)).toBe(false);
    expect(view.items.find((i) => i.selected)?.courseId).toBe(12);
    expect(view.items.find((i) => i.courseId === 12)?.href).toBeNull();
    expect(view.items.find((i) => i.courseId === 13)?.href).toBe("/courses/13");
    expect(view.placementHref).toBeNull();
    expect(view.hint).toBeNull();
  });

  it("is also read-only for a signed-in learner who is not enrolled yet", () => {
    const view = pickerView(fam, state({ enrolled: false, canChange: false, lockedReason: "not_enrolled" }), 11, "en")!;
    expect(view.mode).toBe("visitor");
    expect(view.items.some((i) => i.current)).toBe(false);
    expect(view.placementHref).toBeNull();
  });

  it("lets an enrolled learner who may change choose, marking the effective version as current and checked", () => {
    const view = pickerView(fam, state({ effectiveCourseId: 12, current: { courseId: 12, source: "self", reason: null, byOrganisation: false, decidedAt: null } }), 11, "en")!;
    expect(view.mode).toBe("choose");
    expect(view.items.filter((i) => i.current).map((i) => i.courseId)).toEqual([12]);
    expect(view.items.filter((i) => i.selected).map((i) => i.courseId)).toEqual([12]);
    expect(view.elsewhere).toBe(true);
    expect(view.reason).toBe("self");
  });

  it("marks the default course as current for an enrolled learner who never chose", () => {
    const view = pickerView(fam, state(), 11, "en")!;
    expect(view.items.find((i) => i.current)?.isDefault).toBe(true);
    expect(view.reason).toBeNull();
    expect(view.elsewhere).toBe(false);
  });

  it("is locked when the organisation locked the family, and says who assigned it and by which rule", () => {
    const view = pickerView(
      fam,
      state({ canChange: false, lockedReason: "locked", effectiveCourseId: 13, current: { courseId: 13, source: "rule", reason: { key: "rule", params: { name: "Sales team" } }, byOrganisation: true, decidedAt: null } }),
      13,
      "en"
    )!;
    expect(view.mode).toBe("locked");
    expect(view.assignedByOrganisation).toBe(true);
    expect(view.ruleName).toBe("Sales team");
    expect(view.reason).toBeNull();
    expect(view.hint).toBeNull();
  });

  it("says an administrator assigned it without a rule name", () => {
    const view = pickerView(fam, state({ effectiveCourseId: 12, current: { courseId: 12, source: "admin", reason: { key: "admin" }, byOrganisation: true, decidedAt: null } }), 11, "en")!;
    expect(view.mode).toBe("choose");
    expect(view.assignedByOrganisation).toBe(true);
    expect(view.ruleName).toBeNull();
  });

  it("explains a switch the learner made after the placement check or a hint", () => {
    const placement = pickerView(fam, state({ effectiveCourseId: 13, current: { courseId: 13, source: "placement", reason: { key: "placement" }, byOrganisation: false, decidedAt: null } }), 13, "en")!;
    expect(placement.reason).toBe("placement");
    const harder = pickerView(fam, state({ effectiveCourseId: 13, current: { courseId: 13, source: "hint", reason: { key: "hint", params: { direction: "harder" } }, byOrganisation: false, decidedAt: null } }), 13, "en")!;
    expect(harder.reason).toBe("hint-harder");
    const easier = pickerView(fam, state({ effectiveCourseId: 12, current: { courseId: 12, source: "hint", reason: { key: "hint", params: { direction: "easier" } }, byOrganisation: false, decidedAt: null } }), 12, "en")!;
    expect(easier.reason).toBe("hint-easier");
  });

  it("links to the placement check only when it is available and the learner is enrolled", () => {
    expect(pickerView(fam, state({ placement: { enabled: true, available: true, lastAttempt: null } }), 12, "en")!.placementHref).toBe("/courses/12/placement");
    expect(pickerView(fam, state({ placement: { enabled: true, available: false, lastAttempt: null } }), 12, "en")!.placementHref).toBeNull();
  });

  it("shows a hint with its reasons in the learner's language and the suggested version", () => {
    const hint = { id: 5, direction: "easier", courseId: 11, suggestedCourseId: 12, reasons: [{ rule: "r", messageKey: "insights.reason.variant_too_hard", params: { modules: 2 }, message: "API text" }] };
    const pl = pickerView(fam, state({ hint }), 11, "pl")!;
    expect(pl.hint?.suggested.label).toBe("Intermediate");
    expect(pl.hint?.reasons).toEqual(["Pierwsze 2 moduły sprawiają Ci trudność; łatwiejsza wersja tego kursu może lepiej Ci pasować."]);
    expect(pickerView(fam, state({ hint: { ...hint, reasons: [{ rule: "r", messageKey: "other.key", message: "From the API" }] } }), 11, "en")!.hint?.reasons).toEqual(["From the API"]);
  });

  it("shows no hint to someone who may not change, or whose suggested version is the current one or unknown", () => {
    const hint = { id: 5, direction: "easier", courseId: 11, suggestedCourseId: 12, reasons: [] };
    expect(pickerView(fam, state({ hint, canChange: false, lockedReason: "locked" }), 11, "en")!.hint).toBeNull();
    expect(pickerView(fam, state({ hint, effectiveCourseId: 12 }), 11, "en")!.hint).toBeNull();
    expect(pickerView(fam, state({ hint: { ...hint, suggestedCourseId: 99 } }), 11, "en")!.hint).toBeNull();
  });
});

describe("the placement result", () => {
  const attempt = (over: Record<string, unknown> = {}) => ({
    id: "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    scores: { beginner: { correct: 4, total: 4 }, intermediate: { correct: 5, total: 6 } },
    suggestedCourseId: 13,
    reason: { key: "placement", params: { level: "expert", correct: 5, total: 6, basisLevel: "intermediate" } },
    createdAt: null,
    ...over,
  });

  it("names the suggested version and the evidence behind it", () => {
    const result = placementResult(state(), attempt());
    expect(result).toMatchObject({ kind: "suggestion", level: "intermediate", correct: 5, total: 6, alreadyUsing: false });
    expect(result.kind === "suggestion" && result.suggested.label).toBe("Expert");
  });

  it("notices when the suggestion is the version the learner already uses", () => {
    expect(placementResult(state({ effectiveCourseId: 13 }), attempt())).toMatchObject({ kind: "suggestion", alreadyUsing: true });
  });

  it("has no suggestion when the API found none or the variant is gone", () => {
    expect(placementResult(state(), attempt({ suggestedCourseId: null })).kind).toBe("none");
    expect(placementResult(state(), attempt({ suggestedCourseId: 99 })).kind).toBe("none");
  });
});

describe("the forms", () => {
  const form = (fields: Record<string, string>) => {
    const data = new FormData();
    for (const [k, v] of Object.entries(fields)) data.append(k, v);
    return data;
  };

  it("reads the four picker actions and refuses malformed ones", () => {
    expect(parseChooseForm(form({ action: "switch", course_id: "12" }))).toEqual({ kind: "switch", courseId: 12 });
    expect(parseChooseForm(form({ action: "placement", course_id: "12", attempt_id: "01ARZ3NDEKTSV4RRFFQ69G5FAV" }))).toEqual({ kind: "placement", courseId: 12, attemptId: "01ARZ3NDEKTSV4RRFFQ69G5FAV" });
    expect(parseChooseForm(form({ action: "hint-switch", course_id: "12", hint_id: "5" }))).toEqual({ kind: "hint-switch", courseId: 12, hintId: 5 });
    expect(parseChooseForm(form({ action: "hint-dismiss", hint_id: "5" }))).toEqual({ kind: "hint-dismiss", hintId: 5 });
    expect(parseChooseForm(form({ action: "switch" }))).toBeNull();
    expect(parseChooseForm(form({ action: "switch", course_id: "-1" }))).toBeNull();
    expect(parseChooseForm(form({ action: "switch", course_id: "1e3" }))).toBeNull();
    expect(parseChooseForm(form({ action: "placement", course_id: "12", attempt_id: "short" }))).toBeNull();
    expect(parseChooseForm(form({ action: "hint-switch", course_id: "12" }))).toBeNull();
    expect(parseChooseForm(form({ action: "other", course_id: "12" }))).toBeNull();
  });

  it("collects the answers per question, several for a checkbox group, ignoring other fields", () => {
    const data = new FormData();
    data.append("q-7", "a");
    data.append("q-7", "c");
    data.append("q-8", "t");
    data.append("next", "/courses/1");
    data.append("q-bad name", "x");
    expect(parsePlacementForm(data)).toEqual({ "7": ["a", "c"], "8": ["t"] });
  });
});

describe("the flash message", () => {
  it("round-trips through the query string", () => {
    expect(flashQuery({ kind: "switched", courseId: 12, carried: 3 })).toBe("variant=switched&carried=3");
    expect(readFlash(new URLSearchParams("variant=switched&carried=3"))).toEqual({ kind: "switched", carried: 3 });
    expect(readFlash(new URLSearchParams("variant=switched&carried=-4"))).toEqual({ kind: "switched", carried: 0 });
    expect(readFlash(new URLSearchParams(flashQuery({ kind: "locked" })))).toEqual({ kind: "locked" });
    expect(readFlash(new URLSearchParams("variant=<script>"))).toBeNull();
    expect(readFlash(new URLSearchParams(""))).toBeNull();
  });

  it("says how many lessons carried over, in the learner's language", () => {
    const s = state({ effectiveCourseId: 12 });
    expect(flashMessage({ kind: "switched", carried: 3 }, "en", s)).toEqual({ tone: "ok", text: "Your version is now “Intermediate”. 3 completed lessons were carried over from your previous version." });
    expect(flashMessage({ kind: "switched", carried: 1 }, "pl", s).text).toBe("Twoja wersja to teraz „Intermediate”. 1 ukończona lekcja została przeniesiona z poprzedniej wersji.");
    expect(flashMessage({ kind: "switched", carried: 0 }, "zh", s).text).toContain("没有需要沿用");
    expect(flashMessage({ kind: "switched", carried: 2 }, "en", null).text).toBe("2 completed lessons were carried over from your previous version.");
  });

  it("reports the failures as errors and the harmless outcomes as status", () => {
    expect(flashMessage({ kind: "failed" }, "en", null).tone).toBe("error");
    expect(flashMessage({ kind: "locked" }, "en", null).tone).toBe("error");
    expect(flashMessage({ kind: "no-access" }, "en", null).tone).toBe("error");
    expect(flashMessage({ kind: "unchanged" }, "en", null).tone).toBe("ok");
    expect(flashMessage({ kind: "dismissed" }, "pl", null).text).toBe("Sugestie są wyłączone dla tego kursu.");
  });
});

describe("the calls", () => {
  const tenant = { apiUrl: "http://api.test" } as Tenant;
  const reply = (status: number, data: unknown) => new Response(JSON.stringify({ success: status < 400, data }), { status, headers: { "Content-Type": "application/json" } });
  const withFetch = async <T>(handler: (url: string, init: RequestInit) => Response | Promise<Response>, run: () => Promise<T>): Promise<{ result: T; calls: Array<{ url: string; method: string; body: unknown; auth: string | null }> }> => {
    const calls: Array<{ url: string; method: string; body: unknown; auth: string | null }> = [];
    const spy = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, init) => {
      const request = new Request(input as string, init);
      const body = init?.body ? JSON.parse(String(init.body)) : null;
      calls.push({ url: request.url, method: request.method, body, auth: request.headers.get("authorization") });
      return handler(request.url, init ?? {});
    });
    try {
      return { result: await run(), calls };
    } finally {
      spy.mockRestore();
    }
  };

  it("reads the learner's state with their token and nothing else", async () => {
    const { result, calls } = await withFetch(() => reply(200, stateRaw()), () => getVariantState(tenant, "tok", 11));
    expect(result?.effectiveCourseId).toBe(11);
    expect(calls).toEqual([{ url: "http://api.test/api/courses/11/variant", method: "GET", body: null, auth: "Bearer tok" }]);
  });

  it("treats a course without variants, an error and an unreachable API as no state", async () => {
    expect((await withFetch(() => reply(404, null), () => getVariantState(tenant, "tok", 11))).result).toBeNull();
    expect((await withFetch(() => reply(500, null), () => getVariantState(tenant, "tok", 11))).result).toBeNull();
    expect((await withFetch(() => Promise.reject(new Error("down")), () => getVariantState(tenant, "tok", 11))).result).toBeNull();
  });

  it("reads the public family without a token and gives null for null data", async () => {
    const { result, calls } = await withFetch(() => reply(200, family), () => getVariantFamily({ apiUrl: "http://api.a" } as Tenant, 11));
    expect(result?.variants).toHaveLength(3);
    expect(calls[0]?.auth).toBeNull();
    expect((await withFetch(() => reply(200, null), () => getVariantFamily({ apiUrl: "http://api.b" } as Tenant, 11))).result).toBeNull();
  });

  it("switches with the source and ids the API expects, and reports how many lessons carried over", async () => {
    const { result, calls } = await withFetch(() => reply(200, { ...stateRaw({ effectiveCourseId: 12 }), changed: true, carried: 4 }), () => applyChoice(tenant, "tok", 11, { kind: "switch", courseId: 12 }));
    expect(result).toEqual({ kind: "switched", courseId: 12, carried: 4 });
    expect(calls[0]).toMatchObject({ method: "PUT", url: "http://api.test/api/courses/11/variant", body: { course_id: 12, source: "self" } });
    const hint = await withFetch(() => reply(200, { ...stateRaw(), changed: true, carried: 0 }), () => applyChoice(tenant, "tok", 11, { kind: "hint-switch", courseId: 12, hintId: 5 }));
    expect(hint.calls[0]?.body).toEqual({ course_id: 12, source: "hint", hint_id: 5 });
    const placement = await withFetch(() => reply(200, { ...stateRaw(), changed: true, carried: 0 }), () => applyChoice(tenant, "tok", 11, { kind: "placement", courseId: 12, attemptId: "01ARZ3NDEKTSV4RRFFQ69G5FAV" }));
    expect(placement.calls[0]?.body).toEqual({ course_id: 12, source: "placement", attempt_id: "01ARZ3NDEKTSV4RRFFQ69G5FAV" });
  });

  it("says nothing changed when the learner picks the version they are in", async () => {
    const { result } = await withFetch(() => reply(200, { ...stateRaw(), changed: false, carried: 0 }), () => applyChoice(tenant, "tok", 11, { kind: "switch", courseId: 11 }));
    expect(result).toEqual({ kind: "unchanged" });
  });

  it("tells a locked family from a missing enrolment after a 403, and reports other failures", async () => {
    const locked = await withFetch((_url, init) => (init.method === "PUT" ? reply(403, null) : reply(200, stateRaw({ canChange: false, lockedReason: "locked" }))), () => applyChoice(tenant, "tok", 11, { kind: "switch", courseId: 12 }));
    expect(locked.result).toEqual({ kind: "locked" });
    const noAccess = await withFetch((_url, init) => (init.method === "PUT" ? reply(403, null) : reply(200, stateRaw({ enrolled: false, canChange: false, lockedReason: "not_enrolled" }))), () => applyChoice(tenant, "tok", 11, { kind: "switch", courseId: 12 }));
    expect(noAccess.result).toEqual({ kind: "no-access" });
    expect((await withFetch(() => reply(422, null), () => applyChoice(tenant, "tok", 11, { kind: "switch", courseId: 99 }))).result).toEqual({ kind: "failed" });
    expect((await withFetch(() => Promise.reject(new Error("down")), () => applyChoice(tenant, "tok", 11, { kind: "switch", courseId: 12 }))).result).toEqual({ kind: "failed" });
  });

  it("dismisses a hint on the hint's own route", async () => {
    const { result, calls } = await withFetch(() => reply(200, stateRaw()), () => applyChoice(tenant, "tok", 11, { kind: "hint-dismiss", hintId: 5 }));
    expect(result).toEqual({ kind: "dismissed" });
    expect(calls[0]).toMatchObject({ method: "POST", url: "http://api.test/api/courses/11/variant/hints/5/dismiss" });
  });

  it("sends the placement answers and reads the attempt", async () => {
    const attempt = { id: "01ARZ3NDEKTSV4RRFFQ69G5FAV", scores: { beginner: { correct: 1, total: 1 } }, suggestedCourseId: 12, reason: { key: "placement", params: {} }, createdAt: "2026-10-22T10:00:00+00:00" };
    const { result, calls } = await withFetch(() => reply(201, attempt), () => scorePlacement(tenant, "tok", 11, { "7": ["a"] }));
    expect(result?.suggestedCourseId).toBe(12);
    expect(calls[0]).toMatchObject({ method: "POST", url: "http://api.test/api/courses/11/placement/attempts", body: { answers: { "7": ["a"] } } });
    expect((await withFetch(() => reply(404, null), () => scorePlacement(tenant, "tok", 11, {}))).result).toBeNull();
  });
});
