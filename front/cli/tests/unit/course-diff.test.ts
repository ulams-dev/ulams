import { describe, expect, it } from "vitest";
import { diffLines } from "../../src/commands/course.ts";
import { exportCommand } from "../../src/registry/schema-export.ts";
import { getRegistry } from "../../src/registry/index.ts";
import { selectTools } from "../../src/mcp/tools.ts";
import { runCli } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456" };
const ok = (data: unknown) => ({ status: 200, body: { success: true, data, message: "OK" } });
const A = "01jb0000000000000000000001";
const B = "01jb0000000000000000000002";

const payload = {
  diffVersion: 1,
  courseId: 12,
  sessionId: "ses1",
  from: { versionId: "v1", versionNumber: 3, releaseId: A, releaseNumber: 1 },
  to: { versionId: "v2", versionNumber: 7, releaseId: B, releaseNumber: 3 },
  summary: {
    first: false,
    text: "1 lesson moved; 1 citation repointed; 1 answer key changed.",
    lines: [],
    counts: { added: 0, changed: 1, removed: 0, moved: 1, reordered: 0 },
    learnerImpact: { lessonsWithUpdatedNotice: 0 },
  },
  changes: [
    { id: "l2", type: "lesson", label: "Lesson 2.1", kind: "moved", description: "Lesson 2.1 moved from Module 1 to Module 2.", citations: { added: [], removed: [], repointed: [{ from: "frg_a", to: "frg_b" }] } },
    { id: "o1", type: "option", label: "Lesson 1.1 > Q1 > option A", kind: "changed", description: "Lesson 1.1 > Q1 > option A changed correct answer.", citations: null },
  ],
  quiz: [{ questionId: "q1", label: "Lesson 1.1 > Q1", answerKeyChanged: true, wording: false, correctBefore: ["A"], correctAfter: ["B"], consequence: "Learners who already answered this question get a re-attempt notice." }],
  fragments: { frg_a: { label: "§2 Brewing", text: "old", sourceRevision: { id: "r1", number: 1 } }, frg_b: { label: "§3 Brewing ratios", text: "new", sourceRevision: { id: "r2", number: 2 } } },
};

const url = "GET /api/admin/course-versions/courses/12/compare";

describe("course compare", () => {
  it("sends both sides and returns the payload as JSON", async () => {
    let query: Record<string, string> = {};
    const r = await runCli(["course", "compare", "12", "--from", A, "--to", B, "--json"], { env, routes: { [url]: (req) => ((query = req.query), ok(payload)) } });

    expect(r.code).toBe(0);
    expect(query).toMatchObject({ from: A, to: B });
    const data = r.json().data as typeof payload;
    expect(data.diffVersion).toBe(1);
    expect(data.summary.text).toContain("1 answer key changed");
  });

  it("prints the summary, the changes, the answer keys and the repointed citations for a person", async () => {
    const r = await runCli(["course", "compare", "12", "--from", A, "--to", B, "--output", "text"], { env, routes: { [url]: ok(payload) } });

    expect(r.code).toBe(0);
    expect(r.stdout).toContain("release #1 -> release #3: 1 lesson moved");
    expect(r.stdout).toContain("> Lesson 2.1 moved from Module 1 to Module 2.");
    expect(r.stdout).not.toContain("option A changed"); // an option is shown with its question
    expect(r.stdout).toContain("Lesson 1.1 > Q1: A -> B");
    expect(r.stdout).toContain("re-attempt notice");
    expect(r.stdout).toContain("Lesson 2.1: §2 Brewing -> §3 Brewing ratios");
  });

  it("says so when nothing changed", () => {
    const lines = diffLines({ ...payload, summary: { ...payload.summary, text: "No change." }, changes: [], quiz: [] });

    expect(lines).toEqual(["release #1 -> release #3: No change."]);
  });

  it("names a version when it was never released", () => {
    const lines = diffLines({ ...payload, to: { versionId: "v9", versionNumber: 9, releaseId: null, releaseNumber: null }, changes: [], quiz: [] });

    expect(lines[0]).toContain("release #1 -> version 9");
  });

  it("needs both sides as ids", async () => {
    expect((await runCli(["course", "compare", "12", "--from", A, "--json"], { env, routes: { [url]: ok(payload) } })).code).toBe(2);
    expect((await runCli(["course", "compare", "12", "--from", "nope", "--to", B, "--json"], { env, routes: { [url]: ok(payload) } })).code).toBe(2);
    expect((await runCli(["course", "compare", "abc", "--from", A, "--to", B, "--json"], { env, routes: { [url]: ok(payload) } })).code).toBe(2);
  });

  it("reports a version that is not part of the course", async () => {
    const r = await runCli(["course", "compare", "12", "--from", A, "--to", B, "--json"], { env, routes: { [url]: { status: 404, body: { success: false, message: "That version or release is not part of this course." } } } });

    expect(r.code).not.toBe(0);
    expect(r.stdout + r.stderr).toContain("not part of this course");
  });

  it("is a read-only MCP tool in the builder toolset", () => {
    const cmd = getRegistry().find((c) => c.id === "course.compare");

    expect(cmd).toBeDefined();
    expect(cmd?.kind).toBe("read");
    expect(exportCommand(cmd!).mcp).toMatchObject({ tool: "course_compare", expose: true, toolset: "builder", annotations: { readOnlyHint: true, destructiveHint: false } });
    const tools = selectTools(getRegistry(), { toolsets: ["builder"], readOnly: true, noDestructive: false, yes: false });
    expect(tools.map((c) => c.id)).toContain("course.compare");
  });

  it("is also `course diff --from --to`, with the course given by --course", async () => {
    let query: Record<string, string> = {};
    const r = await runCli(["course", "diff", "--course", "12", "--from", A, "--to", B, "--output", "text"], { env, routes: { [url]: (req) => ((query = req.query), ok(payload)) } });

    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(query).toMatchObject({ from: A, to: B });
    expect(r.stdout).toContain("release #1 -> release #3");
    const json = await runCli(["course", "diff", "--course", "12", "--from", A, "--to", B, "--json"], { env, routes: { [url]: ok(payload) } });
    expect((json.json().data as typeof payload).diffVersion).toBe(1);
  });

  it("exits 13 with --exit-code when the two sides differ", async () => {
    const r = await runCli(["course", "diff", "--course", "12", "--from", A, "--to", B, "--exit-code", "--json"], { env, routes: { [url]: ok(payload) } });
    expect(r.code).toBe(13);
    const same = await runCli(["course", "diff", "--course", "12", "--from", A, "--to", B, "--exit-code", "--json"], { env, routes: { [url]: ok({ ...payload, changes: [], quiz: [] }) } });
    expect(same.code).toBe(0);
  });

  it("needs both --from and --to, and a course", async () => {
    const one = await runCli(["course", "diff", "--course", "12", "--from", A, "--json"], { env, routes: { [url]: ok(payload) } });
    expect(one.code).toBe(2);
    expect(one.stdout + one.stderr).toContain("Pass both --from and --to");
    const noCourse = await runCli(["course", "diff", "--from", A, "--to", B, "--json"], { env, routes: { [url]: ok(payload) } });
    expect(noCourse.code).toBe(2);
    expect(noCourse.stdout + noCourse.stderr).toContain("not a course folder");
    const both = await runCli(["course", "diff", "--course", "12", "--from", A, "--to", B, "--version", "v2", "--json"], { env, routes: { [url]: ok(payload) } });
    expect(both.code).toBe(2);
  });
});
