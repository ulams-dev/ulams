import { afterEach, describe, expect, it, vi } from "vitest";
import type { Course, Tenant, Topic } from "@ulams/sdk";
import { topicKind } from "@ulams/sdk";
import { matchBffRule } from "../../src/lib/bff.ts";
import { codeExerciseLaunch, codeExerciseNode, codeExercisePreview, type CodeExerciseLaunch } from "../../src/lib/code-exercise.ts";
import { completionMode, topicDoc } from "../../src/lib/page-docs.ts";

const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost" } as Tenant;
const course = { id: 7, language: "pl" } as Course;

const launch = (over: Partial<CodeExerciseLaunch["exercise"]> = {}, attempts: CodeExerciseLaunch["attempts"] = { count: 0, best_score: null, last: null }): CodeExerciseLaunch => ({
  runner: { url: "https://content.test/runtime/code-runner/1/runner.html", version: 1 },
  exercise: {
    language: "javascript",
    files: [{ path: "sum.js", content: "export const sum = () => 0;" }],
    tests: [{ path: "sum.test.js", content: "test('adds', () => {});" }],
    timeout_ms: 7000,
    completion_rule: "on_pass",
    pass_score: 80,
    text: "Make sum add.",
    ...over,
  },
  attempts,
});

afterEach(() => vi.unstubAllGlobals());

describe("codeExerciseNode", () => {
  it("builds the catalogue node with the exercise's files, tests and settings", () => {
    const node = codeExerciseNode(launch(), { title: "Make sum add", course, topicId: 42, preview: false, locale: "pl" });
    expect(node.component).toBe("CodeExerciseLesson");
    expect(node.props).toEqual({
      src: "https://content.test/runtime/code-runner/1/runner.html",
      title: "Make sum add",
      topicId: 42,
      language: "javascript",
      files: [{ path: "sum.js", content: "export const sum = () => 0;" }],
      tests: [{ path: "sum.test.js", content: "test('adds', () => {});" }],
      timeoutMs: 7000,
      passScore: 80,
      completionRule: "on_pass",
      text: "Make sum add.",
      locale: "pl",
    });
  });

  it("shows the attempts so far, and leaves the topic id out in a preview so nothing is tracked", () => {
    const withAttempts = codeExerciseNode(launch({}, { count: 3, best_score: 66, last: null }), { title: "T", course, topicId: 42, preview: false }).props as Record<string, unknown>;
    expect(withAttempts.attempts).toEqual({ count: 3, best_score: 66 });
    const preview = codeExerciseNode(launch(), { title: "T", course, topicId: 42, preview: true }).props as Record<string, unknown>;
    expect(preview.topicId).toBeUndefined();
    expect(preview.attempts).toBeUndefined();
  });

  it("explains an error or a missing launch in a callout", () => {
    expect(codeExerciseNode({ error: "Nope" }, { title: "T", course, topicId: 1, preview: false })).toMatchObject({ component: "Callout", props: { text: "Nope" } });
    expect(codeExerciseNode(null, { title: "T", course, topicId: 1, preview: false }).component).toBe("Callout");
  });
});

describe("launches", () => {
  it("posts the launch with the learner's token, server side", async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: launch() }) });
    vi.stubGlobal("fetch", fetchMock);
    const result = await codeExerciseLaunch(tenant, "tok", 42);
    expect("runner" in result && result.runner.url).toContain("/runtime/code-runner/");
    expect(fetchMock.mock.calls[0]![0]).toBe("http://coffee.localhost/api/code-exercises/launches/42");
    expect(fetchMock.mock.calls[0]![1].method).toBe("POST");
    expect(fetchMock.mock.calls[0]![1].headers.Authorization).toBe("Bearer tok");
  });

  it("previews with the author's token through the admin endpoint", async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: launch() }) });
    vi.stubGlobal("fetch", fetchMock);
    await codeExercisePreview(tenant, "author", 42);
    expect(fetchMock.mock.calls[0]![0]).toBe("http://coffee.localhost/api/admin/code-exercises/topics/42/preview");
    expect(fetchMock.mock.calls[0]![1].method).toBeUndefined();
    expect(fetchMock.mock.calls[0]![1].headers.Authorization).toBe("Bearer author");
  });

  it("returns the API's explanation, or a generic one when it cannot be reached or answers nonsense", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: false, json: async () => ({ message: "Forbidden" }) }));
    expect(await codeExerciseLaunch(tenant, "tok", 1)).toEqual({ error: "Forbidden" });
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("down")));
    expect(await codeExerciseLaunch(tenant, "tok", 1)).toEqual({ error: "This exercise cannot be opened right now." });
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: { runner: { url: 5 } } }) }));
    expect(await codeExerciseLaunch(tenant, "tok", 1)).toEqual({ error: "This exercise cannot be opened right now." });
  });
});

describe("topic document and completion", () => {
  const topic = { id: 42, title: "Make sum add", topicable_type: "Ulams\\CodeExercises\\Models\\CodeExerciseTopic", topicable: { text: "Own text" } } as unknown as Topic;
  const input = { tenant, theme: "coffee" as const, course, lesson: undefined, topic, access: true, nextHref: "/next" };

  it("is a code exercise topic, completed by the lesson through ulams:complete", () => {
    expect(topicKind(topic.topicable_type)).toBe("code_exercise");
    expect(completionMode(topic)).toBe("external");
  });

  it("renders the lesson node, or the callout and the topic's text when the launch failed", () => {
    const ok = topicDoc({ ...input, codeExercise: launch() });
    expect(ok.children?.map((c) => c.component)).toEqual(["CodeExerciseLesson"]);
    const failed = topicDoc({ ...input, codeExercise: { error: "Switched off" } });
    expect(failed.children?.map((c) => c.component)).toEqual(["Callout", "Prose"]);
    const preview = topicDoc({ ...input, codeExercise: null, preview: true });
    expect(preview.children?.[0]).toMatchObject({ component: "Callout", props: { title: "Not run in preview" } });
  });
});

describe("BFF", () => {
  it("forwards only the attempts call of a code exercise, as a write", () => {
    expect(matchBffRule("POST", "/api/code-exercises/topics/42/attempts")?.writesProgress).toBe(true);
    expect(matchBffRule("POST", "/api/code-exercises/launches/42")).toBeNull();
    expect(matchBffRule("GET", "/api/code-exercises/topics/42/attempts")).toBeNull();
    expect(matchBffRule("POST", "/api/code-exercises/topics/x/attempts")).toBeNull();
    expect(matchBffRule("GET", "/api/admin/code-exercises/topics/42/attempts")).toBeNull();
    expect(matchBffRule("POST", "/api/admin/topics")).toBeNull();
  });
});
