import { describe, expect, it } from "vitest";
import { releaseChanges } from "../../src/commands/course.ts";
import { runCli, type Handler } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456" };
const ok = (data: unknown) => ({ status: 200, body: { success: true, data, message: "OK" } });
const none = { added: 0, changed: 0, removed: 0 };

const release = (number: number, trigger: string, summary: Record<string, unknown>) => ({
  id: `rel${number}`,
  number,
  trigger,
  versionId: `ver${number}`,
  versionNumber: number + 1,
  reason: null,
  previousReleaseId: number > 1 ? `rel${number - 1}` : null,
  courseId: 12,
  approvedBy: { id: 5, name: "Jane Doe" },
  sourceRevisions: null,
  releasedAt: `2026-10-2${number}T10:30:00+00:00`,
  summary: { first: false, counts: none, lessons: none, questions: none, citationsChanged: 0, answerKeysChanged: 0, ...summary },
});

const payload = {
  sessionId: "ses1",
  courseId: 12,
  releases: [
    release(2, "chat", { counts: { added: 0, changed: 2, removed: 0 }, lessons: { added: 0, changed: 1, removed: 0 }, answerKeysChanged: 1, citationsChanged: 2 }),
    release(1, "generate", { first: true, counts: { added: 20, changed: 0, removed: 0 }, lessons: { added: 4, changed: 0, removed: 0 } }),
  ],
  total: 2,
  page: 1,
  perPage: 25,
};

const routes = (extra: Record<string, Handler> = {}): Record<string, Handler> => ({ "GET /api/admin/course-versions/courses/12/releases": ok(payload), ...extra });

describe("course releases", () => {
  it("returns the payload as JSON and sends the paging query", async () => {
    let query: Record<string, string> = {};
    const r = await runCli(["course", "releases", "12", "--page", "2", "--json"], { env, routes: routes({ "GET /api/admin/course-versions/courses/12/releases": (req) => ((query = req.query), ok(payload)) }) });

    expect(r.code).toBe(0);
    const data = r.json().data as typeof payload;
    expect(data.releases.map((x) => x.number)).toEqual([2, 1]);
    expect(data.sessionId).toBe("ses1");
    expect(query.page).toBe("2");
  });

  it("prints one line per release for a person", async () => {
    const r = await runCli(["course", "releases", "12", "--output", "text"], { env, routes: routes() });

    expect(r.code).toBe(0);
    expect(r.stdout).toContain("#2");
    expect(r.stdout).toContain("chat");
    expect(r.stdout).toContain("Jane Doe");
    expect(r.stdout).toContain("1 lesson changed");
    expect(r.stdout).toContain("1 answer key changed");
    expect(r.stdout).toContain("first release, 4 lessons");
    expect(r.stdout).toContain("page 1/1, 2 total");
  });

  it("says so when nothing was applied yet", async () => {
    const r = await runCli(["course", "releases", "12", "--output", "text"], { env, routes: routes({ "GET /api/admin/course-versions/courses/12/releases": ok({ ...payload, releases: [], total: 0 }) }) });

    expect(r.stdout).toContain("No releases yet");
  });

  it("refuses a course id that is not a number", async () => {
    const r = await runCli(["course", "releases", "abc", "--json"], { env, routes: routes() });

    expect(r.code).toBe(2);
  });

  it("reports a course that was not made with the builder", async () => {
    const r = await runCli(["course", "releases", "12", "--json"], {
      env,
      routes: routes({ "GET /api/admin/course-versions/courses/12/releases": { status: 404, body: { success: false, message: "This course was not made with the Course Builder, so it has no releases. Adopt the course to track its versions." } } }),
    });

    expect(r.code).not.toBe(0);
    expect(r.stdout + r.stderr).toContain("Adopt the course");
  });

  it("shows one release with its document, and writes it to a file with --out", async () => {
    const detail = { ...release(2, "chat", {}), document: { schemaVersion: 3, modules: [] } };
    const routes_ = { "GET /api/admin/course-versions/releases/01r00000000000000000000002": ok(detail) };
    const json = await runCli(["course", "release", "01r00000000000000000000002", "--json"], { env, routes: routes_ });
    expect((json.json().data as { document: unknown }).document).toEqual({ schemaVersion: 3, modules: [] });

    const written: Record<string, string> = {};
    const file = await runCli(["course", "release", "01r00000000000000000000002", "--out", "r2.json", "--json"], {
      env,
      routes: routes_,
      fs: { readFile: async () => new Uint8Array(), readText: async () => "", exists: async () => false, writeFile: async (p, d) => void (written[p] = String(d)) },
    });
    expect(file.json().data).toMatchObject({ number: 2, file: "r2.json" });
    expect(file.json().data).not.toHaveProperty("document");
    expect(JSON.parse(written["r2.json"]!).document).toEqual({ schemaVersion: 3, modules: [] });

    const text = await runCli(["course", "release", "01r00000000000000000000002", "--output", "text"], { env, routes: routes_ });
    expect(text.stdout).toContain("Release #2 (chat)");
    expect(text.stdout).toContain("Approved by: Jane Doe");

    const bad = await runCli(["course", "release", "nope", "--json"], { env, routes: routes_ });
    expect(bad.code).toBe(2);
  });

  it("words the summary", () => {
    expect(releaseChanges({ first: false, counts: none, lessons: none, citationsChanged: 0, answerKeysChanged: 0 })).toBe("no content change");
    expect(releaseChanges({ first: false, counts: { added: 3, changed: 0, removed: 1 }, lessons: { added: 2, changed: 0, removed: 1 }, citationsChanged: 0, answerKeysChanged: 0 })).toBe("2 lessons added, 1 lesson removed, 1 other element changed");
  });
});
